<?php

namespace App\Http\Controllers\AuMasterData;

use App\Http\Controllers\Controller;
use App\Mail\TreatyCodeVerificationUpdateMail;
use App\Models\AuMemberState;
use App\Models\Treaty;
use App\Models\TreatyMemberStateStatus;
use App\Models\TreatySupportingDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;

class TreatyController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:treaties.view')->only(['index', 'show']);
        $this->middleware('permission:treaties.create')->only(['create', 'store']);
        $this->middleware('permission:treaties.edit')->only(['edit', 'update', 'syncMemberStateStatuses']);
        $this->middleware('permission:treaties.delete')->only(['destroy']);
    }

    public function index()
    {
        $statusCounts = TreatyMemberStateStatus::query()
            ->select('treaty_id')
            ->selectRaw('SUM(CASE WHEN is_signed IS TRUE THEN 1 ELSE 0 END) as signed_count')
            ->selectRaw('SUM(CASE WHEN is_ratified IS TRUE THEN 1 ELSE 0 END) as ratified_count')
            ->groupBy('treaty_id');

        $treaties = Treaty::query()
            ->leftJoinSub($statusCounts, 'status_counts', function ($join) {
                $join->on('myb_treaties.id', '=', 'status_counts.treaty_id');
            })
            ->select('myb_treaties.*')
            ->selectRaw('COALESCE(status_counts.signed_count, 0) as signed_count')
            ->selectRaw('COALESCE(status_counts.ratified_count, 0) as ratified_count')
            ->orderByDesc('myb_treaties.adoption_date')
            ->orderByDesc('myb_treaties.created_at')
            ->get();

        return view('au-master-data.treaties.index', compact('treaties'));
    }

    public function create()
    {
        return view('au-master-data.treaties.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'short_title' => 'nullable|string|max:120',
            'reference_code' => 'nullable|string|max:80|unique:myb_treaties,reference_code',
            'description' => 'nullable|string',
            'overview' => 'nullable|string',
            'key_provisions' => 'nullable|string',
            'implementation_framework' => 'nullable|string',
            'monitoring_and_reporting' => 'nullable|string',
            'read_more_url' => 'nullable|url|max:2048',
            'adoption_date' => 'nullable|date',
            'entry_into_force_date' => 'nullable|date|after_or_equal:adoption_date',
            'status' => 'required|in:draft,active,archived',
            'supporting_document_titles' => 'nullable|array',
            'supporting_document_titles.*' => 'nullable|string|max:255',
            'supporting_document_types' => 'nullable|array',
            'supporting_document_types.*' => 'nullable|string|max:100',
            'supporting_documents' => 'nullable|array',
            'supporting_documents.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip|max:20480',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        $treaty = null;
        DB::transaction(function () use ($request, $validated, &$treaty) {
            $treaty = Treaty::create([
                'title' => $validated['title'],
                'short_title' => $validated['short_title'] ?? null,
                'reference_code' => $validated['reference_code'] ?? null,
                'description' => $validated['description'] ?? null,
                'overview' => $validated['overview'] ?? null,
                'key_provisions' => $validated['key_provisions'] ?? null,
                'implementation_framework' => $validated['implementation_framework'] ?? null,
                'monitoring_and_reporting' => $validated['monitoring_and_reporting'] ?? null,
                'read_more_url' => $validated['read_more_url'] ?? null,
                'adoption_date' => $validated['adoption_date'] ?? null,
                'entry_into_force_date' => $validated['entry_into_force_date'] ?? null,
                'status' => $validated['status'],
                'created_by' => $validated['created_by'],
                'updated_by' => $validated['updated_by'],
            ]);

            $this->storeSupportingDocuments($request, $treaty);
        });

        return redirect()
            ->route('settings.au.treaties.show', $treaty->id)
            ->with('success', 'Treaty created successfully. You can now update member-state sign/ratify status.');
    }

    public function show(Treaty $treaty)
    {
        $treaty->load([
            'memberStateStatuses.memberState',
            'memberStateStatuses.signedByUser',
            'memberStateStatuses.signedServiceCodeVerifiedByUser',
            'memberStateStatuses.ratifiedByUser',
            'memberStateStatuses.ratifiedServiceCodeVerifiedByUser',
            'memberStateStatuses.originalSubmittedByUser',
            'supportingDocuments',
        ]);

        $memberStates = AuMemberState::ordered()->get();
        $statusByState = $treaty->memberStateStatuses->keyBy('member_state_id');

        return view('au-master-data.treaties.show', compact('treaty', 'memberStates', 'statusByState'));
    }

    public function edit(Treaty $treaty)
    {
        $treaty->load('supportingDocuments');
        return view('au-master-data.treaties.edit', compact('treaty'));
    }

    public function update(Request $request, Treaty $treaty)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'short_title' => 'nullable|string|max:120',
            'reference_code' => 'nullable|string|max:80|unique:myb_treaties,reference_code,' . $treaty->id,
            'description' => 'nullable|string',
            'overview' => 'nullable|string',
            'key_provisions' => 'nullable|string',
            'implementation_framework' => 'nullable|string',
            'monitoring_and_reporting' => 'nullable|string',
            'read_more_url' => 'nullable|url|max:2048',
            'adoption_date' => 'nullable|date',
            'entry_into_force_date' => 'nullable|date|after_or_equal:adoption_date',
            'status' => 'required|in:draft,active,archived',
            'supporting_document_titles' => 'nullable|array',
            'supporting_document_titles.*' => 'nullable|string|max:255',
            'supporting_document_types' => 'nullable|array',
            'supporting_document_types.*' => 'nullable|string|max:100',
            'supporting_documents' => 'nullable|array',
            'supporting_documents.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip|max:20480',
            'remove_supporting_document_ids' => 'nullable|array',
            'remove_supporting_document_ids.*' => 'nullable|exists:myb_treaty_supporting_documents,id',
        ]);

        $validated['updated_by'] = auth()->id();

        DB::transaction(function () use ($request, $treaty, $validated) {
            $treaty->update([
                'title' => $validated['title'],
                'short_title' => $validated['short_title'] ?? null,
                'reference_code' => $validated['reference_code'] ?? null,
                'description' => $validated['description'] ?? null,
                'overview' => $validated['overview'] ?? null,
                'key_provisions' => $validated['key_provisions'] ?? null,
                'implementation_framework' => $validated['implementation_framework'] ?? null,
                'monitoring_and_reporting' => $validated['monitoring_and_reporting'] ?? null,
                'read_more_url' => $validated['read_more_url'] ?? null,
                'adoption_date' => $validated['adoption_date'] ?? null,
                'entry_into_force_date' => $validated['entry_into_force_date'] ?? null,
                'status' => $validated['status'],
                'updated_by' => $validated['updated_by'],
            ]);

            $this->removeSupportingDocuments($treaty, $request->input('remove_supporting_document_ids', []));
            $this->storeSupportingDocuments($request, $treaty);
        });

        return redirect()
            ->route('settings.au.treaties.show', $treaty->id)
            ->with('success', 'Treaty updated successfully, including supporting documents.');
    }

    public function destroy(Treaty $treaty)
    {
        $treaty->delete();

        return redirect()
            ->route('settings.au.treaties.index')
            ->with('success', 'Treaty deleted successfully.');
    }

    public function syncMemberStateStatuses(Request $request, Treaty $treaty)
    {
        $validated = $request->validate([
            'status' => 'nullable|array',
            'status.*' => 'nullable|in:none,signed,ratified,original_submitted',
            'proof_signed_code' => 'nullable|array',
            'proof_signed_code.*' => [
                'nullable',
                'string',
                'regex:/^[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}$/',
            ],
            'proof_ratified_code' => 'nullable|array',
            'proof_ratified_code.*' => [
                'nullable',
                'string',
                'regex:/^[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}$/',
            ],
        ]);

        $statusInput = $validated['status'] ?? [];
        $signedProofCodeInput = $validated['proof_signed_code'] ?? [];
        $ratifiedProofCodeInput = $validated['proof_ratified_code'] ?? [];
        $memberStateIds = AuMemberState::pluck('id')->all();
        $memberStateNames = AuMemberState::whereIn('id', $memberStateIds)->pluck('name', 'id');
        $existingRecords = $treaty->memberStateStatuses()->get()->keyBy('member_state_id');
        $currentUser = auth()->user();
        $currentUserId = $currentUser?->id;
        $verificationBlocked = [];
        $notificationFailures = [];

        foreach ($memberStateIds as $memberStateId) {
            $requestedStatus = $statusInput[$memberStateId] ?? 'none';
            $record = $existingRecords->get($memberStateId);
            $enteredSignedCode = $this->normalizeServiceCode($signedProofCodeInput[$memberStateId] ?? null);
            $enteredRatifiedCode = $this->normalizeServiceCode($ratifiedProofCodeInput[$memberStateId] ?? null);
            $stateName = (string) ($memberStateNames[$memberStateId] ?? 'Member State');

            if ($requestedStatus === 'none') {
                if ($record) {
                    $record->delete();
                }
                continue;
            }

            if (!$record) {
                $record = new TreatyMemberStateStatus([
                    'treaty_id' => $treaty->id,
                    'member_state_id' => $memberStateId,
                ]);
            }

            $record->updated_by = $currentUserId;
            $wasSignedVerified = !empty($record->signed_service_code_verified_at);
            $wasRatifiedVerified = !empty($record->ratified_service_code_verified_at);

            if ($requestedStatus === 'signed') {
                if (!$record->is_signed) {
                    $record->signed_at = now();
                    $record->signed_by_user_id = $currentUserId;
                }

                $record->is_signed = true;
                $record->signed_service_code = $record->signed_service_code
                    ?: TreatyMemberStateStatus::generateUniqueServiceCode('signed_service_code');
                $record->is_ratified = false;
                $record->ratified_at = null;
                $record->ratified_by_user_id = null;
                $record->ratified_service_code = null;
                $record->ratified_service_code_verified_at = null;
                $record->ratified_service_code_verified_by_user_id = null;
                $record->is_original_submitted = false;
                $record->original_submitted_at = null;
                $record->original_submitted_by_user_id = null;
                $record->original_document_path = null;
                $record->original_document_name = null;
                $record->original_notes = null;
                $record->ratified_document_path = null;
                $record->ratified_document_name = null;
                $record->ratified_notes = null;
            } elseif ($requestedStatus === 'ratified') {
                if (!$record->is_signed) {
                    $record->signed_at = now();
                    $record->signed_by_user_id = $currentUserId;
                }
                if (!$record->is_ratified) {
                    $record->ratified_at = now();
                    $record->ratified_by_user_id = $currentUserId;
                }

                $record->is_signed = true;
                $record->signed_service_code = $record->signed_service_code
                    ?: TreatyMemberStateStatus::generateUniqueServiceCode('signed_service_code');
                $record->is_ratified = true;
                $record->ratified_service_code = $record->ratified_service_code
                    ?: TreatyMemberStateStatus::generateUniqueServiceCode('ratified_service_code');
                $record->is_original_submitted = false;
                $record->original_submitted_at = null;
                $record->original_submitted_by_user_id = null;
                $record->original_document_path = null;
                $record->original_document_name = null;
                $record->original_notes = null;
            } else {
                if (!$record->is_signed) {
                    $record->signed_at = now();
                    $record->signed_by_user_id = $currentUserId;
                }
                if (!$record->is_ratified) {
                    $record->ratified_at = now();
                    $record->ratified_by_user_id = $currentUserId;
                }
                if (!$record->original_submitted_at) {
                    $record->original_submitted_at = now();
                    $record->original_submitted_by_user_id = $currentUserId;
                }

                $record->is_signed = true;
                $record->signed_service_code = $record->signed_service_code
                    ?: TreatyMemberStateStatus::generateUniqueServiceCode('signed_service_code');
                $record->is_ratified = true;
                $record->ratified_service_code = $record->ratified_service_code
                    ?: TreatyMemberStateStatus::generateUniqueServiceCode('ratified_service_code');
            }

            $storedSignedCode = $this->normalizeServiceCode($record->signed_service_code ?? null);
            $storedRatifiedCode = $this->normalizeServiceCode($record->ratified_service_code ?? null);

            if ($record->is_signed && $enteredSignedCode !== null) {
                if ($storedSignedCode !== null && $enteredSignedCode === $storedSignedCode) {
                    $record->signed_service_code_verified_at = now();
                    $record->signed_service_code_verified_by_user_id = $currentUserId;
                } else {
                    $record->signed_service_code_verified_at = null;
                    $record->signed_service_code_verified_by_user_id = null;
                    $verificationBlocked[] = "{$stateName}: signed proof-of-service code mismatch.";
                }
            }

            if ($record->is_ratified && $enteredRatifiedCode !== null) {
                if ($storedRatifiedCode !== null && $enteredRatifiedCode === $storedRatifiedCode) {
                    $record->ratified_service_code_verified_at = now();
                    $record->ratified_service_code_verified_by_user_id = $currentUserId;
                } else {
                    $record->ratified_service_code_verified_at = null;
                    $record->ratified_service_code_verified_by_user_id = null;
                    $verificationBlocked[] = "{$stateName}: ratified proof-of-service code mismatch.";
                }
            }

            if ($requestedStatus === 'original_submitted') {
                if (empty($record->original_document_path)) {
                    $record->is_original_submitted = false;
                    $verificationBlocked[] = "{$stateName}: original submission document is missing.";
                } elseif ($record->hasVerifiedProofOfService()) {
                    $record->is_original_submitted = true;
                } else {
                    $record->is_original_submitted = false;
                    $verificationBlocked[] = "{$stateName}: signed/ratified code verification is still pending.";
                }
            }

            $record->save();

            if (!$wasSignedVerified && !empty($record->signed_service_code_verified_at)) {
                $emailSent = $this->sendCodeVerificationNotification(
                    $treaty,
                    $record,
                    'signed',
                    $stateName,
                    $currentUser
                );

                if (!$emailSent) {
                    $notificationFailures[] = "{$stateName} (signed code verification)";
                }
            }

            if (!$wasRatifiedVerified && !empty($record->ratified_service_code_verified_at)) {
                $emailSent = $this->sendCodeVerificationNotification(
                    $treaty,
                    $record,
                    'ratified',
                    $stateName,
                    $currentUser
                );

                if (!$emailSent) {
                    $notificationFailures[] = "{$stateName} (ratified code verification)";
                }
            }
        }

        $response = back()->with('success', 'Member-state treaty statuses updated successfully.');
        $warningMessages = [];
        if (!empty($verificationBlocked)) {
            $warningMessages[] = 'Not completed for: ' . implode(' | ', $verificationBlocked);
        }
        if (!empty($notificationFailures)) {
            $warningMessages[] = 'Verification email not sent for: ' . implode(' | ', $notificationFailures);
        }
        if (!empty($warningMessages)) {
            $response->with('warning', implode(' ', $warningMessages));
        }

        return $response;
    }

    private function sendCodeVerificationNotification(
        Treaty $treaty,
        TreatyMemberStateStatus $status,
        string $codeType,
        string $memberStateName,
        ?User $actor = null
    ): bool {
        $recipientEmails = $this->resolveCodeVerificationRecipientEmails((string) $status->member_state_id);
        if ($recipientEmails->isEmpty()) {
            return false;
        }

        try {
            $status->loadMissing('memberState');
            Mail::to($recipientEmails->all())
                ->send(new TreatyCodeVerificationUpdateMail($treaty, $status, $codeType, $memberStateName, $actor));
            return true;
        } catch (\Throwable $exception) {
            Log::error('Failed to send treaty code verification notification email.', [
                'treaty_id' => $treaty->id,
                'member_state_id' => $status->member_state_id,
                'code_type' => $codeType,
                'error' => $exception->getMessage(),
            ]);
            return false;
        }
    }

    private function resolveCodeVerificationRecipientEmails(string $memberStateId): Collection
    {
        $memberStateEmails = User::query()
            ->where('user_type', 'member_state')
            ->where('member_state_id', $memberStateId)
            ->whereNotNull('email')
            ->pluck('email');

        $systemRecipientEmails = User::query()
            ->whereNotNull('email')
            ->where(function ($query) {
                $query->whereNull('user_type')
                    ->orWhere('user_type', '!=', 'member_state');
            })
            ->where(function ($query) {
                $query->whereHas('permissions', function ($permissionQuery) {
                    $permissionQuery->where('name', 'treaties.edit');
                })->orWhereHas('role.permissions', function ($permissionQuery) {
                    $permissionQuery->where('name', 'treaties.edit');
                })->orWhereHas('role', function ($roleQuery) {
                    $roleQuery->where('name', 'System Admin');
                });
            })
            ->pluck('email');

        return $memberStateEmails
            ->merge($systemRecipientEmails)
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();
    }

    private function storeSupportingDocuments(Request $request, Treaty $treaty): void
    {
        $files = $request->file('supporting_documents', []);
        $titles = $request->input('supporting_document_titles', []);
        $types = $request->input('supporting_document_types', []);

        if (!is_array($files) || empty($files)) {
            return;
        }

        foreach ($files as $index => $file) {
            if (!$file) {
                continue;
            }

            TreatySupportingDocument::create([
                'treaty_id' => $treaty->id,
                'title' => $titles[$index] ?? null,
                'document_type' => $types[$index] ?? null,
                'file_path' => $file->store("treaties/{$treaty->id}/supporting-documents"),
                'file_name' => $file->getClientOriginalName(),
                'uploaded_by' => auth()->id(),
            ]);
        }
    }

    private function removeSupportingDocuments(Treaty $treaty, array $removeIds): void
    {
        $filteredIds = collect($removeIds)->filter()->values();
        if ($filteredIds->isEmpty()) {
            return;
        }

        $documents = $treaty->supportingDocuments()
            ->whereIn('id', $filteredIds->all())
            ->get();

        foreach ($documents as $document) {
            if ($document->file_path) {
                Storage::disk('local')->delete($document->file_path);
            }
            $document->delete();
        }
    }

    private function normalizeServiceCode(?string $code): ?string
    {
        $normalized = strtoupper(trim((string) $code));
        return $normalized === '' ? null : $normalized;
    }
}
