<?php

namespace App\Http\Controllers;

use App\Mail\EvaluationAssigned;
use App\Mail\VendorProcurementLifecycleMail;
use App\Models\ConsortiumThinkTank;
use App\Models\DynamicForm;
use App\Models\DynamicFormField;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationCriteria;
use App\Models\EvaluationSection;
use App\Models\EvaluationSubmission;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\ThinkTankProcurementDocument;
use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use App\Models\User;
use App\Services\EvaluationReworkGuard;
use App\Services\ProcurementPublicationNotificationService;
use App\Services\ThinkTankProcurementWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ThinkTankProcurementPlanController extends Controller
{
    public function __construct(
        private readonly ThinkTankProcurementWorkflowService $workflow
    ) {}

    public function index(Request $request)
    {
        $member = $this->member($request);
        $status = trim((string) $request->query('status'));
        $fiscalYear = trim((string) $request->query('fiscal_year'));
        $keyword = trim((string) $request->query('q'));

        $base = ThinkTankProcurementPlan::query()->where('think_tank_member_id', $member->id);
        $plans = (clone $base)
            ->withCount([
                'items',
                'items as approved_items_count' => fn ($query) => $query->whereIn('status', [
                    ThinkTankProcurementItem::STATUS_APPROVED,
                    ThinkTankProcurementItem::STATUS_NO_OBJECTION,
                    ThinkTankProcurementItem::STATUS_PUBLISHED,
                ]),
                'items as action_items_count' => fn ($query) => $query->whereIn('status', [
                    ThinkTankProcurementItem::STATUS_REVISION_REQUESTED,
                    ThinkTankProcurementItem::STATUS_REJECTED,
                ]),
            ])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($fiscalYear !== '', fn ($query) => $query->where('fiscal_year', $fiscalYear))
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $search = '%'.$keyword.'%';
                $query->where(fn ($nested) => $nested
                    ->where('title', 'like', $search)
                    ->orWhere('plan_code', 'like', $search));
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $allPlans = (clone $base)->with('items:id,plan_id,status,estimated_amount')->get();
        $allItems = $allPlans->flatMap->items;
        $stats = [
            'plans' => $allPlans->count(),
            'items' => $allItems->count(),
            'budget' => (float) $allItems->sum('estimated_amount'),
            'action_required' => $allItems->whereIn('status', [
                ThinkTankProcurementItem::STATUS_REVISION_REQUESTED,
                ThinkTankProcurementItem::STATUS_REJECTED,
            ])->count(),
            'no_objection' => $allItems->whereIn('status', [
                ThinkTankProcurementItem::STATUS_NO_OBJECTION,
                ThinkTankProcurementItem::STATUS_PUBLISHED,
            ])->count(),
        ];
        $fiscalYears = (clone $base)->pluck('fiscal_year')->filter()->unique()->sortDesc()->values();
        $portalRouteParams = $this->portalRouteParams($request, $member);
        $filters = compact('status', 'fiscalYear', 'keyword');

        return view('think-tank.procurement-plans', compact(
            'member', 'plans', 'stats', 'fiscalYears', 'portalRouteParams', 'filters'
        ));
    }

    public function create(Request $request)
    {
        $member = $this->member($request);
        $currency = Str::upper($member->consortium?->currency ?: 'USD');
        $existingPlans = ThinkTankProcurementPlan::query()
            ->where('think_tank_member_id', $member->id)
            ->withCount('items')
            ->latest('fiscal_year')
            ->limit(8)
            ->get();
        $portalRouteParams = $this->portalRouteParams($request, $member);

        return view('think-tank.procurement-plan-create', compact(
            'member', 'currency', 'existingPlans', 'portalRouteParams'
        ));
    }

    public function evaluations(Request $request)
    {
        $member = $this->member($request);
        $user = $request->user();
        $portalRouteParams = $this->portalRouteParams($request, $member);

        $assignments = EvaluationAssignment::query()
            ->with(['evaluation', 'procurement.thinkTankPlanningItem.plan'])
            ->where('user_id', $user->id)
            ->whereHas('procurement', fn ($query) => $query
                ->where('think_tank_member_id', $member->id)
                ->whereNull('procurements.deleted_at'))
            ->latest('assigned_at')
            ->latest()
            ->get();

        $specificSubmissionIds = $assignments
            ->pluck('form_submission_id')
            ->filter()
            ->unique();
        $wholeProcurementIds = $assignments
            ->whereNull('form_submission_id')
            ->pluck('procurement_id')
            ->filter()
            ->unique();

        $submissions = ($specificSubmissionIds->isEmpty() && $wholeProcurementIds->isEmpty())
            ? collect()
            : FormSubmission::query()
                ->with(['form', 'submitter', 'values'])
                ->availableForEvaluation()
                ->where(function ($query) use ($specificSubmissionIds, $wholeProcurementIds): void {
                    if ($specificSubmissionIds->isNotEmpty()) {
                        $query->orWhereIn('id', $specificSubmissionIds);
                    }
                    if ($wholeProcurementIds->isNotEmpty()) {
                        $query->orWhereIn('procurement_id', $wholeProcurementIds);
                    }
                })
                ->latest()
                ->get();

        $evaluationIds = $assignments->pluck('evaluation_id')->filter()->unique();
        $procurementIds = $assignments->pluck('procurement_id')->filter()->unique();
        $submissionIds = $submissions->pluck('id');
        $evaluationSubmissions = ($evaluationIds->isEmpty() || $procurementIds->isEmpty() || $submissionIds->isEmpty())
            ? collect()
            : EvaluationSubmission::query()
                ->where('evaluator_id', $user->id)
                ->whereIn('evaluation_id', $evaluationIds)
                ->whereIn('procurement_id', $procurementIds)
                ->whereIn('form_submission_id', $submissionIds)
                ->get()
                ->keyBy(fn (EvaluationSubmission $submission): string => implode(':', [
                    $submission->evaluation_id,
                    $submission->procurement_id,
                    $submission->form_submission_id,
                ]));

        $taskCount = $assignments->sum(function (EvaluationAssignment $assignment) use ($submissions): int {
            return $assignment->form_submission_id
                ? $submissions->where('id', $assignment->form_submission_id)->count()
                : $submissions->where('procurement_id', $assignment->procurement_id)->count();
        });
        $completedCount = $evaluationSubmissions->filter(fn (EvaluationSubmission $submission): bool => filled($submission->submitted_at))->count();
        $draftCount = $evaluationSubmissions->filter(fn (EvaluationSubmission $submission): bool => blank($submission->submitted_at))->count();
        $stats = [
            'assignments' => $assignments->count(),
            'tasks' => $taskCount,
            'completed' => $completedCount,
            'drafts' => $draftCount,
            'pending' => max(0, $taskCount - $completedCount),
        ];

        return view('think-tank.evaluations', compact(
            'member', 'assignments', 'submissions', 'evaluationSubmissions', 'stats', 'portalRouteParams'
        ));
    }

    public function evaluationAssignments(Request $request)
    {
        $member = $this->member($request);
        $portalRouteParams = $this->portalRouteParams($request, $member);
        $keyword = trim((string) $request->query('q'));
        $phase = in_array($request->query('phase'), ['technical', 'financial'], true)
            ? (string) $request->query('phase')
            : '';

        $evaluations = Evaluation::query()
            ->where('think_tank_member_id', $member->id)
            ->whereNotNull('procurement_id')
            ->with([
                'sections.criteria',
                'assignments.evaluator:id,name,email,think_tank_access_level,is_disabled',
                'procurement.thinkTankPlanningItem.plan',
            ])
            ->when($phase !== '', fn ($query) => $query->where('evaluation_phase', $phase))
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $search = '%'.$keyword.'%';
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', $search)
                        ->orWhereHas('procurement', fn ($procurement) => $procurement
                            ->where('title', 'like', $search)
                            ->orWhere('reference_no', 'like', $search));
                });
            })
            ->latest()
            ->get();

        $teamMembers = User::query()
            ->where('user_type', 'think_tank')
            ->where('is_disabled', false)
            ->where(function ($query) use ($member): void {
                $query->where('think_tank_member_id', $member->id)
                    ->orWhere('id', $member->portal_user_id);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'think_tank_access_level']);

        $allMemberEvaluations = Evaluation::query()
            ->where('think_tank_member_id', $member->id)
            ->whereNotNull('procurement_id')
            ->with('assignments:id,evaluation_id,user_id,status')
            ->get();
        $stats = [
            'templates' => $allMemberEvaluations->count(),
            'assignments' => $allMemberEvaluations->sum(fn (Evaluation $evaluation): int => $evaluation->assignments->count()),
            'evaluators' => $allMemberEvaluations->flatMap->assignments->pluck('user_id')->filter()->unique()->count(),
            'unassigned' => $allMemberEvaluations->filter(fn (Evaluation $evaluation): bool => $evaluation->assignments->isEmpty())->count(),
        ];
        $filters = compact('keyword', 'phase');

        return view('think-tank.evaluation-assignments', compact(
            'member', 'evaluations', 'teamMembers', 'stats', 'filters', 'portalRouteParams'
        ));
    }

    public function technicalEvaluationTemplates(Request $request)
    {
        return $this->evaluationTemplates($request, 'technical');
    }

    public function financialEvaluationTemplates(Request $request)
    {
        return $this->evaluationTemplates($request, 'financial');
    }

    public function storeTechnicalEvaluationTemplate(Request $request)
    {
        return $this->storeEvaluationTemplateForPhase($request, 'technical');
    }

    public function storeFinancialEvaluationTemplate(Request $request)
    {
        return $this->storeEvaluationTemplateForPhase($request, 'financial');
    }

    private function evaluationTemplates(Request $request, string $phase)
    {
        $member = $this->member($request);
        $portalRouteParams = $this->portalRouteParams($request, $member);
        $keyword = trim((string) $request->query('q'));

        $templates = Evaluation::query()
            ->where('think_tank_member_id', $member->id)
            ->where('evaluation_phase', $phase)
            ->with([
                'sections.criteria',
                'assignments.evaluator:id,name,email',
                'procurement.thinkTankPlanningItem.plan',
            ])
            ->when($keyword !== '', function ($query) use ($keyword): void {
                $search = '%'.$keyword.'%';
                $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', $search)
                        ->orWhere('description', 'like', $search)
                        ->orWhereHas('procurement', fn ($procurement) => $procurement
                            ->where('title', 'like', $search)
                            ->orWhere('reference_no', 'like', $search));
                });
            })
            ->latest()
            ->get();

        $eligibleItems = ThinkTankProcurementItem::query()
            ->whereHas('plan', fn ($query) => $query->where('think_tank_member_id', $member->id))
            ->whereNotNull('procurement_id')
            ->with(['plan:id,title,fiscal_year', 'procurement:id,title,slug,reference_no'])
            ->orderByDesc('updated_at')
            ->get(['id', 'plan_id', 'procurement_id', 'item_code', 'title']);

        $allPhaseTemplates = Evaluation::query()
            ->where('think_tank_member_id', $member->id)
            ->where('evaluation_phase', $phase)
            ->with(['sections.criteria', 'assignments:id,evaluation_id,user_id'])
            ->get();
        $stats = [
            'templates' => $allPhaseTemplates->count(),
            'criteria' => $allPhaseTemplates->sum(fn (Evaluation $evaluation): int => $evaluation->sections->sum(fn (EvaluationSection $section): int => $section->criteria->count())),
            'assignments' => $allPhaseTemplates->sum(fn (Evaluation $evaluation): int => $evaluation->assignments->count()),
            'opportunities' => $allPhaseTemplates->pluck('procurement_id')->filter()->unique()->count(),
        ];

        return view('think-tank.evaluation-templates', compact(
            'member', 'phase', 'templates', 'eligibleItems', 'stats', 'keyword', 'portalRouteParams'
        ));
    }

    private function storeEvaluationTemplateForPhase(Request $request, string $phase)
    {
        $member = $this->member($request);
        $data = $request->validate([
            'item_id' => ['required', 'uuid', Rule::exists('attp_think_tank_procurement_items', 'id')],
        ]);
        $item = ThinkTankProcurementItem::query()
            ->whereKey($data['item_id'])
            ->whereHas('plan', fn ($query) => $query->where('think_tank_member_id', $member->id))
            ->with('plan')
            ->firstOrFail();

        $request->merge(['evaluation_phase' => $phase]);

        return $this->storeEvaluation($request, $item->plan, $item);
    }

    public function store(Request $request)
    {
        $member = $this->member($request);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'fiscal_year' => 'required|string|max:20',
            'currency' => 'required|string|max:10',
            'description' => 'nullable|string|max:5000',
        ]);

        $duplicate = ThinkTankProcurementPlan::query()
            ->where('think_tank_member_id', $member->id)
            ->whereRaw('LOWER(fiscal_year) = ?', [Str::lower($data['fiscal_year'])])
            ->where('status', '<>', ThinkTankProcurementPlan::STATUS_REJECTED)
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'fiscal_year' => 'An active procurement plan folder already exists for this financial year.',
            ]);
        }

        $plan = ThinkTankProcurementPlan::create([
            'consortium_id' => $member->consortium_id,
            'think_tank_member_id' => $member->id,
            'plan_code' => $this->workflow->nextPlanCode($data['fiscal_year']),
            'title' => $data['title'],
            'fiscal_year' => $data['fiscal_year'],
            'currency' => Str::upper($data['currency']),
            'estimated_budget' => 0,
            'description' => $data['description'] ?? null,
            'status' => ThinkTankProcurementPlan::STATUS_DRAFT,
            'created_by' => $request->user()->id,
        ]);

        $this->workflow->event($plan, null, $request->user(), 'plan_folder_created', null, $plan->status);

        return redirect()
            ->route('think-tank.procurement-plans.show', array_merge($this->portalRouteParams($request, $member), ['plan' => $plan]))
            ->with('success', 'Financial-year procurement plan created. Add the planned items and their TOR documents.');
    }

    public function show(Request $request, ThinkTankProcurementPlan $plan)
    {
        $member = $this->memberForPlan($request, $plan);
        $this->assertPlanBelongsToMember($plan, $member);

        $plan->load([
            'items.documents',
            'items.procurement.activeForm.fields',
            'items.procurement.submissions.submitter',
            'events.actor:id,name',
            'events.item:id,item_code,title',
        ]);

        $procurementIds = $plan->items->pluck('procurement_id')->filter();
        $evaluations = Evaluation::query()
            ->where('think_tank_member_id', $member->id)
            ->whereIn('procurement_id', $procurementIds)
            ->with(['sections.criteria', 'assignments.evaluator:id,name,email', 'assignments.procurement:id,title,slug'])
            ->latest()
            ->get();

        $teamMembers = User::query()
            ->where('user_type', 'think_tank')
            ->where(function ($query) use ($member): void {
                $query->where('think_tank_member_id', $member->id)
                    ->orWhere('id', $member->portal_user_id);
            })
            ->where('is_disabled', false)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'think_tank_access_level']);

        $itemStats = [
            'total' => $plan->items->count(),
            'budget' => (float) $plan->items->sum('estimated_amount'),
            'approved' => $plan->items->whereIn('status', [
                ThinkTankProcurementItem::STATUS_APPROVED,
                ThinkTankProcurementItem::STATUS_NO_OBJECTION,
                ThinkTankProcurementItem::STATUS_PUBLISHED,
            ])->count(),
            'action' => $plan->items->whereIn('status', [
                ThinkTankProcurementItem::STATUS_REVISION_REQUESTED,
                ThinkTankProcurementItem::STATUS_REJECTED,
            ])->count(),
            'published' => $plan->items->where('status', ThinkTankProcurementItem::STATUS_PUBLISHED)->count(),
        ];
        $portalRouteParams = $this->portalRouteParams($request, $member);

        return view('think-tank.procurement-plan-show', compact(
            'member', 'plan', 'itemStats', 'evaluations', 'teamMembers', 'portalRouteParams'
        ));
    }

    public function update(Request $request, ThinkTankProcurementPlan $plan)
    {
        $member = $this->memberForPlan($request, $plan);
        $this->assertPlanBelongsToMember($plan, $member);
        abort_unless($plan->isEditable(), 422, 'This plan folder is locked while under review or after approval.');

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'fiscal_year' => 'required|string|max:20',
            'currency' => 'required|string|max:10',
            'description' => 'nullable|string|max:5000',
        ]);
        DB::transaction(function () use ($request, $plan, $member, $data): void {
            $lockedPlan = $this->lockPlanForMutation($plan, $member);
            abort_unless($lockedPlan->isEditable(), 422, 'This plan folder is locked while under review or after approval.');

            $lockedPlan->update([...$data, 'currency' => Str::upper($data['currency'])]);
            $this->workflow->event(
                $lockedPlan,
                null,
                $request->user(),
                'plan_folder_updated',
                $lockedPlan->status,
                $lockedPlan->status
            );
        });

        return back()->with('success', 'Plan details updated.');
    }

    public function storeItem(Request $request, ThinkTankProcurementPlan $plan)
    {
        $member = $this->memberForPlan($request, $plan);
        $this->assertPlanBelongsToMember($plan, $member);
        abort_unless($plan->isEditable(), 422, 'Items cannot be added while this plan is under review or approved.');

        $data = $this->validateItem($request, true);
        $storedPaths = [];

        try {
            $item = DB::transaction(function () use ($request, $plan, $member, $data, &$storedPaths): ThinkTankProcurementItem {
                $lockedPlan = $this->lockPlanForMutation($plan, $member);
                abort_unless($lockedPlan->isEditable(), 422, 'Items cannot be added while this plan is under review or approved.');

                $item = $lockedPlan->items()->create([
                    ...$this->itemAttributes($data),
                    'item_code' => $this->workflow->nextItemCode($lockedPlan),
                    'currency' => Str::upper($data['currency'] ?? $lockedPlan->currency),
                    'status' => ThinkTankProcurementItem::STATUS_DRAFT,
                    'source_activity_status' => ThinkTankProcurementItem::ACTIVITY_STATUS_DRAFT,
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);

                $this->storeItemDocuments($request, $item, $storedPaths);
                $this->workflow->syncPlanBudget($lockedPlan);
                $this->workflow->event($lockedPlan, $item, $request->user(), 'item_created', null, $item->status, null, [
                    'estimated_amount' => (float) $item->estimated_amount,
                ]);

                return $item;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }
            if ($exception instanceof HttpExceptionInterface) {
                throw $exception;
            }
            report($exception);

            return back()->withInput()->with('error', 'The procurement item could not be saved. No uploaded file was retained.');
        }

        return back()->with('success', $item->item_code.' added to the annual plan.');
    }

    public function updateItem(Request $request, ThinkTankProcurementPlan $plan, ThinkTankProcurementItem $item)
    {
        $member = $this->memberForPlan($request, $plan);
        $this->assertItemBelongsToPlan($item, $plan, $member);
        abort_unless($item->isEditable(), 422, 'This item is locked while under review or after approval.');

        $data = $this->validateItem($request, false);
        $storedPaths = [];

        try {
            $item = DB::transaction(function () use ($request, $plan, $item, $member, $data, &$storedPaths): ThinkTankProcurementItem {
                $lockedPlan = $this->lockPlanForMutation($plan, $member);
                $lockedItem = $this->lockItemForMutation($lockedPlan, $item);
                abort_unless($lockedItem->isEditable(), 422, 'This item is locked while under review or after approval.');

                $previousStatus = $lockedItem->status;
                $lockedItem->update([
                    ...$this->itemAttributes($data),
                    'currency' => Str::upper($data['currency'] ?? $lockedPlan->currency),
                    'status' => ThinkTankProcurementItem::STATUS_DRAFT,
                    'source_activity_status' => ThinkTankProcurementItem::ACTIVITY_STATUS_DRAFT,
                    'review_reason' => null,
                    'updated_by' => $request->user()->id,
                ]);
                $this->storeItemDocuments($request, $lockedItem, $storedPaths);
                $this->workflow->syncPlanBudget($lockedPlan);
                $this->workflow->event(
                    $lockedPlan,
                    $lockedItem,
                    $request->user(),
                    'item_corrected',
                    $previousStatus,
                    $lockedItem->status
                );

                return $lockedItem;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }
            if ($exception instanceof HttpExceptionInterface) {
                throw $exception;
            }
            report($exception);

            return back()->withInput()->with('error', 'The procurement item could not be updated. No new file was retained.');
        }

        return back()->with('success', $item->item_code.' updated and returned to draft.');
    }

    public function destroyItem(Request $request, ThinkTankProcurementPlan $plan, ThinkTankProcurementItem $item)
    {
        $member = $this->memberForPlan($request, $plan);
        $this->assertItemBelongsToPlan($item, $plan, $member);

        $deletedItem = DB::transaction(function () use ($request, $plan, $item, $member): ThinkTankProcurementItem {
            $lockedPlan = $this->lockPlanForMutation($plan, $member);
            $lockedItem = $this->lockItemForMutation($lockedPlan, $item);
            abort_unless($lockedItem->isEditable(), 422, 'This item cannot be removed in its current state.');

            $payload = [
                'item_code' => $lockedItem->item_code,
                'title' => $lockedItem->title,
                'status' => $lockedItem->status,
            ];
            $lockedItem->delete();
            $this->workflow->syncPlanBudget($lockedPlan);
            $this->workflow->event($lockedPlan, null, $request->user(), 'item_removed', null, null, null, $payload);

            return $lockedItem;
        });
        Storage::disk('local')->deleteDirectory("think-tank-procurement/{$plan->id}/{$deletedItem->id}");

        return back()->with('success', 'Procurement item removed from the plan.');
    }

    public function destroyDocument(
        Request $request,
        ThinkTankProcurementPlan $plan,
        ThinkTankProcurementItem $item,
        ThinkTankProcurementDocument $document
    ) {
        $member = $this->memberForPlan($request, $plan);
        $this->assertItemBelongsToPlan($item, $plan, $member);
        abort_unless((string) $document->item_id === (string) $item->id, 404);

        $path = DB::transaction(function () use ($request, $plan, $item, $document, $member): string {
            $lockedPlan = $this->lockPlanForMutation($plan, $member);
            $lockedItem = $this->lockItemForMutation($lockedPlan, $item);
            abort_unless($lockedItem->isEditable(), 422, 'Documents are locked in the current workflow state.');

            // Lock every document for this item in a deterministic order so two
            // concurrent TOR deletions cannot both pass the last-TOR check.
            $lockedDocuments = ThinkTankProcurementDocument::query()
                ->where('item_id', $lockedItem->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $lockedDocument = $lockedDocuments->first(
                fn (ThinkTankProcurementDocument $candidate): bool => (string) $candidate->id === (string) $document->id
            );
            abort_unless($lockedDocument, 404);

            $safePath = $this->procurementDocumentPath($lockedPlan, $lockedItem, $lockedDocument);
            if ($lockedDocument->document_type === 'tor'
                && $lockedDocuments->where('document_type', 'tor')->count() <= 1) {
                throw ValidationException::withMessages([
                    'document' => 'Upload a replacement TOR before removing the current one.',
                ]);
            }

            $documentName = $lockedDocument->document_name;
            $lockedDocument->delete();
            $this->workflow->event(
                $lockedPlan,
                $lockedItem,
                $request->user(),
                'item_document_removed',
                $lockedItem->status,
                $lockedItem->status,
                null,
                ['document_name' => $documentName]
            );

            return $safePath;
        });
        Storage::disk('local')->delete($path);

        return back()->with('success', 'Document removed.');
    }

    public function downloadDocument(
        Request $request,
        ThinkTankProcurementPlan $plan,
        ThinkTankProcurementItem $item,
        ThinkTankProcurementDocument $document
    ) {
        $member = $this->memberForPlan($request, $plan);
        $this->assertItemBelongsToPlan($item, $plan, $member);
        abort_unless((string) $document->item_id === (string) $item->id, 404);
        $path = $this->procurementDocumentPath($plan, $item, $document);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404, 'Document file not found.');

        $diskRoot = realpath($disk->path(''));
        $absolutePath = realpath($disk->path($path));
        abort_unless(
            is_string($diskRoot)
                && is_string($absolutePath)
                && is_file($absolutePath)
                && $this->pathIsInside($absolutePath, $diskRoot),
            404,
            'Document file not found.'
        );

        $response = response()->download(
            $absolutePath,
            $this->safeDocumentDownloadName($document),
            [
                'Content-Type' => 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
            ],
            'attachment'
        );
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->addCacheControlDirective('no-cache');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    public function submit(Request $request, ThinkTankProcurementPlan $plan)
    {
        $member = $this->memberForPlan($request, $plan);
        $this->assertPlanBelongsToMember($plan, $member);
        $this->workflow->submit($plan, $request->user());

        return back()->with('success', 'Annual procurement plan submitted to the ATTP Procurement Officer for review.');
    }

    public function launch(Request $request, ThinkTankProcurementPlan $plan, ThinkTankProcurementItem $item)
    {
        abort(
            410,
            'Direct one-step publication has been retired. Open Procurement Execution in the Think Tank portal to create a controlled draft, configure its application form and publish it after the readiness checks pass.'
        );

        $member = $this->memberForPlan($request, $plan);
        $this->assertItemBelongsToPlan($item, $plan, $member);
        abort_unless($item->status === ThinkTankProcurementItem::STATUS_NO_OBJECTION, 422, 'World Bank no-objection is required before publication.');
        abort_if($item->procurement_id, 422, 'This item already has an execution opportunity.');

        $data = $request->validate([
            'application_start_date' => 'required|date',
            'application_end_date' => 'required|date|after_or_equal:application_start_date',
            'visibility_type' => 'required|in:public',
            'publish_now' => 'required|accepted',
            'cover_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'fields' => 'nullable|array|max:30',
            'fields.*.label' => 'required_with:fields|string|max:255',
            'fields.*.type' => ['required_with:fields', Rule::in($this->applicationFieldTypes())],
            'fields.*.required' => 'nullable|boolean',
            'fields.*.options' => 'nullable|string|max:2000',
            'fields.*.help_text' => 'nullable|string|max:500',
            'fields.*.placeholder' => 'nullable|string|max:255',
            'fields.*.min' => 'nullable|numeric',
            'fields.*.max' => 'nullable|numeric',
            'fields.*.max_length' => 'nullable|integer|min:1|max:20000',
            'fields.*.allowed_extensions' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9, .]+$/'],
            'fields.*.max_file_size_mb' => 'nullable|integer|min:1|max:20',
        ]);

        foreach (array_values($data['fields'] ?? []) as $index => $field) {
            if (in_array(($field['type'] ?? null), ['select', 'radio', 'multiselect', 'checkbox'], true)
                && count($this->applicationFieldOptions($field['options'] ?? '')) < 2) {
                throw ValidationException::withMessages([
                    "fields.{$index}.options" => 'Enter at least two choices, one per line or separated by commas.',
                ]);
            }

            if (isset($field['min'], $field['max']) && (float) $field['min'] > (float) $field['max']) {
                throw ValidationException::withMessages([
                    "fields.{$index}.max" => 'The maximum value must be greater than or equal to the minimum value.',
                ]);
            }
        }

        $storedProcurementPaths = [];
        $storedCoverImagePath = null;
        try {
            if ($request->hasFile('cover_image')) {
                $cover = $request->file('cover_image');
                $extension = strtolower($cover->getClientOriginalExtension() ?: 'jpg');
                $storedCoverImagePath = $cover->storeAs(
                    "procurement-covers/{$member->id}",
                    Str::uuid().'.'.$extension,
                    'public'
                );
            }

            $procurement = DB::transaction(function () use ($request, $plan, $item, $member, $data, &$storedProcurementPaths, $storedCoverImagePath): Procurement {
                $lockedPlan = $this->lockPlanForMutation($plan, $member);
                $lockedItem = $this->lockItemForMutation($lockedPlan, $item);
                abort_unless(
                    $lockedItem->status === ThinkTankProcurementItem::STATUS_NO_OBJECTION,
                    422,
                    'World Bank no-objection is required before publication.'
                );
                abort_if($lockedItem->procurement_id, 422, 'This item already has an execution opportunity.');
                $lockedItem->load('documents');

                $procurement = Procurement::create([
                    'consortium_id' => $member->consortium_id,
                    'think_tank_member_id' => $member->id,
                    'think_tank_procurement_plan_id' => $lockedPlan->id,
                    'procurement_owner_type' => 'think_tank',
                    'oversight_status' => 'no_objection_obtained',
                    'title' => $lockedItem->title,
                    'reference_no' => $lockedItem->item_code,
                    'description' => $lockedItem->description ?: $lockedItem->title,
                    'fiscal_year' => preg_match('/\d{4}/', (string) $lockedPlan->fiscal_year, $yearMatch)
                        ? (int) $yearMatch[0]
                        : (int) now()->format('Y'),
                    'estimated_budget' => $lockedItem->estimated_amount,
                    'application_start_date' => $data['application_start_date'],
                    'application_end_date' => $data['application_end_date'],
                    'status' => 'published',
                    'visibility_type' => 'public',
                    'cover_image_path' => $storedCoverImagePath,
                    'created_by' => $request->user()->id,
                ]);

                $form = DynamicForm::create([
                    'name' => $lockedItem->title.' Application Form',
                    'applies_to' => 'procurement',
                    'status' => 'approved',
                    'is_active' => true,
                    'procurement_id' => $procurement->id,
                    'created_by' => $request->user()->id,
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                ]);

                foreach ($this->defaultApplicationFields() as $field) {
                    DynamicFormField::updateOrCreate(
                        ['form_id' => $form->id, 'field_key' => $field['field_key']],
                        [...$field, 'created_by' => $request->user()->id]
                    );
                }
                foreach (array_values($data['fields'] ?? []) as $index => $field) {
                    $label = trim($field['label']);
                    if ($label === '') {
                        continue;
                    }
                    DynamicFormField::create([
                        'form_id' => $form->id,
                        'label' => $label,
                        'field_key' => 'custom_'.Str::slug($label, '_').'_'.($index + 1),
                        'field_type' => $field['type'],
                        'is_required' => (bool) ($field['required'] ?? false),
                        'options' => ($options = $this->applicationFieldOptions($field['options'] ?? ''))
                            ? implode("\n", $options)
                            : null,
                        'help_text' => trim((string) ($field['help_text'] ?? '')) ?: null,
                        'placeholder' => trim((string) ($field['placeholder'] ?? '')) ?: null,
                        'validation_rules' => $this->applicationFieldValidation($field),
                        'sort_order' => 100 + $index,
                        'created_by' => $request->user()->id,
                    ]);
                }

                foreach ($lockedItem->documents->whereIn('document_type', ['tor', 'supporting']) as $sourceDocument) {
                    $extension = pathinfo($sourceDocument->original_name, PATHINFO_EXTENSION);
                    $targetPath = "procurements/{$procurement->id}/documents/".Str::uuid().($extension ? '.'.$extension : '');
                    if (! Storage::disk('local')->copy($sourceDocument->file_path, $targetPath)) {
                        throw new \RuntimeException('A planning document could not be copied to the execution record.');
                    }
                    $storedProcurementPaths[] = $targetPath;
                    ProcurementDocument::create([
                        'procurement_id' => $procurement->id,
                        'document_name' => $sourceDocument->document_name,
                        'original_name' => $sourceDocument->original_name,
                        'file_path' => $targetPath,
                        'mime_type' => $sourceDocument->mime_type,
                        'file_size' => $sourceDocument->file_size,
                        'audience' => $sourceDocument->document_type === 'tor'
                            ? ProcurementDocument::AUDIENCE_BIDDER
                            : ProcurementDocument::AUDIENCE_INTERNAL,
                        'uploaded_by' => $request->user()->id,
                    ]);
                }

                $lockedItem->update([
                    'procurement_id' => $procurement->id,
                    'status' => ThinkTankProcurementItem::STATUS_PUBLISHED,
                    'source_activity_status' => ThinkTankProcurementItem::ACTIVITY_STATUS_WORLD_BANK_APPROVED,
                    'updated_by' => $request->user()->id,
                ]);
                $this->workflow->event($lockedPlan, $lockedItem, $request->user(), 'item_execution_created', ThinkTankProcurementItem::STATUS_NO_OBJECTION, $lockedItem->status, null, [
                    'procurement_id' => $procurement->id,
                    'published' => $procurement->status === 'published',
                ]);

                return $procurement;
            });
        } catch (Throwable $exception) {
            foreach ($storedProcurementPaths as $path) {
                Storage::disk('local')->delete($path);
            }
            if ($storedCoverImagePath) {
                Storage::disk('public')->delete($storedCoverImagePath);
            }
            if ($exception instanceof HttpExceptionInterface) {
                throw $exception;
            }
            report($exception);

            return back()->withInput()->with('error', 'Execution setup could not be completed. No copied file was retained.');
        }

        return back()->with('success', 'Procurement opportunity published. Applicants will automatically receive vendor portal access.');
    }

    public function recallPublication(
        Request $request,
        ThinkTankProcurementPlan $plan,
        ThinkTankProcurementItem $item,
        EvaluationReworkGuard $reworkGuard
    ) {
        $member = $this->memberForPlan($request, $plan);
        $this->assertItemBelongsToPlan($item, $plan, $member);
        $procurement = $item->procurement()->firstOrFail();

        $data = $request->validate([
            'recall_reason' => 'required|string|min:10|max:2000',
        ]);

        DB::transaction(function () use ($request, $plan, $item, $member, $procurement, $data, $reworkGuard): void {
            $lockedPlan = $this->lockPlanForMutation($plan, $member);
            $lockedItem = $this->lockItemForMutation($lockedPlan, $item);
            abort_unless(
                $lockedItem->status === ThinkTankProcurementItem::STATUS_PUBLISHED
                    && (string) $lockedItem->procurement_id === (string) $procurement->id,
                422,
                'This procurement item is no longer linked to the published opportunity.'
            );

            $lockedProcurement = $reworkGuard->lockAndAssertNoPendingRework(
                $lockedItem->procurement_id,
                'Complete or resolve all pending evaluation rework before recalling this procurement publication.'
            );

            abort_unless($lockedProcurement->status === 'published', 422, 'Only a published procurement opportunity can be recalled.');

            $lockedProcurement->update([
                'status' => 'recalled',
                'recalled_at' => now(),
                'recalled_by' => $request->user()->id,
                'recall_reason' => trim($data['recall_reason']),
            ]);

            $lockedProcurement->submissions()
                ->where('status', '!=', FormSubmission::STATUS_WITHDRAWN)
                ->update([
                    'status' => FormSubmission::STATUS_REVISION_REQUESTED,
                    'vendor_response' => null,
                    'updated_at' => now(),
                ]);

            $this->workflow->event(
                $lockedPlan,
                $lockedItem,
                $request->user(),
                'item_publication_recalled',
                'published',
                'recalled',
                trim($data['recall_reason']),
                [
                    'procurement_id' => $lockedProcurement->id,
                    'application_count' => $lockedProcurement->submissions()->count(),
                    'publication_version' => $lockedProcurement->publication_version,
                ]
            );
        });

        $notified = $this->queueVendorPublicationNotifications($procurement->fresh(), 'recalled', true);

        return back()->with(
            'success',
            'Procurement opportunity recalled. '.($notified > 0
                ? "{$notified} applicant notification(s) were queued for email delivery."
                : 'There were no active applicants to notify.')
        );
    }

    public function republishPublication(Request $request, ThinkTankProcurementPlan $plan, ThinkTankProcurementItem $item)
    {
        $member = $this->memberForPlan($request, $plan);
        $this->assertItemBelongsToPlan($item, $plan, $member);
        $procurement = $item->procurement()->firstOrFail();

        $data = $request->validate([
            'application_start_date' => 'required|date|after_or_equal:today',
            'application_end_date' => 'required|date|after_or_equal:application_start_date',
        ]);

        DB::transaction(function () use ($request, $plan, $item, $member, $procurement, $data): void {
            $lockedPlan = $this->lockPlanForMutation($plan, $member);
            $lockedItem = $this->lockItemForMutation($lockedPlan, $item);
            abort_unless(
                $lockedItem->status === ThinkTankProcurementItem::STATUS_PUBLISHED
                    && (string) $lockedItem->procurement_id === (string) $procurement->id,
                422,
                'This procurement item is no longer linked to the recalled opportunity.'
            );
            $lockedProcurement = Procurement::query()
                ->withTrashed()
                ->whereKey($lockedItem->procurement_id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedProcurement->status === 'recalled', 422, 'Only a recalled procurement opportunity can be republished.');

            $fromVersion = max(1, (int) $lockedProcurement->publication_version);
            $lockedProcurement->update([
                'status' => 'published',
                'application_start_date' => $data['application_start_date'],
                'application_end_date' => $data['application_end_date'],
                'publication_version' => $fromVersion + 1,
                'republished_at' => now(),
            ]);

            $this->workflow->event(
                $lockedPlan,
                $lockedItem,
                $request->user(),
                'item_publication_republished',
                'recalled',
                'published',
                $lockedProcurement->recall_reason,
                [
                    'procurement_id' => $lockedProcurement->id,
                    'previous_publication_version' => $fromVersion,
                    'publication_version' => $fromVersion + 1,
                    'application_start_date' => $data['application_start_date'],
                    'application_end_date' => $data['application_end_date'],
                ]
            );
        });

        $notified = $this->queueVendorPublicationNotifications($procurement->fresh(), 'republished');

        return back()->with(
            'success',
            'Procurement opportunity republished. '.($notified > 0
                ? "{$notified} previous applicant notification(s) were queued for email delivery."
                : 'It is now visible to new applicants.')
        );
    }

    public function storeEvaluation(Request $request, ThinkTankProcurementPlan $plan, ThinkTankProcurementItem $item)
    {
        $member = $this->memberForPlan($request, $plan);
        $this->assertItemBelongsToPlan($item, $plan, $member);
        abort_unless($item->procurement_id, 422, 'Publish or configure the procurement opportunity before creating evaluations.');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'evaluation_phase' => 'required|in:technical,financial',
            'description' => 'nullable|string|max:5000',
            'criteria' => 'required|array|min:1|max:30',
            'criteria.*.name' => 'required|string|max:255',
            'criteria.*.description' => 'nullable|string|max:1000',
            'criteria.*.max_score' => 'required|numeric|min:0.01|max:100',
        ]);

        $total = collect($data['criteria'])->sum(fn ($criterion) => (float) $criterion['max_score']);
        if (abs($total - 100) > 0.001) {
            throw ValidationException::withMessages(['criteria' => 'Evaluation criteria must total exactly 100 points. Current total: '.$total.'.']);
        }

        $evaluation = DB::transaction(function () use ($request, $item, $member, $data): Evaluation {
            $evaluation = Evaluation::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => 'active',
                'type' => 'services',
                'think_tank_member_id' => $member->id,
                'evaluation_phase' => $data['evaluation_phase'],
                'procurement_id' => $item->procurement_id,
                'is_portfolio_custom' => false,
                'created_by' => $request->user()->id,
            ]);
            $section = EvaluationSection::create([
                'evaluation_id' => $evaluation->id,
                'name' => Str::headline($data['evaluation_phase']).' evaluation',
                'description' => $data['description'] ?? null,
            ]);
            foreach ($data['criteria'] as $criterion) {
                EvaluationCriteria::create([
                    'evaluation_section_id' => $section->id,
                    'name' => $criterion['name'],
                    'description' => $criterion['description'] ?? null,
                    'max_score' => $criterion['max_score'],
                ]);
            }
            $this->workflow->event($item->plan, $item, $request->user(), 'evaluation_template_created', $item->status, $item->status, null, [
                'evaluation_id' => $evaluation->id,
                'phase' => $evaluation->evaluation_phase,
            ]);

            return $evaluation;
        });

        return back()->with('success', Str::headline($evaluation->evaluation_phase).' evaluation template created.');
    }

    public function assignEvaluation(Request $request, ThinkTankProcurementPlan $plan, ThinkTankProcurementItem $item, Evaluation $evaluation)
    {
        $member = $this->memberForPlan($request, $plan);
        $this->assertItemBelongsToPlan($item, $plan, $member);
        abort_unless((string) $evaluation->think_tank_member_id === (string) $member->id, 404);
        abort_unless($item->procurement_id, 422);

        $data = $request->validate([
            'evaluator_ids' => 'required|array|min:1',
            'evaluator_ids.*' => [
                'required',
                'uuid',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('user_type', 'think_tank')
                    ->where('is_disabled', false)
                    ->where(function ($nested) use ($member): void {
                        $nested->where('think_tank_member_id', $member->id)
                            ->orWhere('id', $member->portal_user_id);
                    })),
            ],
        ]);

        $created = 0;
        foreach (array_unique($data['evaluator_ids']) as $userId) {
            $assignment = EvaluationAssignment::firstOrCreate([
                'evaluation_id' => $evaluation->id,
                'procurement_id' => $item->procurement_id,
                'user_id' => $userId,
                'form_submission_id' => null,
            ], [
                'assigned_by' => $request->user()->id,
                'assigned_at' => now(),
                'status' => 'assigned',
            ]);
            if (! $assignment->wasRecentlyCreated) {
                continue;
            }
            $created++;
            $evaluator = User::find($userId);
            if ($evaluator?->email) {
                try {
                    Mail::to($evaluator->email)->send(new EvaluationAssigned($evaluator, $evaluation, $item->procurement));
                } catch (Throwable $exception) {
                    logger()->warning('Think Tank evaluator assignment email failed.', [
                        'assignment_id' => $assignment->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }

        $this->workflow->event($plan, $item, $request->user(), 'evaluation_team_assigned', $item->status, $item->status, null, [
            'evaluation_id' => $evaluation->id,
            'new_assignments' => $created,
        ]);

        return back()->with('success', $created.' evaluation team member assignment(s) created.');
    }

    private function validateItem(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'source_reference' => 'nullable|string|max:255',
            'loan_credit_no' => 'nullable|string|max:255',
            'component' => 'nullable|string|max:5000',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:10000',
            'procurement_category' => 'required|in:goods,works,consulting_services,non_consulting_services,training,other',
            'procurement_method' => 'required|string|max:120',
            'market_approach' => 'required|string|max:80',
            'review_type' => 'required|string|max:80',
            'source_sea_sh_risk' => 'nullable|string|max:30',
            'source_document_type' => 'required|string|max:255',
            'source_process_status' => 'required|string|max:255',
            'quantity' => 'required|numeric|min:0.0001',
            'unit' => 'nullable|string|max:60',
            'estimated_unit_cost' => 'required|numeric|min:0.01',
            'estimated_amount' => 'nullable|numeric|min:0',
            'currency' => 'required|string|max:10',
            'planned_quarter' => 'nullable|string|max:30',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
            'tor' => [$creating ? 'required' : 'nullable', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
            'supporting_documents' => 'nullable|array|max:20',
            'supporting_documents.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,jpg,jpeg,png,zip|max:20480',
        ], [
            'tor.required' => 'Attach the Terms of Reference before adding this procurement item.',
            'tor.mimes' => 'The Terms of Reference must be a PDF or Word document.',
        ]);

        $data['estimated_amount'] = round(
            (float) $data['quantity'] * (float) $data['estimated_unit_cost'],
            2
        );

        return $data;
    }

    private function itemAttributes(array $data): array
    {
        return collect($data)->only([
            'source_reference', 'loan_credit_no', 'component', 'title', 'description',
            'procurement_category', 'procurement_method', 'market_approach', 'review_type',
            'source_sea_sh_risk', 'source_document_type', 'source_process_status',
            'quantity', 'unit', 'estimated_unit_cost', 'estimated_amount', 'currency',
            'planned_quarter', 'planned_start_date', 'planned_end_date',
        ])->all();
    }

    private function storeItemDocuments(Request $request, ThinkTankProcurementItem $item, array &$storedPaths): void
    {
        $files = [];
        if ($request->hasFile('tor')) {
            $files[] = ['type' => 'tor', 'name' => 'Terms of Reference', 'file' => $request->file('tor')];
        }
        foreach ($request->file('supporting_documents', []) as $file) {
            $files[] = ['type' => 'supporting', 'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 'file' => $file];
        }

        foreach ($files as $entry) {
            $file = $entry['file'];
            $path = $file->store("think-tank-procurement/{$item->plan_id}/{$item->id}", 'local');
            if (! $path) {
                throw new \RuntimeException('A procurement item document could not be stored.');
            }
            $storedPaths[] = $path;
            $item->documents()->create([
                'document_type' => $entry['type'],
                'document_name' => $entry['name'],
                'original_name' => basename($file->getClientOriginalName()),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => (int) $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }
    }

    /**
     * Lock the current tenant-owned plan before any lifecycle mutation.
     */
    private function lockPlanForMutation(
        ThinkTankProcurementPlan $plan,
        ConsortiumThinkTank $member
    ): ThinkTankProcurementPlan {
        return ThinkTankProcurementPlan::query()
            ->whereKey($plan->getKey())
            ->where('think_tank_member_id', $member->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Lock an item only through its already-locked parent plan.
     */
    private function lockItemForMutation(
        ThinkTankProcurementPlan $plan,
        ThinkTankProcurementItem $item
    ): ThinkTankProcurementItem {
        $lockedItem = ThinkTankProcurementItem::query()
            ->where('plan_id', $plan->getKey())
            ->whereKey($item->getKey())
            ->lockForUpdate()
            ->firstOrFail();
        $lockedItem->setRelation('plan', $plan);

        return $lockedItem;
    }

    /**
     * Accept only canonical private paths inside this exact plan/item folder.
     */
    private function procurementDocumentPath(
        ThinkTankProcurementPlan $plan,
        ThinkTankProcurementItem $item,
        ThinkTankProcurementDocument $document
    ): string {
        $path = str_replace('\\', '/', trim((string) $document->file_path));
        $decodedPath = rawurldecode($path);
        $segments = explode('/', $path);
        $expectedPrefix = "think-tank-procurement/{$plan->id}/{$item->id}/";

        abort_unless(
            $path !== ''
                && $decodedPath === $path
                && ! str_contains($path, "\0")
                && ! str_starts_with($path, '/')
                && preg_match('/^[a-zA-Z]:\//', $path) !== 1
                && ! str_contains($path, '://')
                && ! collect($segments)->contains(
                    fn (string $segment): bool => $segment === '' || $segment === '.' || $segment === '..'
                )
                && str_starts_with($path, $expectedPrefix)
                && strlen($path) > strlen($expectedPrefix),
            404,
            'Document file not found.'
        );

        return $path;
    }

    private function pathIsInside(string $candidate, string $root): bool
    {
        $candidate = str_replace('\\', '/', $candidate);
        $root = rtrim(str_replace('\\', '/', $root), '/');
        if (PHP_OS_FAMILY === 'Windows') {
            $candidate = Str::lower($candidate);
            $root = Str::lower($root);
        }

        return str_starts_with($candidate, $root.'/');
    }

    private function safeDocumentDownloadName(ThinkTankProcurementDocument $document): string
    {
        $name = basename(str_replace('\\', '/', trim((string) $document->original_name)));
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? '';
        $name = trim($name, " .\t\n\r\0\x0B");
        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'procurement-document';
        }

        return mb_substr($name, 0, 180);
    }

    private function defaultApplicationFields(): array
    {
        return [
            ['label' => 'Organization Profile', 'field_key' => 'organization_profile', 'field_type' => 'file', 'is_required' => true, 'help_text' => 'Upload a PDF or Microsoft Office document (maximum 20 MB).', 'validation_rules' => ['allowed_extensions' => ['pdf', 'doc', 'docx'], 'max_file_size_mb' => 20], 'sort_order' => 10],
            ['label' => 'Technical Proposal', 'field_key' => 'technical_proposal', 'field_type' => 'file', 'is_required' => true, 'help_text' => 'Upload the complete technical proposal (maximum 20 MB).', 'validation_rules' => ['allowed_extensions' => ['pdf', 'doc', 'docx'], 'max_file_size_mb' => 20], 'sort_order' => 20],
            ['label' => 'Financial Proposal', 'field_key' => 'financial_proposal', 'field_type' => 'file', 'is_required' => true, 'help_text' => 'Upload the complete financial proposal (maximum 20 MB).', 'validation_rules' => ['allowed_extensions' => ['pdf', 'xls', 'xlsx'], 'max_file_size_mb' => 20], 'sort_order' => 30],
            ['label' => 'Quoted Amount', 'field_key' => 'quoted_amount', 'field_type' => 'number', 'is_required' => true, 'placeholder' => 'Enter the total quoted amount', 'validation_rules' => ['min' => 0], 'sort_order' => 40],
            ['label' => 'Relevant Experience', 'field_key' => 'relevant_experience', 'field_type' => 'textarea', 'is_required' => true, 'placeholder' => 'Summarize assignments relevant to this opportunity', 'validation_rules' => ['max_length' => 10000], 'sort_order' => 50],
        ];
    }

    private function queueVendorPublicationNotifications(
        Procurement $procurement,
        string $event,
        bool $onlyActive = false
    ): int {
        return app(ProcurementPublicationNotificationService::class)
            ->queue($procurement, $event, $onlyActive);
    }

    private function applicationFieldTypes(): array
    {
        return [
            'text', 'textarea', 'email', 'tel', 'number', 'url', 'date', 'time',
            'datetime-local', 'select', 'radio', 'multiselect', 'checkbox',
            'boolean', 'file', 'image',
        ];
    }

    private function applicationFieldOptions(?string $options): array
    {
        return collect(preg_split('/[\r\n,]+/', (string) $options))
            ->map(fn ($option) => trim((string) $option))
            ->filter()
            ->unique()
            ->take(50)
            ->values()
            ->all();
    }

    private function applicationFieldValidation(array $field): ?array
    {
        $rules = [];
        $type = (string) ($field['type'] ?? 'text');

        if ($type === 'number') {
            if (isset($field['min']) && $field['min'] !== '') {
                $rules['min'] = (float) $field['min'];
            }
            if (isset($field['max']) && $field['max'] !== '') {
                $rules['max'] = (float) $field['max'];
            }
        }

        if (in_array($type, ['text', 'textarea', 'email', 'tel', 'url'], true)
            && isset($field['max_length']) && $field['max_length'] !== '') {
            $rules['max_length'] = (int) $field['max_length'];
        }

        if (in_array($type, ['file', 'image'], true)) {
            $allowed = $type === 'image'
                ? ['jpg', 'jpeg', 'png', 'webp']
                : ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt', 'zip'];
            $requested = collect(preg_split('/[\s,]+/', strtolower((string) ($field['allowed_extensions'] ?? ''))))
                ->map(fn ($extension) => ltrim(trim((string) $extension), '.'))
                ->filter()
                ->intersect($allowed)
                ->unique()
                ->values()
                ->all();
            $rules['allowed_extensions'] = $requested ?: $allowed;
            $rules['max_file_size_mb'] = (int) ($field['max_file_size_mb'] ?? ($type === 'image' ? 5 : 10));
        }

        return $rules ?: null;
    }

    private function member(Request $request): ConsortiumThinkTank
    {
        $user = $request->user();
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return ConsortiumThinkTank::with('consortium')
                ->when($request->input('think_tank_member_id'), fn ($query, $memberId) => $query->whereKey($memberId))
                ->orderBy('name')
                ->firstOrFail();
        }

        $member = $user->resolvedThinkTankMembership();
        abort_unless($member, 403, 'This account is not linked to a think tank.');

        return $member->loadMissing('consortium');
    }

    private function memberForPlan(Request $request, ThinkTankProcurementPlan $plan): ConsortiumThinkTank
    {
        $user = $request->user();
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return $plan->member()->with('consortium')->firstOrFail();
        }

        return $this->member($request);
    }

    private function assertPlanBelongsToMember(ThinkTankProcurementPlan $plan, ConsortiumThinkTank $member): void
    {
        abort_unless((string) $plan->think_tank_member_id === (string) $member->id, 404);
    }

    private function assertItemBelongsToPlan(
        ThinkTankProcurementItem $item,
        ThinkTankProcurementPlan $plan,
        ConsortiumThinkTank $member
    ): void {
        $this->assertPlanBelongsToMember($plan, $member);
        abort_unless((string) $item->plan_id === (string) $plan->id, 404);
    }

    private function portalRouteParams(Request $request, ConsortiumThinkTank $member): array
    {
        return ($request->user()->isSuperAdmin() || $request->user()->isAdmin())
            ? ['think_tank_member_id' => $member->id]
            : [];
    }
}
