<?php

namespace App\Http\Controllers\Treaty;

use App\Http\Controllers\Controller;
use App\Mail\TreatyProofServiceCodesMail;
use App\Models\Treaty;
use App\Models\TreatyMemberStateStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MemberStateTreatyController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:member_state.treaties.view')->only(['index']);
        $this->middleware('permission:member_state.treaties.update')->only(['updateStatus', 'resendProofServiceEmail']);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Treaty::query()
            ->whereIn('status', ['active', 'draft'])
            ->with(['memberStateStatuses' => function ($query) use ($user) {
                $query->where('member_state_id', $user->member_state_id);
            }, 'supportingDocuments'])
            ->orderByDesc('adoption_date')
            ->orderBy('title');

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search, $user) {
                $builder->where('title', 'like', '%' . $search . '%')
                    ->orWhere('short_title', 'like', '%' . $search . '%')
                    ->orWhere('reference_code', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('overview', 'like', '%' . $search . '%')
                    ->orWhere('key_provisions', 'like', '%' . $search . '%')
                    ->orWhere('implementation_framework', 'like', '%' . $search . '%')
                    ->orWhere('monitoring_and_reporting', 'like', '%' . $search . '%')
                    ->orWhereHas('memberStateStatuses', function ($statusQuery) use ($user, $search) {
                        $statusQuery->where('member_state_id', $user->member_state_id)
                            ->where(function ($codesQuery) use ($search) {
                                $codesQuery->where('signed_service_code', 'like', '%' . $search . '%')
                                    ->orWhere('ratified_service_code', 'like', '%' . $search . '%')
                                    ->orWhere('signed_document_name', 'like', '%' . $search . '%')
                                    ->orWhere('ratified_document_name', 'like', '%' . $search . '%')
                                    ->orWhere('original_document_name', 'like', '%' . $search . '%');
                            });
                    });
            });
        }

        if ($request->filled('year')) {
            $query->whereYear('adoption_date', (int) $request->input('year'));
        }

        $treaties = $query->get();

        $statusByTreatyId = $treaties
            ->mapWithKeys(function ($treaty) {
                return [$treaty->id => $treaty->memberStateStatuses->first()];
            });

        $stageFilter = (string) $request->input('stage', '');
        if ($stageFilter !== '') {
            $treaties = $treaties->filter(function ($treaty) use ($statusByTreatyId, $stageFilter) {
                $status = $statusByTreatyId->get($treaty->id);
                return $this->matchesStageFilter($status, $stageFilter);
            })->values();
        }

        $codeVerificationFilter = (string) $request->input('code_verification', '');
        if ($codeVerificationFilter !== '') {
            $treaties = $treaties->filter(function ($treaty) use ($statusByTreatyId, $codeVerificationFilter) {
                $status = $statusByTreatyId->get($treaty->id);
                return $this->matchesCodeVerificationFilter($status, $codeVerificationFilter);
            })->values();
        }

        $statusRows = $treaties->map(function ($treaty) {
            return $treaty->memberStateStatuses->first();
        })->filter();

        $stageByTreatyId = $treaties
            ->mapWithKeys(function ($treaty) use ($statusByTreatyId) {
                $status = $statusByTreatyId->get($treaty->id);
                return [$treaty->id => $this->resolveTreatyStage($status)];
            });

        $availableYears = Treaty::query()
            ->whereIn('status', ['active', 'draft'])
            ->whereNotNull('adoption_date')
            ->selectRaw('EXTRACT(YEAR FROM adoption_date) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');

        $summary = [
            'total_treaties' => $treaties->count(),
            'signed_count' => $statusRows->where('is_signed', true)->count(),
            'ratified_count' => $statusRows->where('is_ratified', true)->count(),
            'original_submitted_count' => $statusRows->where('is_original_submitted', true)->count(),
            'pending_sign_count' => $treaties->count() - $statusRows->where('is_signed', true)->count(),
            'pending_ratification_count' => $statusRows->where('is_signed', true)->where('is_ratified', false)->count(),
            'pending_original_submission_count' => $statusRows->where('is_ratified', true)->where('is_original_submitted', false)->count(),
            'fully_completed_count' => $statusRows->filter(function ($status) {
                return $status->is_original_submitted
                    && $status->signed_service_code_verified_at
                    && $status->ratified_service_code_verified_at;
            })->count(),
            'pending_legal_verification_count' => $statusRows->filter(function ($status) {
                if (!$status->signed_service_code && !$status->ratified_service_code) {
                    return false;
                }

                return !$status->signed_service_code_verified_at || !$status->ratified_service_code_verified_at;
            })->count(),
        ];

        return view('member-state.treaties.index', [
            'memberState' => $user->memberState,
            'treaties' => $treaties,
            'summary' => $summary,
            'statusByTreatyId' => $statusByTreatyId,
            'stageByTreatyId' => $stageByTreatyId,
            'availableYears' => $availableYears,
            'filters' => [
                'q' => $search,
                'year' => $request->input('year', ''),
                'stage' => $stageFilter,
                'code_verification' => $codeVerificationFilter,
            ],
        ]);
    }

    public function updateStatus(Request $request, Treaty $treaty)
    {
        $user = $request->user();

        $actionType = (string) $request->input('action_type');
        $rules = [
            'action_type' => 'required|in:sign,ratify,submit_original',
            'notes' => 'nullable|string|max:2000',
            'action_date' => 'nullable|date',
        ];

        if ($actionType === 'submit_original') {
            $rules['original_document'] = 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:20480';
        } else {
            $rules['proof_document'] = 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240';
        }

        $validated = $request->validate($rules);

        $status = TreatyMemberStateStatus::firstOrNew([
            'treaty_id' => $treaty->id,
            'member_state_id' => $user->member_state_id,
        ]);

        if ($validated['action_type'] === 'ratify' && !$status->is_signed) {
            return back()->withErrors([
            'action_type' => 'This treaty must be signed first before it can be ratified.',
            ]);
        }

        if ($validated['action_type'] === 'submit_original' && !$status->is_ratified) {
            return back()->withErrors([
                'action_type' => 'The treaty must be ratified before submitting original signed and ratified copies.',
            ]);
        }

        $dateTime = isset($validated['action_date'])
            ? \Carbon\Carbon::parse($validated['action_date'])->endOfDay()
            : now();

        if ($validated['action_type'] === 'sign') {
            $status->is_signed = true;
            $status->is_original_submitted = false;
            $status->signed_at = $dateTime;
            $status->signed_by_user_id = $user->id;
            $status->signed_notes = $validated['notes'] ?? null;
            $status->signed_service_code = $status->signed_service_code
                ?: TreatyMemberStateStatus::generateUniqueServiceCode('signed_service_code');
            $status->signed_service_code_verified_at = null;
            $status->signed_service_code_verified_by_user_id = null;

            $path = $request->file('proof_document')->store(
                "treaties/{$treaty->id}/{$user->member_state_id}/signed"
            );
            $status->signed_document_path = $path;
            $status->signed_document_name = $request->file('proof_document')->getClientOriginalName();
        }

        if ($validated['action_type'] === 'ratify') {
            $status->is_ratified = true;
            $status->is_original_submitted = false;
            $status->ratified_at = $dateTime;
            $status->ratified_by_user_id = $user->id;
            $status->ratified_notes = $validated['notes'] ?? null;
            $status->ratified_service_code = $status->ratified_service_code
                ?: TreatyMemberStateStatus::generateUniqueServiceCode('ratified_service_code');
            $status->ratified_service_code_verified_at = null;
            $status->ratified_service_code_verified_by_user_id = null;

            if (!$status->is_signed) {
                $status->is_signed = true;
                $status->signed_at = $dateTime;
                $status->signed_by_user_id = $user->id;
                $status->signed_service_code = $status->signed_service_code
                    ?: TreatyMemberStateStatus::generateUniqueServiceCode('signed_service_code');
            }

            $path = $request->file('proof_document')->store(
                "treaties/{$treaty->id}/{$user->member_state_id}/ratified"
            );
            $status->ratified_document_path = $path;
            $status->ratified_document_name = $request->file('proof_document')->getClientOriginalName();
        }

        if ($validated['action_type'] === 'submit_original') {
            $status->signed_service_code = $status->signed_service_code
                ?: TreatyMemberStateStatus::generateUniqueServiceCode('signed_service_code');
            $status->ratified_service_code = $status->ratified_service_code
                ?: TreatyMemberStateStatus::generateUniqueServiceCode('ratified_service_code');
            $status->signed_service_code_verified_at = null;
            $status->signed_service_code_verified_by_user_id = null;
            $status->ratified_service_code_verified_at = null;
            $status->ratified_service_code_verified_by_user_id = null;
            $status->is_original_submitted = false;
            $status->original_submitted_at = $dateTime;
            $status->original_submitted_by_user_id = $user->id;
            $status->original_notes = $validated['notes'] ?? null;

            $path = $request->file('original_document')->store(
                "treaties/{$treaty->id}/{$user->member_state_id}/original-submission"
            );
            $status->original_document_path = $path;
            $status->original_document_name = $request->file('original_document')->getClientOriginalName();
        }

        $status->updated_by = $user->id;
        $status->save();

        $emailSent = true;
        if (in_array($validated['action_type'], ['sign', 'ratify'], true)) {
            $emailSent = $this->sendProofServiceCodesToLegal($treaty, $status, $user, false);
        }

        $message = match ($validated['action_type']) {
            'sign' => 'Treaty signing details saved. Signed proof-of-service code generated.',
            'ratify' => 'Treaty ratification details saved. Ratified proof-of-service code generated.',
            'submit_original' => 'Original copy submitted. AU Legal Directorate must verify signed and ratified service codes before final completion.',
            default => 'Treaty status updated successfully.',
        };

        $response = back()->with('success', $message);
        if (in_array($validated['action_type'], ['sign', 'ratify'], true) && !$emailSent) {
            $response->with('warning', 'Treaty status saved, but proof-of-service email could not be delivered. You can use Resend Email once mail/legal recipients are ready.');
        }

        return $response;
    }

    public function resendProofServiceEmail(Request $request, Treaty $treaty)
    {
        $user = $request->user();

        $status = TreatyMemberStateStatus::query()
            ->where('treaty_id', $treaty->id)
            ->where('member_state_id', $user->member_state_id)
            ->first();

        if (!$status || (!$status->signed_service_code && !$status->ratified_service_code)) {
            return back()->with('error', 'No proof-of-service code is available yet for this treaty.');
        }

        $isSignedPending = $status->signed_service_code && !$status->signed_service_code_verified_at;
        $isRatifiedPending = $status->ratified_service_code && !$status->ratified_service_code_verified_at;

        if (!$isSignedPending && !$isRatifiedPending) {
            return back()->with('info', 'AU Legal has already confirmed all proof-of-service codes for this treaty.');
        }

        $sent = $this->sendProofServiceCodesToLegal($treaty, $status, $user, true);

        if (!$sent) {
            return back()->with('warning', 'Proof-of-service email could not be resent now. Please check mail configuration or legal recipient setup.');
        }

        return back()->with('success', 'Proof-of-service email has been resent to AU Legal Directorate.');
    }

    private function sendProofServiceCodesToLegal(Treaty $treaty, TreatyMemberStateStatus $status, User $actor, bool $isResend): bool
    {
        $recipientEmails = $this->resolveLegalRecipientEmails();
        if ($recipientEmails->isEmpty()) {
            return false;
        }

        try {
            Mail::to($recipientEmails->all())
                ->send(new TreatyProofServiceCodesMail($treaty, $status, $actor, $isResend));
            return true;
        } catch (\Throwable $exception) {
            Log::error('Failed to send treaty proof-of-service email.', [
                'treaty_id' => $treaty->id,
                'member_state_id' => $status->member_state_id,
                'is_resend' => $isResend,
                'error' => $exception->getMessage(),
            ]);
            return false;
        }
    }

    private function resolveLegalRecipientEmails(): Collection
    {
        return User::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where('user_type', '!=', 'member_state')
            ->where(function ($query) {
                $query->whereHas('permissions', function ($permissionQuery) {
                    $permissionQuery->where('name', 'treaties.edit');
                })->orWhereHas('role.permissions', function ($permissionQuery) {
                    $permissionQuery->where('name', 'treaties.edit');
                });
            })
            ->pluck('email')
            ->map(function ($email) {
                return strtolower(trim((string) $email));
            })
            ->filter()
            ->unique()
            ->values();
    }

    private function resolveTreatyStage(?TreatyMemberStateStatus $status): string
    {
        if (!$status || !$status->is_signed) {
            return 'Not Started';
        }

        if ($status->is_signed && !$status->is_ratified) {
            return 'Signed';
        }

        if ($status->is_ratified && !$status->is_original_submitted) {
            return 'Ratified';
        }

        if (
            $status->is_original_submitted
            && $status->signed_service_code_verified_at
            && $status->ratified_service_code_verified_at
        ) {
            return 'Completed';
        }

        return 'Original Submitted';
    }

    private function matchesStageFilter(?TreatyMemberStateStatus $status, string $stageFilter): bool
    {
        $stageFilter = Str::lower($stageFilter);

        return match ($stageFilter) {
            'not_started' => !$status || !$status->is_signed,
            'signed' => (bool) ($status?->is_signed) && !(bool) ($status?->is_ratified),
            'ratified' => (bool) ($status?->is_ratified) && !(bool) ($status?->is_original_submitted),
            'original_submitted' => (bool) ($status?->is_original_submitted),
            'completed' => (bool) ($status?->is_original_submitted)
                && !empty($status?->signed_service_code_verified_at)
                && !empty($status?->ratified_service_code_verified_at),
            default => true,
        };
    }

    private function matchesCodeVerificationFilter(?TreatyMemberStateStatus $status, string $filter): bool
    {
        $filter = Str::lower($filter);

        $hasSignedCode = !empty($status?->signed_service_code);
        $hasRatifiedCode = !empty($status?->ratified_service_code);
        $signedVerified = !empty($status?->signed_service_code_verified_at);
        $ratifiedVerified = !empty($status?->ratified_service_code_verified_at);

        return match ($filter) {
            'pending' => ($hasSignedCode || $hasRatifiedCode) && (!$signedVerified || !$ratifiedVerified),
            'verified' => ($hasSignedCode || $hasRatifiedCode) && $signedVerified && $ratifiedVerified,
            'not_generated' => !$hasSignedCode && !$hasRatifiedCode,
            default => true,
        };
    }
}
