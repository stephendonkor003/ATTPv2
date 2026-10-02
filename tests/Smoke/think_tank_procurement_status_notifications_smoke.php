<?php

use App\Jobs\SendThinkTankProcurementStatusNotification;
use App\Mail\ThinkTankProcurementStatusMail;
use App\Models\ConsortiumThinkTank;
use App\Models\Role;
use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use App\Models\ThinkTankProcurementStatusNotification;
use App\Models\User;
use App\Services\ThinkTankProcurementStatusNotificationService;
use App\Services\ThinkTankProcurementWorkflowService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$ensure = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

Mail::fake();
DB::beginTransaction();

try {
    $member = ConsortiumThinkTank::query()->whereNotNull('consortium_id')->firstOrFail();
    $token = Str::lower(Str::random(14));
    $systemAdminRole = Role::query()->where('name', 'System Admin')->firstOrFail();
    $procurementRole = Role::query()->where('name', 'Procurement Officer')->firstOrFail();
    $thinkTankRoleId = Role::query()->where('name', 'Think Tank User')->value('id');

    $newUser = static function (array $attributes) use ($token): User {
        return User::query()->create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => Str::random(40),
            'user_type' => $attributes['user_type'],
            'role_id' => $attributes['role_id'] ?? null,
            'think_tank_member_id' => $attributes['think_tank_member_id'] ?? null,
            'think_tank_access_level' => $attributes['think_tank_access_level'] ?? null,
            'must_change_password' => false,
            'password_changed_at' => now(),
            'otp_verified_at' => now(),
            'is_disabled' => $attributes['is_disabled'] ?? false,
            'is_blacklisted' => $attributes['is_blacklisted'] ?? false,
        ]);
    };

    $secretariatAdmin = $newUser([
        'name' => 'Notification Smoke Secretariat Admin',
        'email' => "procurement-status-admin-{$token}@example.test",
        'user_type' => 'admin',
        'role_id' => $systemAdminRole->id,
    ]);
    $secretariatOfficer = $newUser([
        'name' => 'Notification Smoke Secretariat Officer',
        'email' => "procurement-status-officer-{$token}@example.test",
        'user_type' => 'admin',
        'role_id' => $procurementRole->id,
    ]);
    $thinkTankAdmin = $newUser([
        'name' => 'Notification Smoke Think Tank Admin',
        'email' => "procurement-status-tt-admin-{$token}@example.test",
        'user_type' => 'think_tank',
        'role_id' => $thinkTankRoleId,
        'think_tank_member_id' => $member->id,
        'think_tank_access_level' => User::THINK_TANK_ACCESS_ADMIN,
    ]);
    $thinkTankOfficer = $newUser([
        'name' => 'Notification Smoke Think Tank Procurement Officer',
        'email' => "procurement-status-tt-officer-{$token}@example.test",
        'user_type' => 'think_tank',
        'role_id' => $thinkTankRoleId,
        'think_tank_member_id' => $member->id,
        'think_tank_access_level' => User::THINK_TANK_ACCESS_PROCUREMENT,
    ]);
    $thinkTankMeOfficer = $newUser([
        'name' => 'Notification Smoke M and E Officer',
        'email' => "procurement-status-tt-me-{$token}@example.test",
        'user_type' => 'think_tank',
        'role_id' => $thinkTankRoleId,
        'think_tank_member_id' => $member->id,
        'think_tank_access_level' => User::THINK_TANK_ACCESS_ME,
    ]);
    $blockedThinkTankAdmin = $newUser([
        'name' => 'Notification Smoke Blocked Admin',
        'email' => "procurement-status-blocked-{$token}@example.test",
        'user_type' => 'think_tank',
        'role_id' => $thinkTankRoleId,
        'think_tank_member_id' => $member->id,
        'think_tank_access_level' => User::THINK_TANK_ACCESS_ADMIN,
        'is_disabled' => true,
    ]);

    $plan = ThinkTankProcurementPlan::query()->create([
        'consortium_id' => $member->consortium_id,
        'think_tank_member_id' => $member->id,
        'plan_code' => 'NOTICE-'.Str::upper($token),
        'title' => 'Procurement status notification smoke plan',
        'fiscal_year' => '2026',
        'estimated_budget' => 7500,
        'currency' => 'USD',
        'status' => ThinkTankProcurementPlan::STATUS_DRAFT,
        'version' => 1,
        'created_by' => $thinkTankAdmin->id,
    ]);
    $item = $plan->items()->create([
        'item_code' => $plan->plan_code.'-001',
        'title' => 'Notification smoke procurement activity',
        'procurement_category' => 'consulting_services',
        'procurement_method' => 'QCBS',
        'estimated_amount' => 7500,
        'currency' => 'USD',
        'status' => ThinkTankProcurementItem::STATUS_DRAFT,
        'created_by' => $thinkTankAdmin->id,
        'updated_by' => $thinkTankAdmin->id,
    ]);
    $item->documents()->create([
        'document_type' => 'tor',
        'document_name' => 'Terms of Reference',
        'original_name' => 'notification-smoke-tor.pdf',
        'file_path' => "think-tank-procurement/{$plan->id}/{$item->id}/notification-smoke-tor.pdf",
        'mime_type' => 'application/pdf',
        'file_size' => 128,
        'uploaded_by' => $thinkTankAdmin->id,
    ]);

    app(ThinkTankProcurementWorkflowService::class)->submit($plan, $thinkTankAdmin);
    $event = $plan->events()->where('action', 'plan_submitted')->latest('created_at')->firstOrFail();
    $notices = ThinkTankProcurementStatusNotification::query()
        ->where('event_id', $event->id)
        ->get()
        ->keyBy(fn (ThinkTankProcurementStatusNotification $notice): string => strtolower($notice->recipient_email));

    foreach ([$secretariatAdmin, $secretariatOfficer, $thinkTankAdmin, $thinkTankOfficer] as $expected) {
        $ensure($notices->has(strtolower($expected->email)), "Expected recipient missing: {$expected->name}");
    }
    foreach ([$thinkTankMeOfficer, $blockedThinkTankAdmin] as $excluded) {
        $ensure(! $notices->has(strtolower($excluded->email)), "Ineligible recipient was staged: {$excluded->name}");
    }
    $adminNotice = $notices->get(strtolower($secretariatAdmin->email));
    $ensure(in_array('secretariat_administrator', $adminNotice->audiences, true), 'Administrator audience missing.');
    $ensure(in_array('secretariat_procurement_officer', $adminNotice->audiences, true), 'Case-insensitive recipient deduplication did not merge audiences.');

    $revokedNotice = $notices->get(strtolower($thinkTankOfficer->email));
    $thinkTankOfficer->forceFill(['is_disabled' => true])->save();
    (new SendThinkTankProcurementStatusNotification((string) $revokedNotice->id))
        ->handle(app(ThinkTankProcurementStatusNotificationService::class));
    $ensure(
        $revokedNotice->fresh()->failure_code === 'recipient_no_longer_eligible',
        'Revoked recipient was not suppressed before delivery.'
    );

    $eligibleNotice = $notices->get(strtolower($thinkTankAdmin->email));
    (new SendThinkTankProcurementStatusNotification((string) $eligibleNotice->id))
        ->handle(app(ThinkTankProcurementStatusNotificationService::class));
    $ensure($eligibleNotice->fresh()->status === ThinkTankProcurementStatusNotification::STATUS_SENT, 'Eligible delivery was not marked sent.');
    (new SendThinkTankProcurementStatusNotification((string) $eligibleNotice->id))
        ->handle(app(ThinkTankProcurementStatusNotificationService::class));
    Mail::assertSent(ThinkTankProcurementStatusMail::class, 1);

    $sentMail = null;
    Mail::assertSent(ThinkTankProcurementStatusMail::class, function (ThinkTankProcurementStatusMail $mail) use (&$sentMail, $eligibleNotice): bool {
        if ((string) $mail->notification->id !== (string) $eligibleNotice->id) {
            return false;
        }
        $sentMail = $mail;

        return true;
    });
    $sentMail->build();
    $ensure(count($sentMail->rawAttachments) === 1, 'Status email does not contain exactly one PDF summary.');
    $attachment = $sentMail->rawAttachments[0];
    $ensure(($attachment['options']['mime'] ?? null) === 'application/pdf', 'Status attachment is not a PDF.');
    $ensure(str_starts_with((string) $attachment['data'], '%PDF'), 'Status attachment does not contain PDF bytes.');
    $ensure(str_contains($sentMail->render(), 'African Think Tank Platform'), 'Branded email body did not render.');

    $thinkTankOfficer->forceFill(['is_disabled' => false])->save();
    $workflow = app(ThinkTankProcurementWorkflowService::class);
    $approvedPlan = $workflow->decidePlan($plan->fresh(), $secretariatOfficer, 'approve', null);
    $readyItem = $workflow->recordNoObjection($item->fresh(), $secretariatOfficer, [
        'step_reference' => 'STEP-'.$token,
        'no_objection_reference' => 'WB-NO-'.$token,
        'no_objection_date' => now()->toDateString(),
        'no_objection_notes' => 'Formal World Bank no-objection recorded by the smoke test.',
    ]);
    $ensure($approvedPlan->status === ThinkTankProcurementPlan::STATUS_APPROVED, 'Secretariat approval did not persist.');
    $ensure($readyItem->status === ThinkTankProcurementItem::STATUS_NO_OBJECTION, 'No-objection did not make the item ready to execute.');

    $readyEvent = $plan->events()
        ->where('action', 'world_bank_no_objection_recorded')
        ->where('item_id', $item->id)
        ->latest('created_at')
        ->firstOrFail();
    $readyNotices = ThinkTankProcurementStatusNotification::query()
        ->where('event_id', $readyEvent->id)
        ->get()
        ->keyBy(fn (ThinkTankProcurementStatusNotification $notice): string => strtolower($notice->recipient_email));
    foreach ([$secretariatAdmin, $secretariatOfficer, $thinkTankAdmin, $thinkTankOfficer] as $expected) {
        $ensure($readyNotices->has(strtolower($expected->email)), "No-objection recipient missing: {$expected->name}");
    }
    $ensure(! $readyNotices->has(strtolower($thinkTankMeOfficer->email)), 'M&E user received a procurement no-objection notice.');

    $readyNotice = $readyNotices->get(strtolower($thinkTankAdmin->email));
    (new SendThinkTankProcurementStatusNotification((string) $readyNotice->id))
        ->handle(app(ThinkTankProcurementStatusNotificationService::class));
    $ensure($readyNotice->fresh()->status === ThinkTankProcurementStatusNotification::STATUS_SENT, 'No-objection notice was not marked sent.');
    Mail::assertSent(ThinkTankProcurementStatusMail::class, 2);
    Mail::assertSent(ThinkTankProcurementStatusMail::class, function (ThinkTankProcurementStatusMail $mail) use ($readyNotice): bool {
        return (string) $mail->notification->id === (string) $readyNotice->id
            && str_contains(strtolower($mail->notification->heading), 'ready to execute')
            && str_contains(strtolower($mail->render()), 'ready to execute');
    });

    echo "THINK_TANK_PROCUREMENT_STATUS_NOTIFICATIONS_OK\n";
} finally {
    DB::rollBack();
}

