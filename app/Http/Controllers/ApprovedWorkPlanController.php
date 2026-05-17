<?php

namespace App\Http\Controllers;

use App\Models\ApprovedWorkPlan;
use App\Models\ApprovedWorkPlanItemReview;
use App\Models\BudgetCommitment;
use App\Models\Funder;
use App\Models\Program;
use App\Models\ProgramFunding;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Resource;
use App\Models\ResourceCategory;
use App\Models\SubActivity;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApprovedWorkPlanController extends Controller
{
    public function index(Request $request)
    {
        $programs = Program::orderBy('name')->get();
        $program = null;
        $funders = collect();
        $report = null;
        $summary = null;
        $filters = $this->resolveFilters($request);
        $selectedProgramId = $request->input('program_id');

        if ($selectedProgramId) {
            $program = Program::with([
                'projects.activities.subActivities.allocations',
                'approvedFundings.funder',
                'fundings.funder',
            ])->findOrFail($selectedProgramId);

            $fundings = $program->approvedFundings->isNotEmpty()
                ? $program->approvedFundings
                : $program->fundings;

            $funders = $fundings->pluck('funder')->filter()->unique('id')->values();
            $report = $this->buildWorkPlanHierarchy($program, $fundings->pluck('id')->all(), $filters);
            $summary = $this->summarizeWorkPlan($report);
        }

        return view('finance.awp.index', [
            'programs' => $programs,
            'program' => $program,
            'funders' => $funders,
            'report' => $report,
            'summary' => $summary,
            'filters' => $filters,
            'query' => $request->query(),
            'selectedProgramId' => $selectedProgramId,
        ]);
    }

    public function partnerIndex(Request $request)
    {
        $funder = $this->partnerFunder($request);
        $fundings = ProgramFunding::with('program')
            ->where('funder_id', $funder->id)
            ->where('status', 'approved')
            ->get();

        $programs = $fundings
            ->map(function (ProgramFunding $funding) {
                return $funding->program
                    ?: ($funding->program_name ? Program::where('name', $funding->program_name)->first() : null);
            })
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        $program = null;
        $report = null;
        $summary = null;
        $filters = $this->resolveFilters($request);
        $selectedProgramId = $request->input('program_id') ?: $programs->first()?->id;

        if ($selectedProgramId) {
            $program = Program::with(['projects.activities.subActivities.allocations'])->findOrFail($selectedProgramId);

            $programFundingIds = $fundings
                ->filter(function (ProgramFunding $funding) use ($program) {
                    return (string) $funding->program_id === (string) $program->id
                        || ($funding->program_name && $funding->program_name === $program->name);
                })
                ->pluck('id')
                ->all();

            abort_if(empty($programFundingIds), 403, 'This work plan is not funded by your partner account.');

            $report = $this->buildWorkPlanHierarchy($program, $programFundingIds, $filters);
            $summary = $this->summarizeWorkPlan($report);
        }

        return view('finance.awp.index', [
            'programs' => $programs,
            'program' => $program,
            'funders' => collect([$funder]),
            'report' => $report,
            'summary' => $summary,
            'filters' => $filters,
            'query' => $request->query(),
            'selectedProgramId' => $selectedProgramId,
            'awpLayout' => 'layouts.partner',
            'awpTitle' => 'Partner Work Plan - No Objection',
            'awpSubtitle' => 'Review work plan items funded by your organization and record no-objection decisions.',
            'awpIndexRoute' => 'partner.workplan.index',
            'awpReviewRoute' => 'partner.workplan.items.review',
            'awpDocumentRoute' => 'partner.workplan.items.document',
            'awpCanReview' => true,
            'awpCanEdit' => false,
            'awpAllowDocumentUpload' => false,
            'awpReadOnly' => false,
        ]);
    }

    public function reviewItem(Request $request, PurchaseRequestItem $item)
    {
        abort_unless($this->canReviewAwpItems($request), 403);

        $data = $request->validate([
            'status' => 'required|in:pending,approved,rejected,needs_revision',
            'review_notes' => 'nullable|string|max:2000',
            'document_type' => 'nullable|required_with:document_file|in:tor,terms_of_reference,concept_note',
            'document_file' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:20480',
            'tor_file' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:20480',
            'concept_note_file' => 'nullable|file|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png|max:20480',
        ]);

        $item->load('purchaseRequest.programFunding.funder');
        $purchaseRequest = $item->purchaseRequest;
        $existingReview = $item->awpReview;

        $documentPayload = [];
        if ($request->hasFile('document_file')) {
            if ($existingReview?->document_path) {
                Storage::disk('local')->delete($existingReview->document_path);
            }

            $file = $request->file('document_file');
            $documentPayload = [
                'document_type' => $data['document_type'],
                'document_path' => $file->store("approved-work-plan-items/{$item->id}", 'local'),
                'document_name' => $file->getClientOriginalName(),
                'document_uploaded_by' => $request->user()?->id,
                'document_uploaded_at' => now(),
            ];
        }

        if ($request->hasFile('tor_file')) {
            if ($existingReview?->tor_path) {
                Storage::disk('local')->delete($existingReview->tor_path);
            }

            $file = $request->file('tor_file');
            $documentPayload = [
                ...$documentPayload,
                'tor_path' => $file->store("approved-work-plan-items/{$item->id}/tor", 'local'),
                'tor_name' => $file->getClientOriginalName(),
                'tor_uploaded_at' => now(),
                'document_uploaded_by' => $request->user()?->id,
                'document_uploaded_at' => now(),
            ];
        }

        if ($request->hasFile('concept_note_file')) {
            if ($existingReview?->concept_note_path) {
                Storage::disk('local')->delete($existingReview->concept_note_path);
            }

            $file = $request->file('concept_note_file');
            $documentPayload = [
                ...$documentPayload,
                'concept_note_path' => $file->store("approved-work-plan-items/{$item->id}/concept-note", 'local'),
                'concept_note_name' => $file->getClientOriginalName(),
                'concept_note_uploaded_at' => now(),
                'document_uploaded_by' => $request->user()?->id,
                'document_uploaded_at' => now(),
            ];
        }

        ApprovedWorkPlanItemReview::updateOrCreate(
            ['purchase_request_item_id' => $item->id],
            [
                'program_funding_id' => $purchaseRequest?->program_funding_id,
                'funder_id' => $purchaseRequest?->programFunding?->funder_id,
                'status' => $data['status'],
                'reviewed_by' => $request->user()?->id,
                'reviewed_at' => now(),
                'review_notes' => $data['review_notes'] ?? null,
                ...$documentPayload,
            ]
        );

        return back()->with('success', 'Work plan item review updated.');
    }

    public function partnerReviewItem(Request $request, PurchaseRequestItem $item)
    {
        $this->assertPartnerCanAccessItem($request, $item);

        $data = $request->validate([
            'status' => 'required|in:pending,approved,rejected,needs_revision',
            'review_notes' => 'nullable|string|max:2000',
        ]);

        $item->load('purchaseRequest.programFunding.funder');
        $purchaseRequest = $item->purchaseRequest;

        ApprovedWorkPlanItemReview::updateOrCreate(
            ['purchase_request_item_id' => $item->id],
            [
                'program_funding_id' => $purchaseRequest?->program_funding_id,
                'funder_id' => $purchaseRequest?->programFunding?->funder_id,
                'status' => $data['status'],
                'reviewed_by' => $request->user()?->id,
                'reviewed_at' => now(),
                'review_notes' => $data['review_notes'] ?? null,
            ]
        );

        return back()->with('success', 'World Bank review updated.');
    }

    public function updateItem(Request $request, PurchaseRequestItem $item)
    {
        abort_unless($request->user()?->hasPermission('finance.awp.edit'), 403);

        $item->loadMissing([
            'awpReview',
            'purchaseRequest.commitments.approvedWorkPlans',
            'purchaseRequest.programFunding',
            'purchaseRequest.subActivity',
            'resource',
            'resourceCategory',
        ]);

        abort_if(
            $item->awpReview?->status === 'approved',
            403,
            'This work plan item is already approved by the World Bank and cannot be edited.'
        );

        $data = $request->validate([
            'sub_activity_id' => [
                'required',
                'uuid',
                Rule::exists('myb_sub_activities', 'id'),
            ],
            'activity' => 'nullable|string|max:255',
            'work_plan_serial' => 'nullable|string|max:40',
            'implemented_by' => 'nullable|string|max:255',
            'budget_code' => 'nullable|string|max:255',
            'estimated_amount' => 'required|numeric|min:0|max:999999999999.99',
            'object_type' => 'nullable|string|max:255',
            'work_plan_months' => 'nullable|array',
            'work_plan_months.*' => ['string', Rule::in(array_keys($this->workPlanMonthLabels()))],
            'work_plan_audience' => 'nullable|string|max:255',
            'work_plan_units' => 'nullable|string|max:255',
            'work_plan_payment_basis' => ['nullable', Rule::in(['one_off', 'scheduled', 'monthly'])],
            'work_plan_person_months' => 'nullable|integer|min:0|max:120',
            'work_plan_monthly_amount' => 'nullable|numeric|min:0|max:999999999999.99',
            'intermediate_indicator' => 'nullable|string|max:4000',
            'result_indicator' => 'nullable|string|max:4000',
            'observations' => 'nullable|string|max:4000',
            'attp_secretariat_comments' => 'nullable|string|max:4000',
        ]);
        $data = array_merge([
            'activity' => null,
            'work_plan_serial' => null,
            'implemented_by' => null,
            'budget_code' => null,
            'object_type' => null,
            'work_plan_months' => [],
            'work_plan_audience' => null,
            'work_plan_units' => null,
            'work_plan_payment_basis' => null,
            'work_plan_person_months' => null,
            'work_plan_monthly_amount' => null,
            'intermediate_indicator' => null,
            'result_indicator' => null,
            'observations' => null,
            'attp_secretariat_comments' => null,
        ], $data);

        $purchaseRequest = $item->purchaseRequest;
        $selectedSubActivity = SubActivity::with('activity.project')->findOrFail($data['sub_activity_id']);
        $programId = $purchaseRequest?->programFunding?->program_id;
        if ($programId && (string) $selectedSubActivity->activity?->project?->program_id !== (string) $programId) {
            throw ValidationException::withMessages([
                'sub_activity_id' => 'Select a sub-activity that belongs to this work plan program.',
            ]);
        }

        $data['activity'] = trim((string) ($data['activity'] ?? '')) ?: $selectedSubActivity->name;

        $amount = round((float) $data['estimated_amount'], 2);
        $months = $this->normalizeWorkPlanMonths($data['work_plan_months'] ?? []);
        $paymentBasis = $this->normalizePaymentBasis(
            $data['work_plan_payment_basis'] ?? null,
            $data,
            $months
        );
        $personMonths = $data['work_plan_person_months'] ?? null;
        $personMonths = $personMonths !== null && $personMonths !== ''
            ? (int) $personMonths
            : ($paymentBasis === 'monthly' ? count($months) : null);
        $monthlyAmount = $data['work_plan_monthly_amount'] ?? null;
        $monthlyAmount = $monthlyAmount !== null && $monthlyAmount !== ''
            ? round((float) $monthlyAmount, 2)
            : (($paymentBasis === 'monthly' && $personMonths > 0) ? round($amount / $personMonths, 2) : null);
        $category = $this->resourceCategoryForObjectType($data['object_type'] ?? null, $request->user()?->id);
        $resource = $this->syncWorkPlanResource($item, $category, $data, $request->user()?->id);

        $item->update([
            'resource_category_id' => $category->id,
            'resource_id' => $resource->id,
            'work_plan_serial' => $data['work_plan_serial'] ?: null,
            'implemented_by' => $data['implemented_by'] ?: null,
            'budget_code' => $data['budget_code'] ?: null,
            'object_type' => $data['object_type'] ?: null,
            'estimated_amount' => $amount,
            'amount' => $amount,
            'work_plan_months' => $months ?: null,
            'work_plan_audience' => $data['work_plan_audience'] ?: null,
            'work_plan_units' => $data['work_plan_units'] ?: null,
            'work_plan_payment_basis' => $paymentBasis,
            'work_plan_person_months' => $personMonths,
            'work_plan_monthly_amount' => $monthlyAmount,
            'milestone' => $data['intermediate_indicator'] ?: $data['result_indicator'] ?: null,
            'intermediate_indicator' => $data['intermediate_indicator'] ?: null,
            'result_indicator' => $data['result_indicator'] ?: null,
            'observations' => $data['observations'] ?: null,
            'attp_secretariat_comments' => $data['attp_secretariat_comments'] ?: null,
        ]);

        if ($purchaseRequest) {
            $purchaseRequest->update([
                'allocation_id' => $selectedSubActivity->id,
                'total_amount' => $amount,
                'description' => 'FY' . ($purchaseRequest->start_year ?: now()->year) . ' ATTP work plan item: ' . $data['activity'],
            ]);

            foreach ($purchaseRequest->commitments as $commitment) {
                $commitment->update([
                    'resource_category_id' => $category->id,
                    'resource_id' => $resource->id,
                    'allocation_id' => $selectedSubActivity->id,
                    'commitment_amount' => $amount,
                    'description' => 'FY' . ($commitment->commitment_year ?: $purchaseRequest->start_year) . ' ATTP work plan commitment: ' . $data['activity'],
                ]);

                $commitment->approvedWorkPlans()->update([
                    'title' => $data['activity'],
                    'planned_amount' => $amount,
                    'expected_outputs' => $data['result_indicator'] ?: $data['intermediate_indicator'],
                    'implementation_notes' => trim(($data['observations'] ?? '') . "\n" . ($data['attp_secretariat_comments'] ?? '')) ?: null,
                ]);
            }
        }

        return back()->with('success', 'Work plan item updated.');
    }

    public function downloadItemDocument(PurchaseRequestItem $item)
    {
        $review = $item->awpReview;

        $type = request('type');
        $path = match ($type) {
            'tor' => $review?->tor_path,
            'concept_note' => $review?->concept_note_path,
            default => $review?->document_path,
        };
        $name = match ($type) {
            'tor' => $review?->tor_name,
            'concept_note' => $review?->concept_note_name,
            default => $review?->document_name,
        };

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download(
            $path,
            $name ?: 'work-plan-document'
        );
    }

    public function partnerDownloadItemDocument(Request $request, PurchaseRequestItem $item)
    {
        $this->assertPartnerCanAccessItem($request, $item);

        return $this->downloadItemDocument($item);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->hydrateFromCommitment($data);

        ApprovedWorkPlan::create([
            ...$data,
            'awp_code' => $data['awp_code'] ?? $this->nextCode(),
            'currency' => 'USD',
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Approved Work Plan created.');
    }

    public function update(Request $request, ApprovedWorkPlan $awp)
    {
        abort_if($awp->status === 'approved' && ! $request->user()?->can('finance.awp.approve'), 403);

        $data = $this->validated($request, $awp);
        $this->hydrateFromCommitment($data);

        $awp->update([
            ...$data,
            'currency' => 'USD',
        ]);

        return back()->with('success', 'Approved Work Plan updated.');
    }

    public function approve(Request $request, ApprovedWorkPlan $awp)
    {
        $data = $request->validate([
            'review_notes' => 'nullable|string|max:2000',
        ]);

        $awp->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'review_notes' => $data['review_notes'] ?? null,
        ]);

        return back()->with('success', 'Approved Work Plan approved.');
    }

    public function close(Request $request, ApprovedWorkPlan $awp)
    {
        $data = $request->validate([
            'review_notes' => 'nullable|string|max:2000',
        ]);

        $awp->update([
            'status' => 'closed',
            'review_notes' => $data['review_notes'] ?? $awp->review_notes,
        ]);

        return back()->with('success', 'Approved Work Plan closed.');
    }

    public function destroy(ApprovedWorkPlan $awp)
    {
        abort_if($awp->status === 'approved', 403, 'Approved work plans cannot be deleted.');

        $awp->delete();

        return back()->with('success', 'Approved Work Plan deleted.');
    }

    private function validated(Request $request, ?ApprovedWorkPlan $awp = null): array
    {
        return $request->validate([
            'awp_code' => ['nullable', 'string', 'max:100', Rule::unique('approved_work_plans', 'awp_code')->ignore($awp?->id)],
            'title' => 'required|string|max:255',
            'budget_commitment_id' => 'nullable|exists:myb_budget_commitments,id',
            'program_funding_id' => 'nullable|exists:myb_program_fundings,id',
            'fiscal_year' => 'nullable|string|max:20',
            'planned_amount' => 'required|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|in:draft,submitted,approved,closed',
            'description' => 'nullable|string',
            'expected_outputs' => 'nullable|string',
            'implementation_notes' => 'nullable|string',
        ]);
    }

    private function hydrateFromCommitment(array &$data): void
    {
        if (empty($data['budget_commitment_id'])) {
            return;
        }

        $commitment = BudgetCommitment::whereKey($data['budget_commitment_id'])->first();
        if (! $commitment) {
            return;
        }

        $data['program_funding_id'] = $data['program_funding_id'] ?: $commitment->program_funding_id;
        $data['fiscal_year'] = $data['fiscal_year'] ?: (string) $commitment->commitment_year;
        $data['planned_amount'] = (float) ($data['planned_amount'] ?: $commitment->commitment_amount);
        $data['governance_node_id'] = $commitment->governance_node_id;
    }

    private function nextCode(): string
    {
        return 'AWP-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5));
    }

    private function resolveFilters(Request $request): array
    {
        $mode = $request->input('filter_mode', 'multi_year');
        $year = (int) $request->input('year', now()->year);
        $startYear = (int) $request->input('start_year', $year);
        $endYear = (int) $request->input('end_year', $startYear);
        $startDate = null;
        $endDate = null;

        if ($mode === 'yearly') {
            $startYear = $endYear = $year;
            $startDate = Carbon::create($year, 1, 1)->startOfDay();
            $endDate = Carbon::create($year, 12, 31)->endOfDay();
        } elseif ($mode === 'quarterly') {
            $quarter = max(1, min(4, (int) $request->input('quarter', 1)));
            $startMonth = (($quarter - 1) * 3) + 1;
            $startYear = $endYear = $year;
            $startDate = Carbon::create($year, $startMonth, 1)->startOfDay();
            $endDate = (clone $startDate)->addMonths(2)->endOfMonth()->endOfDay();
        } elseif ($mode === 'semiannual') {
            $half = (int) $request->input('half', 1) === 2 ? 2 : 1;
            $startYear = $endYear = $year;
            $startDate = Carbon::create($year, $half === 1 ? 1 : 7, 1)->startOfDay();
            $endDate = Carbon::create($year, $half === 1 ? 6 : 12, 1)->endOfMonth()->endOfDay();
        } elseif ($mode === 'range') {
            $startDate = $request->filled('start_date') ? Carbon::parse($request->input('start_date'))->startOfDay() : null;
            $endDate = $request->filled('end_date') ? Carbon::parse($request->input('end_date'))->endOfDay() : null;
            $startYear = $startDate?->year ?? $startYear;
            $endYear = $endDate?->year ?? $startYear;
        } else {
            $mode = 'multi_year';
            if ($endYear < $startYear) {
                $endYear = $startYear;
            }
            $startDate = Carbon::create($startYear, 1, 1)->startOfDay();
            $endDate = Carbon::create($endYear, 12, 31)->endOfDay();
        }

        return [
            'mode' => $mode,
            'start_year' => $startYear,
            'end_year' => $endYear,
            'year_range' => range($startYear, $endYear),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'label' => $startDate && $endDate
                ? $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y')
                : 'All available work plan items',
        ];
    }

    private function buildWorkPlanHierarchy(Program $program, array $fundingIds, array $filters): array
    {
        $requests = empty($fundingIds)
            ? collect()
            : PurchaseRequest::with([
                'items.resourceCategory',
                'items.resource',
                'items.awpReview.reviewer',
                'items.awpReview.documentUploader',
                'programFunding.funder',
                'commitments',
            ])
                ->whereIn('program_funding_id', $fundingIds)
                ->where('allocation_level', 'sub_activity')
                ->get()
                ->filter(function (PurchaseRequest $request) use ($filters) {
                    $date = $this->resolveRequestDate($request);
                    if (!$filters['start_date'] || !$filters['end_date']) {
                        return true;
                    }

                    return $date->between($filters['start_date'], $filters['end_date']);
                });

        $requestsBySubActivity = $requests->groupBy('allocation_id');

        return $program->projects->sortBy(fn ($project) => $this->sortKeyForProject($project))->map(function ($project) use ($requestsBySubActivity) {
            $projectTotal = 0;
            $projectCounts = $this->emptyStatusCounts();

            $activities = $project->activities
                ->sortBy(fn ($activity) => $this->sortKeyForActivity($activity, $requestsBySubActivity))
                ->map(function ($activity) use ($requestsBySubActivity, &$projectTotal, &$projectCounts) {
                $activityTotal = 0;
                $activityCounts = $this->emptyStatusCounts();

                $subActivities = $activity->subActivities
                    ->sortBy(fn ($subActivity) => $this->sortKeyForSubActivity($subActivity, $requestsBySubActivity))
                    ->map(function ($subActivity) use ($requestsBySubActivity, &$activityTotal, &$activityCounts, &$projectTotal, &$projectCounts) {
                    $items = collect($requestsBySubActivity[$subActivity->id] ?? [])
                        ->flatMap(function (PurchaseRequest $request) {
                            $requestCommitmentAmount = $this->committedAmountForRequest($request);
                            $requestItemAmount = (float) $request->items->sum('amount');

                            return $request->items
                                ->sortBy(fn (PurchaseRequestItem $item) => $this->sortKeyForItem($item))
                                ->map(function (PurchaseRequestItem $item) use ($request, $requestCommitmentAmount, $requestItemAmount) {
                                $review = $item->awpReview;
                                $status = $review?->status ?? 'pending';
                                $commitmentAmount = $this->commitmentAmountForItem(
                                    $item,
                                    $requestCommitmentAmount,
                                    $requestItemAmount
                                );
                                $months = $this->normalizeWorkPlanMonths($item->work_plan_months ?? []);
                                $paymentBasis = $this->normalizePaymentBasis($item->work_plan_payment_basis, [
                                    'activity' => $item->resource?->name ?? $item->milestone ?? '',
                                    'object_type' => $item->object_type,
                                    'observations' => $item->observations,
                                    'intermediate_indicator' => $item->intermediate_indicator,
                                ], $months);
                                $personMonths = $item->work_plan_person_months ?: ($paymentBasis === 'monthly' ? count($months) : null);
                                $monthlyAmount = $item->work_plan_monthly_amount
                                    ?: (($paymentBasis === 'monthly' && $personMonths > 0) ? round($commitmentAmount / $personMonths, 2) : null);

                                return [
                                    'item' => $item,
                                    'purchaseRequest' => $request,
                                    'label' => $item->resource?->name
                                        ?? $item->resourceCategory?->name
                                        ?? $item->milestone
                                        ?? $request->description
                                        ?? 'Work plan item',
                                    'amount' => $commitmentAmount,
                                    'status' => $status,
                                    'status_label' => $this->formatWorldBankStatus($status),
                                    'review' => $review,
                                    'is_placeholder' => false,
                                    'month_keys' => $months,
                                    'month_labels' => $this->workPlanMonthLabelsFor($months),
                                    'month_text' => $this->workPlanMonthText($months),
                                    'payment_basis' => $paymentBasis,
                                    'payment_basis_label' => $this->workPlanPaymentBasisLabel($paymentBasis),
                                    'person_months' => $personMonths,
                                    'monthly_amount' => $monthlyAmount,
                                ];
                            });
                        })->values();

                    if ($items->isEmpty()) {
                        $items = collect([
                            [
                                'item' => null,
                                'purchaseRequest' => null,
                                'label' => 'No work plan item recorded',
                                'amount' => 0.0,
                                'status' => 'pending',
                                'status_label' => 'Not approved by World Bank',
                                'review' => null,
                                'is_placeholder' => true,
                                'month_keys' => [],
                                'month_labels' => [],
                                'month_text' => null,
                                'payment_basis' => 'scheduled',
                                'payment_basis_label' => 'Scheduled activity',
                                'person_months' => null,
                                'monthly_amount' => null,
                            ],
                        ]);
                    }

                    $subTotal = (float) $items->sum('amount');
                    $subCounts = $this->countItemStatuses($items);

                    $activityTotal += $subTotal;
                    $projectTotal += $subTotal;
                    $activityCounts = $this->mergeStatusCounts($activityCounts, $subCounts);
                    $projectCounts = $this->mergeStatusCounts($projectCounts, $subCounts);

                    return [
                        'subActivity' => $subActivity,
                        'allocation_years' => $this->subActivityAllocationYears($subActivity),
                        'items' => $items,
                        'total' => $subTotal,
                        'counts' => $subCounts,
                    ];
                })->values();

                return [
                    'activity' => $activity,
                    'subActivities' => $subActivities,
                    'total' => $activityTotal,
                    'counts' => $activityCounts,
                ];
            })->values();

            return [
                'project' => $project,
                'activities' => $activities,
                'total' => $projectTotal,
                'counts' => $projectCounts,
            ];
        })->values()->all();
    }

    private function summarizeWorkPlan(array $report): array
    {
        $counts = $this->emptyStatusCounts();
        $amount = 0;

        foreach ($report as $projectRow) {
            $amount += $projectRow['total'];
            $counts = $this->mergeStatusCounts($counts, $projectRow['counts']);
        }

        $totalItems = array_sum($counts);

        return [
            'total_items' => $totalItems,
            'approved' => $counts['approved'],
            'pending' => $counts['pending'],
            'rejected' => $counts['rejected'],
            'needs_revision' => $counts['needs_revision'],
            'approval_rate' => $totalItems > 0 ? round(($counts['approved'] / $totalItems) * 100, 2) : 0,
            'amount' => $amount,
        ];
    }

    private function workPlanMonthLabels(): array
    {
        return [
            'jan' => 'Jan',
            'feb' => 'Feb',
            'mar' => 'Mar',
            'apr' => 'Apr',
            'may' => 'May',
            'jun' => 'Jun',
            'jul' => 'Jul',
            'aug' => 'Aug',
            'sep' => 'Sep',
            'oct' => 'Oct',
            'nov' => 'Nov',
            'dec' => 'Dec',
        ];
    }

    private function normalizeWorkPlanMonths($months): array
    {
        $labels = $this->workPlanMonthLabels();
        $months = is_array($months) ? $months : [];

        return collect(array_keys($labels))
            ->filter(fn (string $month) => in_array($month, $months, true))
            ->values()
            ->all();
    }

    private function workPlanMonthLabelsFor(array $months): array
    {
        $labels = $this->workPlanMonthLabels();

        return collect($this->normalizeWorkPlanMonths($months))
            ->map(fn (string $month) => $labels[$month])
            ->all();
    }

    private function workPlanMonthText(array $months): ?string
    {
        $labels = $this->workPlanMonthLabelsFor($months);

        return empty($labels) ? null : implode(', ', $labels);
    }

    private function normalizePaymentBasis(?string $paymentBasis, array $data, array $months): string
    {
        if (in_array($paymentBasis, ['one_off', 'scheduled', 'monthly'], true)) {
            return $paymentBasis;
        }

        if ($this->looksLikeMonthlyPersonnelLine($data)) {
            return 'monthly';
        }

        return count($months) > 1 ? 'scheduled' : 'one_off';
    }

    private function looksLikeMonthlyPersonnelLine(array $data): bool
    {
        $objectType = $this->normalizeWorkPlanText((string) ($data['object_type'] ?? ''));
        if ($objectType !== '' && $objectType !== 'consulting') {
            return false;
        }

        $text = $this->normalizeWorkPlanText(implode(' ', [
            $data['activity'] ?? '',
            $data['observations'] ?? '',
            $data['intermediate_indicator'] ?? '',
        ]));

        if (str_contains($text, 'critical position')) {
            return true;
        }

        foreach ([
            'technical project coordinator',
            'procurement specialist',
            'project admin assistant',
            'technical project advisor',
            'communication specialist',
            'project m e officer',
            'financial management specialist',
            'grm consultant',
        ] as $phrase) {
            if (str_contains($text, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function workPlanPaymentBasisLabel(string $paymentBasis): string
    {
        return match ($paymentBasis) {
            'monthly' => 'Monthly / person-month',
            'scheduled' => 'Scheduled activity',
            default => 'One-off / milestone',
        };
    }

    private function subActivityAllocationYears($subActivity)
    {
        return ($subActivity->allocations ?? collect())
            ->sortBy('year')
            ->map(fn ($allocation) => [
                'year' => (int) $allocation->year,
                'amount' => (float) $allocation->amount,
            ])
            ->values();
    }

    private function resourceCategoryForObjectType(?string $objectType, ?string $createdBy): ResourceCategory
    {
        $normalized = $this->normalizeWorkPlanText((string) $objectType);
        $name = match (true) {
            str_contains($normalized, 'workshop') || str_contains($normalized, 'w shop') => 'Workshops & Events',
            str_contains($normalized, 'goods') => 'Goods',
            str_contains($normalized, 'application') || str_contains($normalized, 'pass through') => 'Grants / Transfers',
            str_contains($normalized, 'staff') || str_contains($normalized, 'translation') || str_contains($normalized, 'communication') || str_contains($normalized, 'ioc') => 'Implementation/Operational Costs (IOC)',
            default => 'Consulting Services',
        };

        return ResourceCategory::firstOrCreate(
            ['name' => $name],
            [
                'description' => $name . ' for ATTP work plan items',
                'status' => 'active',
                'created_by' => $createdBy,
            ]
        );
    }

    private function syncWorkPlanResource(PurchaseRequestItem $item, ResourceCategory $category, array $data, ?string $createdBy): Resource
    {
        $resource = $item->resource ?: new Resource();
        $resource->fill([
            'resource_category_id' => $category->id,
            'name' => $data['activity'],
            'reference_code' => $data['budget_code'] ?: $resource->reference_code ?: 'AWP-' . now()->year . '-' . Str::upper(Str::random(5)),
            'description' => $data['intermediate_indicator'] ?: $data['result_indicator'] ?: null,
            'status' => 'active',
            'is_human_resource' => $this->normalizeWorkPlanText((string) ($data['object_type'] ?? '')) === 'consulting',
            'created_by' => $resource->created_by ?: $createdBy,
        ]);
        $resource->save();

        return $resource;
    }

    private function normalizeWorkPlanText(string $value): string
    {
        $value = Str::ascii(Str::lower($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private function sortKeyForProject($project): string
    {
        if (preg_match('/component\s*#?\s*(\d+)/i', (string) $project->name, $matches)) {
            return sprintf('%03d-%s', (int) $matches[1], Str::lower($project->name));
        }

        return '999-' . Str::lower((string) $project->name);
    }

    private function sortKeyForActivity($activity, $requestsBySubActivity): string
    {
        $sortOrder = $activity->subActivities
            ->map(fn ($subActivity) => $this->minimumWorkPlanSortOrder($subActivity, $requestsBySubActivity))
            ->filter()
            ->min();

        $subComponentOrder = 999;
        if (preg_match('/\(([a-z])\)/i', (string) $activity->name, $matches)) {
            $subComponentOrder = ord(Str::lower($matches[1])) - 96;
        }

        return sprintf(
            '%010d-%03d-%s',
            $sortOrder ?: 999999999,
            $subComponentOrder,
            Str::lower((string) $activity->name)
        );
    }

    private function sortKeyForSubActivity($subActivity, $requestsBySubActivity): string
    {
        return sprintf(
            '%010d-%s',
            $this->minimumWorkPlanSortOrder($subActivity, $requestsBySubActivity) ?: 999999999,
            Str::lower((string) $subActivity->name)
        );
    }

    private function sortKeyForItem(PurchaseRequestItem $item): string
    {
        return sprintf(
            '%010d-%s',
            $item->work_plan_sort_order ?: 999999999,
            Str::lower((string) ($item->resource?->name ?? $item->milestone ?? $item->id))
        );
    }

    private function minimumWorkPlanSortOrder($subActivity, $requestsBySubActivity): ?int
    {
        return collect($requestsBySubActivity[$subActivity->id] ?? [])
            ->flatMap(fn (PurchaseRequest $request) => $request->items)
            ->pluck('work_plan_sort_order')
            ->filter()
            ->min();
    }

    private function resolveRequestDate(PurchaseRequest $request): Carbon
    {
        return $request->commitment_date
            ?? $request->delivery_date
            ?? $request->created_at
            ?? now();
    }

    private function emptyStatusCounts(): array
    {
        return [
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'needs_revision' => 0,
        ];
    }

    private function committedAmountForRequest(PurchaseRequest $request): float
    {
        $eligibleStatuses = [
            BudgetCommitment::STATUS_SUBMITTED,
            BudgetCommitment::STATUS_APPROVED,
        ];

        $commitments = $request->commitments ?? collect();
        $committed = (float) $commitments
            ->whereIn('status', $eligibleStatuses)
            ->sum('commitment_amount');

        return $committed > 0 ? $committed : (float) $commitments->sum('commitment_amount');
    }

    private function commitmentAmountForItem(PurchaseRequestItem $item, float $requestCommitmentAmount, float $requestItemAmount): float
    {
        if ($requestCommitmentAmount <= 0) {
            return 0.0;
        }

        if ($requestItemAmount <= 0) {
            return round($requestCommitmentAmount, 2);
        }

        return round($requestCommitmentAmount * ((float) $item->amount / $requestItemAmount), 2);
    }

    private function countItemStatuses($items): array
    {
        $counts = $this->emptyStatusCounts();
        foreach ($items as $item) {
            $status = $item['status'] ?? 'pending';
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        return $counts;
    }

    private function mergeStatusCounts(array $left, array $right): array
    {
        foreach ($right as $status => $count) {
            $left[$status] = ($left[$status] ?? 0) + $count;
        }

        return $left;
    }

    private function formatWorldBankStatus(string $status): string
    {
        return match ($status) {
            'approved' => 'Approved by World Bank',
            'rejected' => 'Rejected by World Bank',
            'needs_revision' => 'Needs revision by World Bank',
            default => 'Not approved by World Bank',
        };
    }

    private function canReviewAwpItems(Request $request): bool
    {
        $user = $request->user();

        return (bool) ($user && ($user->hasPermission('finance.awp.approve') || $user->isFundingPartner()));
    }

    private function partnerFunder(Request $request): Funder
    {
        $funder = Funder::where('user_id', $request->user()?->id ?? Auth::id())->first();

        abort_unless($funder && $funder->hasPortalAccess(), 403, 'No funding partner account is linked to this user.');

        return $funder;
    }

    private function assertPartnerCanAccessItem(Request $request, PurchaseRequestItem $item): void
    {
        $funder = $this->partnerFunder($request);
        $item->loadMissing('purchaseRequest.programFunding');
        $itemFunderId = $item->purchaseRequest?->programFunding?->funder_id;

        abort_unless((string) $itemFunderId === (string) $funder->id, 403, 'This work plan item is not funded by your partner account.');
    }
}
