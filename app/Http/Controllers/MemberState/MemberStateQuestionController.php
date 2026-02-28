<?php

namespace App\Http\Controllers\MemberState;

use App\Http\Controllers\Controller;
use App\Mail\MemberStateQuestionSubmittedNotification;
use App\Models\MemberStateQuestion;
use App\Models\SystemAuditLog;
use App\Models\User;
use App\Support\IpGeo;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MemberStateQuestionController extends Controller
{
    public function index(Request $request)
    {
        $memberStateId = $request->user()->member_state_id;

        $baseQuery = MemberStateQuestion::query()
            ->where('member_state_id', $memberStateId);

        $query = (clone $baseQuery)
            ->with(['answeredBy:id,name,email']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $questions = $query
            ->orderByDesc('asked_on')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'open' => (clone $baseQuery)->where('status', 'open')->count(),
            'in_review' => (clone $baseQuery)->where('status', 'in_review')->count(),
            'answered' => (clone $baseQuery)->where('status', 'answered')->count(),
            'closed' => (clone $baseQuery)->where('status', 'closed')->count(),
        ];

        $questionResponders = $this->questionResponderQuery()
            ->get(['id', 'name', 'email']);

        return view('member-state.questions.index', [
            'memberState' => $request->user()->memberState,
            'questions' => $questions,
            'stats' => $stats,
            'questionResponderCount' => $questionResponders->count(),
            'questionResponderNames' => $questionResponders->pluck('name')->filter()->values(),
            'filters' => [
                'status' => (string) $request->input('status', ''),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'asked_on' => ['required', 'date'],
            'subject' => ['required', 'string', 'max:255'],
            'question_text' => ['required', 'string', 'max:12000'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
        ]);

        $questionResponders = $this->questionResponderQuery()
            ->get(['id', 'name', 'email']);

        if ($questionResponders->isEmpty()) {
            return back()
                ->withInput()
                ->withErrors([
                    'subject' => 'No AU question responders are configured. Please contact the superadmin.',
                ]);
        }

        $primaryResponder = $questionResponders->first();

        $question = MemberStateQuestion::create(array_merge($validated, [
            'member_state_id' => $request->user()->member_state_id,
            'responsible_officer_id' => $primaryResponder?->id,
            'responsible_officer_email' => $primaryResponder?->email,
            'status' => 'open',
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));

        $this->notifyQuestionResponders($question, $questionResponders, $request);

        $this->logQuestionAudit(
            $request,
            'member_state_question_submitted',
            'Member state submitted a question to the AU desk.',
            [
                'question_id' => $question->id,
                'member_state_id' => $question->member_state_id,
                'status' => $question->status,
                'priority' => $question->priority,
                'primary_responder_id' => $question->responsible_officer_id,
                'responder_count' => $questionResponders->count(),
            ],
            $question
        );

        return back()->with('success', 'Question submitted and routed automatically to AU question responders.');
    }

    public function destroy(Request $request, MemberStateQuestion $question)
    {
        abort_unless($question->member_state_id === $request->user()->member_state_id, 403);

        if ($question->status === 'answered') {
            return back()->with('error', 'Answered questions cannot be deleted.');
        }

        $payload = [
            'question_id' => $question->id,
            'member_state_id' => $question->member_state_id,
            'status' => $question->status,
            'priority' => $question->priority,
            'responsible_officer_id' => $question->responsible_officer_id,
        ];

        $question->delete();

        $this->logQuestionAudit(
            $request,
            'member_state_question_deleted',
            'Member state deleted a question entry.',
            $payload
        );

        return back()->with('success', 'Question deleted.');
    }

    private function questionResponderQuery()
    {
        return User::query()
            ->whereNotNull('email')
            ->where(function ($query) {
                $query->whereNull('user_type')
                    ->orWhere('user_type', '!=', 'member_state');
            })
            ->where(function ($query) {
                $query->whereHas('permissions', function ($permissionQuery) {
                    $permissionQuery->where('name', 'questions.respond');
                })->orWhereHas('role.permissions', function ($permissionQuery) {
                    $permissionQuery->where('name', 'questions.respond');
                })->orWhereHas('role', function ($roleQuery) {
                    $roleQuery->where('name', 'System Admin');
                });
            })
            ->orderBy('name');
    }

    private function notifyQuestionResponders(
        MemberStateQuestion $question,
        Collection $questionResponders,
        Request $request
    ): void {
        $recipientEmails = $questionResponders
            ->pluck('email')
            ->filter(fn ($email) => filled($email))
            ->unique()
            ->values();

        if ($recipientEmails->isEmpty()) {
            $this->logQuestionAudit(
                $request,
                'question_responder_email_alert_skipped',
                'Question responder email notification skipped because no recipient email is configured.',
                [
                    'question_id' => $question->id,
                    'member_state_id' => $question->member_state_id,
                ],
                $question
            );
            return;
        }

        try {
            $question->loadMissing(['memberState', 'creator']);
            Mail::to($recipientEmails->all())->send(new MemberStateQuestionSubmittedNotification($question));

            $this->logQuestionAudit(
                $request,
                'question_responder_email_alert_sent',
                'Back-office question responders were notified about a new member-state question.',
                [
                    'question_id' => $question->id,
                    'member_state_id' => $question->member_state_id,
                    'recipient_count' => $recipientEmails->count(),
                    'recipient_emails' => $recipientEmails->all(),
                ],
                $question
            );
        } catch (\Throwable $exception) {
            Log::error('Failed to send AU responder question alert email.', [
                'question_id' => $question->id,
                'member_state_id' => $question->member_state_id,
                'recipient_count' => $recipientEmails->count(),
                'message' => $exception->getMessage(),
            ]);

            $this->logQuestionAudit(
                $request,
                'question_responder_email_alert_failed',
                'Failed to send question responder notification email for member-state question.',
                [
                    'question_id' => $question->id,
                    'member_state_id' => $question->member_state_id,
                    'recipient_count' => $recipientEmails->count(),
                ],
                $question
            );
        }
    }

    private function logQuestionAudit(
        Request $request,
        string $action,
        string $actionMessage,
        array $payload = [],
        ?MemberStateQuestion $question = null
    ): void {
        try {
            SystemAuditLog::create([
                'user_id' => optional($request->user())->id,
                'module' => 'questions',
                'action' => $action,
                'action_message' => $actionMessage,
                'description' => $actionMessage,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route_name' => optional($request->route())->getName(),
                'ip_address' => $request->ip(),
                'country' => IpGeo::countryForIp($request->ip()),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'status_code' => 200,
                'payload' => array_merge([
                    'question_id' => $question?->id,
                    'member_state_id' => $question?->member_state_id,
                ], $payload),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to persist member-state question audit log entry.', [
                'action' => $action,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
