<?php

use App\Jobs\NotifyAssistantSubmissionReviewers;
use App\Mail\AssistantSubmissionReviewMail;
use App\Models\AssistantSubmission;
use App\Models\User;
use App\Services\AssistantSubmissionNotificationService;
use App\Services\AssistantSubmissionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Testing\Fakes\MailFake;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['assistant_submissions.mailer' => null]);

function assistantNotificationAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

class AssistantReviewFaultMailFake extends MailFake
{
    public array $failEmails = [];

    public function send($view, array $data = [], $callback = null)
    {
        foreach ($view->to as $recipient) {
            if (in_array(strtolower($recipient['address']), $this->failEmails, true)) {
                throw new RuntimeException('Simulated mail transport failure.');
            }
        }

        return parent::send($view, $data, $callback);
    }
}

// Fakes are installed before fixtures or delivery configuration are changed.
$safeMail = Mail::fake();
Queue::fake();
$startLevel = DB::transactionLevel();
$failed = false;
DB::beginTransaction();

try {
    assistantNotificationAssert(Schema::hasTable('assistant_submissions'), 'Apply the assistant submissions migration before this smoke test.');
    $existingUser = User::query()->firstOrFail();
    $creator = User::query()->create([
        'name' => 'Meskerom Notification Smoke',
        'email' => 'assistant-notification-'.Str::lower(Str::random(12)).'@example.test',
        'password' => $existingUser->getRawOriginal('password'),
        'user_type' => 'staff',
        'role_id' => $existingUser->role_id,
        'is_disabled' => false,
    ]);
    $submission = AssistantSubmission::query()->create([
        'reference_no' => 'AS-MAIL-SMOKE-'.Str::upper(Str::random(10)),
        'kind' => 'purchase_request', 'status' => 'pending', 'created_by' => $creator->id,
        'payload' => ['bank_id' => 'raw-bank-id-must-not-be-printed'],
        'summary' => [
            'Programme' => 'Regional Development', 'Purpose' => 'Office supplies',
            'Total' => 'USD 320.00',
            'Line items' => [['Resource' => 'Printer paper', 'Quantity' => '4', 'Unit cost' => '80.00', 'Total' => '320.00']],
        ],
        'documents' => [['field' => 'attachments.0', 'path' => 'unused-smoke-path', 'name' => 'Supplier quotation.pdf', 'mime' => 'application/pdf']],
        'notification_status' => 'queued', 'notification_recipients' => [],
    ]);

    $primary = new User;
    $primary->forceFill(['id' => (string) Str::uuid(), 'name' => 'Main Project Coordinator', 'email' => 'coordinator@example.test']);
    $admin = new User;
    $admin->forceFill(['id' => (string) Str::uuid(), 'name' => 'Other Administrator', 'email' => 'ADMIN@example.test']);
    $duplicate = new User;
    $duplicate->forceFill(['id' => (string) Str::uuid(), 'name' => 'Duplicate email', 'email' => 'admin@example.test']);
    $invalid = new User;
    $invalid->forceFill(['id' => (string) Str::uuid(), 'name' => 'Invalid email', 'email' => 'not-an-email']);
    $reviewers = new class extends AssistantSubmissionService
    {
        public array $users = [];

        public function reviewers(AssistantSubmission $submission)
        {
            return collect($this->users);
        }
    };
    $reviewers->users = [$primary, $admin, $duplicate, $invalid];
    $notifications = app(AssistantSubmissionNotificationService::class);
    $job = new NotifyAssistantSubmissionReviewers((string) $submission->id);

    config(['mail.default' => 'log']);
    config(['assistant_submissions.mailer' => 'smtp']);
    assistantNotificationAssert($notifications->hasDeliveryTransport(), 'Dedicated SMTP mailer should work while general mail stays on log.');
    config(['assistant_submissions.mailer' => null]);
    $notifications->notify($submission);
    assistantNotificationAssert($submission->fresh()->notification_status === 'mail_not_configured', 'Log mailer was incorrectly presented as a delivery configuration.');
    Queue::assertNothingPushed();
    Mail::assertNothingSent();

    config(['mail.default' => 'failover', 'mail.mailers.failover' => ['transport' => 'failover', 'mailers' => ['smtp', 'log']]]);
    assistantNotificationAssert(! $notifications->hasDeliveryTransport(), 'A log fallback was incorrectly accepted as guaranteed delivery.');
    config(['mail.default' => 'smtp', 'assistant_submissions.coordinator_email' => 'coordinator@example.test']);
    $notifications->notify($submission);
    assistantNotificationAssert($submission->fresh()->notification_status === 'queued', 'Submission was not marked queued.');
    Queue::assertNothingPushed(); // Dispatch must wait for the outer transaction commit.

    $job->handle($reviewers, $notifications);
    Mail::assertSent(AssistantSubmissionReviewMail::class, 2);
    $submission->refresh();
    assistantNotificationAssert($submission->notification_status === 'sent', 'Successful notification did not reach sent state.');
    assistantNotificationAssert($submission->notification_recipients === ['coordinator@example.test', 'admin@example.test'], 'Recipient ledger did not deduplicate and normalize successful emails.');
    $job->handle($reviewers, $notifications);
    Mail::assertSent(AssistantSubmissionReviewMail::class, 2);

    $faultMail = new AssistantReviewFaultMailFake($safeMail->manager);
    $faultMail->failEmails = ['admin@example.test'];
    Mail::swap($faultMail);
    $submission->forceFill(['notification_status' => 'queued', 'notification_recipients' => []])->save();
    $failureObserved = false;
    try {
        $job->handle($reviewers, $notifications);
    } catch (RuntimeException $exception) {
        $failureObserved = str_contains($exception->getMessage(), 'could not be delivered');
    }
    assistantNotificationAssert($failureObserved, 'Partial delivery failure was swallowed instead of retrying.');
    $submission->refresh();
    assistantNotificationAssert($submission->status === 'pending' && $submission->notification_status === 'failed', 'Mail failure altered approval state or hid delivery failure.');
    assistantNotificationAssert($submission->notification_recipients === ['coordinator@example.test'], 'Successful recipient was not preserved after partial failure.');
    $faultMail->failEmails = [];
    $job->handle($reviewers, $notifications);
    Mail::assertSent(AssistantSubmissionReviewMail::class, 2);
    Mail::assertSent(AssistantSubmissionReviewMail::class, fn ($mail) => $mail->hasTo('coordinator@example.test'));
    assistantNotificationAssert($submission->fresh()->notification_status === 'sent', 'Retry did not complete outstanding delivery.');

    Mail::fake();
    $reviewers->users = [];
    $submission->forceFill(['notification_status' => 'queued', 'notification_recipients' => []])->save();
    $job->handle($reviewers, $notifications);
    assistantNotificationAssert($submission->fresh()->notification_status === 'no_recipients', 'Missing reviewers were not made explicit.');
    Mail::assertNothingSent();

    $reviewers->users = [$primary, $admin];
    $submission->forceFill(['status' => 'approved', 'notification_status' => 'queued'])->save();
    $job->handle($reviewers, $notifications);
    assistantNotificationAssert($submission->fresh()->notification_status === 'cancelled', 'Reviewed request retained a stale queued approval alert.');
    Mail::assertNothingSent();

    $submission->forceFill(['status' => 'pending', 'notification_status' => 'sending'])->save();
    $job->failed(new RuntimeException('Simulated exhausted retries.'));
    assistantNotificationAssert($submission->fresh()->notification_status === 'failed', 'Exhausted attempts did not leave a visible failure.');

    $submission->refresh()->load(['creator', 'reviewer']);
    $mail = new AssistantSubmissionReviewMail($submission, $primary);
    $html = $mail->render();
    $attachment = $mail->rawAttachments[0] ?? [];
    assistantNotificationAssert(str_contains($html, 'Meskerom Notification Smoke') && str_contains($html, 'Urgent attention requested'), 'Urgent creator notification content is missing.');
    assistantNotificationAssert(str_contains($html, 'coordinator@example.test') && str_contains($html, 'Any authorized administrator'), 'Main coordinator and alternative admin approval were not explained.');
    assistantNotificationAssert(str_starts_with($attachment['data'] ?? '', '%PDF-') && strlen($attachment['data']) > 3000, 'Email lacks a populated PDF attachment.');
    assistantNotificationAssert(($attachment['options']['mime'] ?? '') === 'application/pdf', 'PDF attachment MIME type is incorrect.');
    $pdfHtml = view('administrative-assistant.submissions.pdf', ['submission' => $submission])->render();
    foreach (['Pending approval - not effective in reports', 'Supplier quotation.pdf', 'Printer paper', 'USD 320.00'] as $expected) {
        assistantNotificationAssert(str_contains($pdfHtml, $expected), 'PDF is missing: '.$expected);
    }
    assistantNotificationAssert(! str_contains($html.$pdfHtml, 'raw-bank-id-must-not-be-printed'), 'Internal payload IDs leaked into the email or PDF.');
    $submission->kind = 'disbursement';
    assistantNotificationAssert(str_contains(view('administrative-assistant.submissions.pdf', ['submission' => $submission])->render(), '<h1>Disbursement</h1>'), 'Disbursement PDF has the wrong type.');
    Mail::assertNothingSent();

    echo "ASSISTANT_SUBMISSION_NOTIFICATIONS_OK\n";
    echo "Covered: safe transport, deferred queue, all recipients, duplicate retry, partial failure recovery, missing reviewers, reviewed cancellation, failure state, email and PDF.\n";
} catch (Throwable $exception) {
    $failed = true;
    fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n");
} finally {
    while (DB::transactionLevel() > $startLevel) {
        DB::rollBack();
    }
    Queue::assertNothingPushed();
    Mail::fake();
}

exit($failed ? 1 : 0);
