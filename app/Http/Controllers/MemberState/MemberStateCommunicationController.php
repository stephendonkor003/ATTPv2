<?php

namespace App\Http\Controllers\MemberState;

use App\Http\Controllers\Controller;
use App\Mail\MemberStateCommunicationSubmittedNotification;
use App\Models\MemberStateCommunication;
use App\Models\MemberStateCommunicationAttachment;
use App\Models\SystemAuditLog;
use App\Models\User;
use App\Support\IpGeo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class MemberStateCommunicationController extends Controller
{
    public function index(Request $request)
    {
        $memberStateId = $request->user()->member_state_id;

        $baseQuery = MemberStateCommunication::query()
            ->where('member_state_id', $memberStateId);

        $query = (clone $baseQuery);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('channel')) {
            $query->where('channel', $request->input('channel'));
        }
        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($inner) use ($term) {
                $inner->where('subject', 'like', '%' . $term . '%')
                    ->orWhere('message', 'like', '%' . $term . '%')
                    ->orWhere('response_text', 'like', '%' . $term . '%');
            });
        }

        $communications = $query
            ->with('attachments')
            ->orderByDesc('communication_date')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'pending_response' => (clone $baseQuery)->where('status', 'pending_response')->count(),
            'in_review' => (clone $baseQuery)->where('status', 'in_review')->count(),
            'answered' => (clone $baseQuery)->where('status', 'answered')->count(),
            'with_attachments' => (clone $baseQuery)->whereHas('attachments')->count(),
        ];

        return view('member-state.communications.index', [
            'memberState' => $request->user()->memberState,
            'communications' => $communications,
            'stats' => $stats,
            'filters' => [
                'status' => (string) $request->input('status', ''),
                'channel' => (string) $request->input('channel', ''),
                'q' => (string) $request->input('q', ''),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'communication_date' => ['required', 'date'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:12000'],
            'channel' => ['required', 'in:official_note,email,meeting,report,other'],
            'attachments' => ['nullable', 'array', 'max:25'],
            'attachments.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg,txt,zip'],
        ]);

        $communicationData = $validated;
        unset($communicationData['attachments']);

        $communication = MemberStateCommunication::create(array_merge($communicationData, [
            'member_state_id' => $request->user()->member_state_id,
            'status' => 'pending_response',
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));

        $files = $request->file('attachments', []);
        $uploadedAttachmentCount = 0;
        foreach ($files as $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }

            $storedPath = $file->store('member-state/communications/' . $communication->id, 'local');
            $communication->attachments()->create([
                'file_path' => $storedPath,
                'file_name' => (string) $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size_bytes' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
            $uploadedAttachmentCount++;
        }

        $this->notifyResponsibleOfficers($communication, $request);
        $this->logCommunicationAudit(
            $request,
            'member_state_communication_submitted',
            'Member state submitted a communication to AU.',
            [
                'communication_id' => $communication->id,
                'member_state_id' => $communication->member_state_id,
                'status' => $communication->status,
                'channel' => $communication->channel,
                'attachments_count' => $uploadedAttachmentCount,
            ],
            $communication
        );

        return back()->with('success', 'Communication submitted to the African Union successfully.');
    }

    public function destroy(Request $request, MemberStateCommunication $communication)
    {
        abort_unless($communication->member_state_id === $request->user()->member_state_id, 403);

        if ($communication->status === 'answered') {
            return back()->with('error', 'Answered communication records cannot be deleted.');
        }

        $communication->loadMissing('attachments');
        foreach ($communication->attachments as $attachment) {
            $path = (string) ($attachment->file_path ?? '');
            if ($path === '') {
                continue;
            }

            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            } elseif (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        $payload = [
            'communication_id' => $communication->id,
            'member_state_id' => $communication->member_state_id,
            'status' => $communication->status,
            'attachments_count' => $communication->attachments->count(),
        ];

        $communication->delete();

        $this->logCommunicationAudit(
            $request,
            'member_state_communication_deleted',
            'Member state deleted a communication record.',
            $payload
        );

        return back()->with('success', 'Communication deleted.');
    }

    public function downloadAttachment(
        Request $request,
        MemberStateCommunication $communication,
        MemberStateCommunicationAttachment $attachment
    ) {
        abort_unless($communication->member_state_id === $request->user()->member_state_id, 403);
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

        if ($request->boolean('download', true)) {
            $this->logCommunicationAudit(
                $request,
                'member_state_communication_attachment_downloaded',
                'Member state downloaded a communication attachment.',
                [
                    'communication_id' => $communication->id,
                    'member_state_id' => $communication->member_state_id,
                    'attachment_id' => $attachment->id,
                    'file_name' => $attachment->file_name,
                ],
                $communication
            );
            return $privateDisk->download($path, $attachment->file_name ?? basename($path), $headers);
        }

        $this->logCommunicationAudit(
            $request,
            'member_state_communication_attachment_previewed',
            'Member state previewed a communication attachment.',
            [
                'communication_id' => $communication->id,
                'member_state_id' => $communication->member_state_id,
                'attachment_id' => $attachment->id,
                'file_name' => $attachment->file_name,
            ],
            $communication
        );

        return $privateDisk->response($path, null, $headers);
    }

    private function notifyResponsibleOfficers(MemberStateCommunication $communication, Request $request): void
    {
        $communication->loadMissing(['memberState', 'creator']);

        $recipientEmails = User::query()
            ->whereNotNull('email')
            ->where(function ($query) {
                $query->whereHas('permissions', function ($permissionQuery) {
                    $permissionQuery->where('name', 'communications.respond');
                })->orWhereHas('role.permissions', function ($permissionQuery) {
                    $permissionQuery->where('name', 'communications.respond');
                });
            })
            ->pluck('email')
            ->filter()
            ->unique()
            ->values();

        if ($recipientEmails->isEmpty()) {
            $recipientEmails = User::query()
                ->whereNotNull('email')
                ->whereHas('role', function ($query) {
                    $query->where('name', 'System Admin');
                })
                ->pluck('email')
                ->filter()
                ->unique()
                ->values();
        }

        if ($recipientEmails->isEmpty()) {
            $this->logCommunicationAudit(
                $request,
                'communication_officer_email_alert_skipped',
                'No recipient was configured for communications officer alerts.',
                [
                    'communication_id' => $communication->id,
                    'member_state_id' => $communication->member_state_id,
                ],
                $communication
            );
            return;
        }

        try {
            Mail::to($recipientEmails->all())->send(new MemberStateCommunicationSubmittedNotification($communication));

            $this->logCommunicationAudit(
                $request,
                'communication_officer_email_alert_sent',
                'Responsible officers were notified about a new member-state communication.',
                [
                    'communication_id' => $communication->id,
                    'member_state_id' => $communication->member_state_id,
                    'recipient_count' => $recipientEmails->count(),
                ],
                $communication
            );
        } catch (\Throwable $exception) {
            Log::error('Failed to send communications officer alert email.', [
                'communication_id' => $communication->id,
                'member_state_id' => $communication->member_state_id,
                'message' => $exception->getMessage(),
            ]);

            $this->logCommunicationAudit(
                $request,
                'communication_officer_email_alert_failed',
                'Failed to notify responsible officers about a new communication.',
                [
                    'communication_id' => $communication->id,
                    'member_state_id' => $communication->member_state_id,
                ],
                $communication
            );
        }
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
            Log::warning('Failed to persist communication audit log entry.', [
                'action' => $action,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
