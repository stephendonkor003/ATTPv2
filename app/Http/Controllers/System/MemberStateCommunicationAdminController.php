<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Mail\MemberStateCommunicationResponseNotification;
use App\Models\AuMemberState;
use App\Models\MemberStateCommunication;
use App\Models\MemberStateCommunicationAttachment;
use App\Models\SystemAuditLog;
use App\Models\User;
use App\Support\IpGeo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class MemberStateCommunicationAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:communications.view')->only(['index', 'downloadAttachment']);
        $this->middleware('permission:communications.respond')->only(['respond']);
    }

    public function index(Request $request)
    {
        $baseQuery = MemberStateCommunication::query();

        $query = MemberStateCommunication::query()
            ->with([
                'memberState:id,name,code,code_alpha2',
                'creator:id,name,email',
                'responder:id,name,email',
                'attachments',
            ]);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->input('channel'));
        }

        if ($request->filled('member_state_id')) {
            $query->where('member_state_id', $request->input('member_state_id'));
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($term) {
                $inner->where('subject', 'like', '%' . $term . '%')
                    ->orWhere('message', 'like', '%' . $term . '%')
                    ->orWhere('response_text', 'like', '%' . $term . '%')
                    ->orWhereHas('memberState', function ($memberStateQuery) use ($term) {
                        $memberStateQuery->where('name', 'like', '%' . $term . '%')
                            ->orWhere('code', 'like', '%' . $term . '%')
                            ->orWhere('code_alpha2', 'like', '%' . $term . '%');
                    });
            });
        }

        $communications = $query
            ->orderByRaw("
                CASE status
                    WHEN 'pending_response' THEN 1
                    WHEN 'in_review' THEN 2
                    WHEN 'answered' THEN 3
                    WHEN 'closed' THEN 4
                    ELSE 5
                END
            ")
            ->orderByDesc('communication_date')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'pending_response' => (clone $baseQuery)->where('status', 'pending_response')->count(),
            'in_review' => (clone $baseQuery)->where('status', 'in_review')->count(),
            'answered' => (clone $baseQuery)->where('status', 'answered')->count(),
            'closed' => (clone $baseQuery)->where('status', 'closed')->count(),
            'with_attachments' => (clone $baseQuery)->whereHas('attachments')->count(),
        ];

        return view('system.communications.index', [
            'communications' => $communications,
            'memberStates' => AuMemberState::query()->ordered()->get(['id', 'name']),
            'stats' => $stats,
            'filters' => [
                'q' => (string) $request->input('q', ''),
                'status' => (string) $request->input('status', ''),
                'channel' => (string) $request->input('channel', ''),
                'member_state_id' => (string) $request->input('member_state_id', ''),
            ],
        ]);
    }

    public function respond(Request $request, MemberStateCommunication $communication)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:in_review,answered,closed'],
            'response_text' => ['required', 'string', 'max:12000'],
        ]);

        $communication->update([
            'status' => $validated['status'],
            'response_text' => $validated['response_text'],
            'responded_by' => $request->user()->id,
            'responded_at' => now(),
            'updated_by' => $request->user()->id,
        ]);

        $communication->refresh()->loadMissing(['memberState', 'responder', 'creator', 'attachments']);

        $this->logCommunicationAudit(
            $request,
            'communication_response_sent',
            'AU back-office responded to a member-state communication.',
            [
                'communication_id' => $communication->id,
                'member_state_id' => $communication->member_state_id,
                'status' => $communication->status,
            ],
            $communication
        );

        $recipientEmails = User::query()
            ->where('user_type', 'member_state')
            ->where('member_state_id', $communication->member_state_id)
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->unique()
            ->values();

        if ($recipientEmails->isNotEmpty()) {
            try {
                Mail::to($recipientEmails->all())->send(new MemberStateCommunicationResponseNotification($communication));

                $this->logCommunicationAudit(
                    $request,
                    'communication_response_email_sent',
                    'Response notification email sent to member-state users.',
                    [
                        'communication_id' => $communication->id,
                        'member_state_id' => $communication->member_state_id,
                        'recipient_count' => $recipientEmails->count(),
                    ],
                    $communication
                );
            } catch (\Throwable $exception) {
                Log::error('Failed to send member-state communication response email.', [
                    'communication_id' => $communication->id,
                    'member_state_id' => $communication->member_state_id,
                    'message' => $exception->getMessage(),
                ]);

                $this->logCommunicationAudit(
                    $request,
                    'communication_response_email_failed',
                    'Failed to send response notification email to member-state users.',
                    [
                        'communication_id' => $communication->id,
                        'member_state_id' => $communication->member_state_id,
                    ],
                    $communication
                );
            }
        } else {
            $this->logCommunicationAudit(
                $request,
                'communication_response_email_skipped',
                'No member-state email recipients were configured for this communication response.',
                [
                    'communication_id' => $communication->id,
                    'member_state_id' => $communication->member_state_id,
                ],
                $communication
            );
        }

        return back()->with('success', 'Response saved and member-state notification processed.');
    }

    public function downloadAttachment(
        Request $request,
        MemberStateCommunication $communication,
        MemberStateCommunicationAttachment $attachment
    ) {
        abort_unless($attachment->communication_id === $communication->id, 404);

        $path = (string) ($attachment->file_path ?? '');
        abort_if($path === '', 404, 'Attachment not found.');

        $privateDisk = Storage::disk('local');

        if (!$privateDisk->exists($path) && Storage::disk('public')->exists($path)) {
            $stream = Storage::disk('public')->readStream($path);
            if ($stream !== false) {
                $privateDisk->writeStream($path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                Storage::disk('public')->delete($path);
            }
        }

        abort_unless($privateDisk->exists($path), 404, 'Attachment file missing on disk.');

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        $this->logCommunicationAudit(
            $request,
            'communication_attachment_downloaded_admin',
            'Back-office user downloaded a member-state communication attachment.',
            [
                'communication_id' => $communication->id,
                'member_state_id' => $communication->member_state_id,
                'attachment_id' => $attachment->id,
                'file_name' => $attachment->file_name,
            ],
            $communication
        );

        if ($request->boolean('download', true)) {
            return $privateDisk->download($path, $attachment->file_name ?? basename($path), $headers);
        }

        return $privateDisk->response($path, null, $headers);
    }

    private function logCommunicationAudit(
        Request $request,
        string $action,
        string $actionMessage,
        array $payload = [],
        ?MemberStateCommunication $communication = null
    ): void {
        try {
            SystemAuditLog::create([
                'user_id' => optional($request->user())->id,
                'module' => 'communications',
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
                    'communication_id' => $communication?->id,
                    'member_state_id' => $communication?->member_state_id,
                ], $payload),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to persist back-office communication audit log entry.', [
                'action' => $action,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
