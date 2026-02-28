<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Mail\MemberStateQuestionResponseNotification;
use App\Models\AuMemberState;
use App\Models\MemberStateQuestion;
use App\Models\SystemAuditLog;
use App\Models\User;
use App\Support\IpGeo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MemberStateQuestionAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:questions.view')->only(['index']);
        $this->middleware('permission:questions.respond')->only(['respond']);
    }

    public function index(Request $request)
    {
        $baseQuery = MemberStateQuestion::query();

        $query = MemberStateQuestion::query()
            ->with([
                'memberState:id,name,code,code_alpha2',
                'creator:id,name,email',
                'answeredBy:id,name,email',
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        if ($request->filled('member_state_id')) {
            $query->where('member_state_id', $request->input('member_state_id'));
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($term) {
                $inner->where('subject', 'like', '%' . $term . '%')
                    ->orWhere('question_text', 'like', '%' . $term . '%')
                    ->orWhere('answer_text', 'like', '%' . $term . '%')
                    ->orWhereHas('memberState', function ($memberStateQuery) use ($term) {
                        $memberStateQuery->where('name', 'like', '%' . $term . '%')
                            ->orWhere('code', 'like', '%' . $term . '%')
                            ->orWhere('code_alpha2', 'like', '%' . $term . '%');
                    });
            });
        }

        $questions = $query
            ->orderByRaw("
                CASE status
                    WHEN 'open' THEN 1
                    WHEN 'in_review' THEN 2
                    WHEN 'answered' THEN 3
                    WHEN 'closed' THEN 4
                    ELSE 5
                END
            ")
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
            'urgent' => (clone $baseQuery)->where('priority', 'urgent')->count(),
        ];

        return view('system.questions.index', [
            'questions' => $questions,
            'memberStates' => AuMemberState::query()->ordered()->get(['id', 'name']),
            'stats' => $stats,
            'filters' => [
                'q' => (string) $request->input('q', ''),
                'status' => (string) $request->input('status', ''),
                'priority' => (string) $request->input('priority', ''),
                'member_state_id' => (string) $request->input('member_state_id', ''),
            ],
        ]);
    }

    public function respond(Request $request, MemberStateQuestion $question)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:in_review,answered,closed'],
            'answer_text' => ['required', 'string', 'max:12000'],
        ]);

        $question->update([
            'status' => $validated['status'],
            'answer_text' => $validated['answer_text'],
            'answered_by' => $request->user()->id,
            'answered_at' => now(),
            'updated_by' => $request->user()->id,
        ]);

        $question->refresh()->loadMissing(['memberState', 'creator', 'answeredBy']);

        $this->logQuestionAudit(
            $request,
            'question_response_sent',
            'AU back-office responded to a member-state question.',
            [
                'question_id' => $question->id,
                'member_state_id' => $question->member_state_id,
                'status' => $question->status,
                'priority' => $question->priority,
            ],
            $question
        );

        $this->notifyMemberStateQuestionResponse($request, $question);

        return back()->with('success', 'Question response saved and member-state notification processed.');
    }

    private function notifyMemberStateQuestionResponse(Request $request, MemberStateQuestion $question): void
    {
        $recipientEmails = User::query()
            ->where('user_type', 'member_state')
            ->where('member_state_id', $question->member_state_id)
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->unique()
            ->values();

        if ($recipientEmails->isEmpty()) {
            $this->logQuestionAudit(
                $request,
                'question_response_email_skipped',
                'No member-state email recipients were configured for this question response.',
                [
                    'question_id' => $question->id,
                    'member_state_id' => $question->member_state_id,
                ],
                $question
            );
            return;
        }

        try {
            Mail::to($recipientEmails->all())->send(new MemberStateQuestionResponseNotification($question));

            $this->logQuestionAudit(
                $request,
                'question_response_email_sent',
                'Question response notification email sent to member-state users.',
                [
                    'question_id' => $question->id,
                    'member_state_id' => $question->member_state_id,
                    'recipient_count' => $recipientEmails->count(),
                ],
                $question
            );
        } catch (\Throwable $exception) {
            Log::error('Failed to send member-state question response email.', [
                'question_id' => $question->id,
                'member_state_id' => $question->member_state_id,
                'message' => $exception->getMessage(),
            ]);

            $this->logQuestionAudit(
                $request,
                'question_response_email_failed',
                'Failed to send question response notification email to member-state users.',
                [
                    'question_id' => $question->id,
                    'member_state_id' => $question->member_state_id,
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
            Log::warning('Failed to persist back-office question audit log entry.', [
                'action' => $action,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
