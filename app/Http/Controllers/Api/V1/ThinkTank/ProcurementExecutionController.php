<?php

namespace App\Http\Controllers\Api\V1\ThinkTank;

use App\Exceptions\ThinkTankApiException;
use App\Http\Controllers\Controller;
use App\Models\ConsortiumThinkTank;
use App\Models\DynamicForm;
use App\Models\DynamicFormField;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\SystemAuditLog;
use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use App\Services\EvaluationReworkGuard;
use App\Services\ProcurementPublicationNotificationService;
use App\Services\ProcurementRichTextService;
use App\Services\ThinkTankProcurementApiService;
use App\Services\ThinkTankProcurementExecutionService;
use App\Services\ThinkTankProcurementWorkflowService;
use App\Services\ThinkTankVendorDirectoryService;
use App\Support\DynamicProcurementFormCatalog;
use App\Support\ThinkTankApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ProcurementExecutionController extends Controller
{
    private const MAX_FILES_PER_REQUEST = 20;

    private const MAX_BYTES_PER_REQUEST = 60 * 1024 * 1024;

    public function __construct(
        private readonly ThinkTankProcurementExecutionService $execution,
        private readonly ThinkTankProcurementApiService $planning,
        private readonly ThinkTankProcurementWorkflowService $workflow,
        private readonly ProcurementPublicationNotificationService $publicationNotifications,
        private readonly ThinkTankVendorDirectoryService $vendorDirectory,
        private readonly ProcurementRichTextService $richText,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $this->rejectUnexpectedQuery($request, []);
        $member = $this->member($request);
        $executions = $this->executionQuery($member)
            ->with(['thinkTankPlanningItem.plan', 'forms.fields', 'documents'])
            ->latest('updated_at')
            ->get();
        $eligible = $this->eligibleQuery($member)
            ->with(['plan', 'noObjectionRecorder:id,name'])
            ->latest('no_objection_recorded_at')
            ->get();

        return ThinkTankApiResponse::success([
            'permissions' => $this->permissions($request),
            'summary' => [
                'eligible' => $eligible->count(),
                'draft' => $executions->where('status', 'draft')->count(),
                'published' => $executions->where('status', 'published')->count(),
                'recalled' => $executions->where('status', 'recalled')->count(),
                'closed' => $executions->where('status', 'closed')->count(),
                'total' => $executions->count(),
            ],
            'eligibleItems' => $eligible->take(5)
                ->map(fn (ThinkTankProcurementItem $item): array => $this->execution->eligibleItem($item))
                ->values()
                ->all(),
            'recentExecutions' => $executions->take(5)
                ->map(fn (Procurement $procurement): array => $this->execution->executionCard($procurement, $request->user()))
                ->values()
                ->all(),
            'methodTemplates' => $this->execution->methodTemplates(),
            'tenantVendorCategoryOptions' => $this->vendorDirectory->payload($member)['categories'],
            'tenantVendorOptions' => $this->vendorDirectory->payload($member)['vendors'],
        ]);
    }

    public function eligibleItems(Request $request): JsonResponse
    {
        $this->rejectUnexpectedQuery($request, ['q', 'method', 'fiscal_year', 'item_id', 'page']);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'method' => ['nullable', 'string', Rule::in($this->methodCodes())],
            'fiscal_year' => ['nullable', 'string', 'max:20'],
            'item_id' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $member = $this->member($request);
        $query = $this->eligibleQuery($member)->with(['plan', 'noObjectionRecorder:id,name']);
        $search = Str::lower(trim((string) ($filters['q'] ?? '')));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->whereRaw('LOWER(title) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(item_code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(no_objection_reference, ?)) LIKE ?', ['', $like]);
            });
        }
        if (filled($filters['fiscal_year'] ?? null)) {
            $query->whereHas('plan', fn ($plans) => $plans->where('fiscal_year', $filters['fiscal_year']));
        }
        if (filled($filters['item_id'] ?? null)) {
            $query->whereKey($filters['item_id']);
        }
        $this->applyMethodFilter($query, $member, (string) ($filters['method'] ?? ''), true);

        $allEligible = $this->eligibleQuery($member)->with('plan:id,fiscal_year')->get();
        $paginator = $query->latest('no_objection_recorded_at')->paginate(15)->withQueryString();

        return ThinkTankApiResponse::success([
            'permissions' => $this->permissions($request),
            'summary' => ['eligibleCount' => $allEligible->count()],
            'items' => $paginator->getCollection()
                ->map(fn (ThinkTankProcurementItem $item): array => $this->execution->eligibleItem($item))
                ->values()
                ->all(),
            'filters' => [
                'query' => (string) ($filters['q'] ?? ''),
                'method' => (string) ($filters['method'] ?? ''),
                'fiscalYear' => (string) ($filters['fiscal_year'] ?? ''),
                'itemId' => (string) ($filters['item_id'] ?? ''),
            ],
            'options' => [
                'methods' => $this->methodOptions(),
                'tenantVendorCategoryOptions' => $this->vendorDirectory->payload($member)['categories'],
                'tenantVendorOptions' => $this->vendorDirectory->payload($member)['vendors'],
                'fiscalYears' => $allEligible->pluck('plan.fiscal_year')->filter()->unique()->sortDesc()->values()
                    ->map(fn ($year): array => ['value' => (string) $year, 'label' => (string) $year])->all(),
            ],
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->rejectUnexpectedQuery($request, ['q', 'status', 'method', 'fiscal_year', 'page']);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(ThinkTankProcurementExecutionService::EXECUTION_STATUSES)],
            'method' => ['nullable', 'string', Rule::in($this->methodCodes())],
            'fiscal_year' => ['nullable', 'string', 'max:20'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $member = $this->member($request);
        $base = $this->executionQuery($member);
        $all = (clone $base)->with(['thinkTankPlanningItem.plan', 'forms.fields', 'documents'])->get();
        $query = (clone $base)->with(['thinkTankPlanningItem.plan', 'forms.fields', 'documents']);
        $search = Str::lower(trim((string) ($filters['q'] ?? '')));
        $query
            ->when($search !== '', function ($builder) use ($search): void {
                $like = '%'.$search.'%';
                $builder->where(fn ($nested) => $nested
                    ->whereRaw('LOWER(title) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(reference_no, ?)) LIKE ?', ['', $like]));
            })
            ->when(filled($filters['status'] ?? null), fn ($builder) => $builder->where('status', $filters['status']))
            ->when(filled($filters['fiscal_year'] ?? null), fn ($builder) => $builder->where('fiscal_year', $filters['fiscal_year']));
        $this->applyMethodFilter($query, $member, (string) ($filters['method'] ?? ''));
        $paginator = $query->latest('updated_at')->paginate(12)->withQueryString();

        return ThinkTankApiResponse::success([
            'permissions' => $this->permissions($request),
            'summary' => [
                'eligible' => $this->eligibleQuery($member)->count(),
                'total' => $all->count(),
                'draft' => $all->where('status', 'draft')->count(),
                'published' => $all->where('status', 'published')->count(),
                'recalled' => $all->where('status', 'recalled')->count(),
                'closed' => $all->where('status', 'closed')->count(),
            ],
            'executions' => $paginator->getCollection()
                ->map(fn (Procurement $procurement): array => $this->execution->executionCard($procurement, $request->user()))
                ->values()
                ->all(),
            'filters' => [
                'query' => (string) ($filters['q'] ?? ''),
                'status' => (string) ($filters['status'] ?? ''),
                'method' => (string) ($filters['method'] ?? ''),
                'fiscalYear' => (string) ($filters['fiscal_year'] ?? ''),
            ],
            'options' => [
                'statuses' => collect(ThinkTankProcurementExecutionService::EXECUTION_STATUSES)
                    ->map(fn (string $status): array => ['value' => $status, 'label' => $this->execution->statusLabel($status)])->all(),
                'methods' => $this->methodOptions(),
                'tenantVendorCategoryOptions' => $this->vendorDirectory->payload($member)['categories'],
                'tenantVendorOptions' => $this->vendorDirectory->payload($member)['vendors'],
                'fiscalYears' => $all->pluck('fiscal_year')->filter()->unique()->sortDesc()->values()
                    ->map(fn ($year): array => ['value' => (string) $year, 'label' => (string) $year])->all(),
            ],
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        $this->rejectUnexpectedQuery($request, []);
        $member = $this->member($request);
        $executions = $this->executionQuery($member)
            ->with(['thinkTankPlanningItem.plan', 'forms.fields', 'documents'])
            ->latest('updated_at')
            ->get();

        $methodCounts = $executions->groupBy(function (Procurement $procurement): string {
            $item = $procurement->thinkTankPlanningItem;

            return $item
                ? ($this->planning->methodCode((string) $item->procurement_method, $item->procurement_category) ?: 'other')
                : 'other';
        });

        return ThinkTankApiResponse::success([
            'summary' => [
                'total' => $executions->count(),
                'estimatedBudget' => round((float) $executions->sum('estimated_budget'), 2),
                'published' => $executions->where('status', 'published')->count(),
                'submissionCount' => $executions->sum(fn (Procurement $procurement): int => $procurement->submissions()->count()),
            ],
            'statuses' => collect(ThinkTankProcurementExecutionService::EXECUTION_STATUSES)
                ->map(fn (string $status): array => [
                    'status' => $status,
                    'label' => $this->execution->statusLabel($status),
                    'count' => $executions->where('status', $status)->count(),
                ])->all(),
            'methods' => collect($this->methodOptions())->map(fn (array $method): array => [
                'code' => $method['value'],
                'label' => $method['label'],
                'count' => $methodCounts->get($method['value'], collect())->count(),
            ])->all(),
            'fiscalYears' => $executions->groupBy(fn (Procurement $procurement): string => (string) $procurement->fiscal_year)
                ->map(fn ($rows, string $year): array => ['year' => $year, 'count' => $rows->count()])
                ->sortByDesc('year')->values()->all(),
            'recentPublications' => $executions->whereIn('status', ['published', 'closed'])->take(5)
                ->map(fn (Procurement $procurement): array => $this->execution->executionCard($procurement, $request->user()))
                ->values()->all(),
        ]);
    }

    public function show(Request $request, string $execution): JsonResponse
    {
        $this->rejectUnexpectedQuery($request, []);

        return ThinkTankApiResponse::success(
            $this->execution->execution($this->ownedExecution($request, $execution), $request->user()),
        );
    }

    public function preview(Request $request, string $execution): JsonResponse
    {
        return $this->show($request, $execution);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertMultipart($request);
        $this->rejectUnexpectedBody($request, [
            'item_id', 'lock_token', 'title', 'description', 'application_start_date',
            'application_end_date', 'visibility_type', 'tenant_vendor_category_ids', 'tenant_vendor_ids',
            'cover_image', 'documents',
        ]);
        $this->assertDocumentRowsHaveOnly($request, ['name', 'audience', 'file']);
        $data = $request->validate([
            'item_id' => ['required', 'uuid'],
            'lock_token' => ['required', 'string', 'size:64'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:50000'],
            'application_start_date' => ['nullable', 'date_format:Y-m-d'],
            'application_end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:application_start_date'],
            'visibility_type' => ['nullable', Rule::in(['public', 'vendor_group'])],
            'tenant_vendor_category_ids' => ['nullable', 'array', 'max:50'],
            'tenant_vendor_category_ids.*' => ['uuid', 'distinct'],
            'tenant_vendor_ids' => ['nullable', 'array', 'max:100'],
            'tenant_vendor_ids.*' => ['uuid', 'distinct'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'documents' => ['nullable', 'array', 'max:'.self::MAX_FILES_PER_REQUEST],
            'documents.*.name' => ['required_with:documents.*.file', 'nullable', 'string', 'max:255'],
            'documents.*.audience' => ['nullable', Rule::in([ProcurementDocument::AUDIENCE_BIDDER, ProcurementDocument::AUDIENCE_INTERNAL])],
            'documents.*.file' => ['required_with:documents.*.name', 'file', 'mimes:'.implode(',', ThinkTankProcurementExecutionService::SAFE_DOCUMENT_EXTENSIONS), 'max:20480'],
        ]);
        $this->assertUploadEnvelope($request);
        $this->assertDateWindow($data['application_start_date'] ?? null, $data['application_end_date'] ?? null);
        $member = $this->member($request);
        $storedPaths = [];
        $storedCover = null;

        try {
            $procurement = DB::transaction(function () use ($request, $member, $data, &$storedPaths, &$storedCover): Procurement {
                $item = ThinkTankProcurementItem::query()
                    ->whereKey($data['item_id'])
                    ->whereHas('plan', fn ($plans) => $plans->where('think_tank_member_id', $member->id))
                    ->with(['plan', 'documents'])
                    ->lockForUpdate()
                    ->firstOrFail();
                $plan = $item->plan()->where('think_tank_member_id', $member->id)->lockForUpdate()->firstOrFail();
                $this->planning->assertLockToken($item, $data['lock_token']);
                abort_unless($plan->status === ThinkTankProcurementPlan::STATUS_APPROVED, 422, 'Only an approved annual procurement plan can enter execution.');
                abort_unless($item->status === ThinkTankProcurementItem::STATUS_NO_OBJECTION, 422, 'Only an item with World Bank no-objection can enter procurement execution.');
                abort_if($item->procurement_id, 422, 'This planning item already has a procurement execution record.');
                abort_unless($item->no_objection_date && ($item->no_objection_reference || $item->documents->contains('document_type', 'no_objection')), 422, 'The no-objection decision date and its reference or evidence document are required.');

                $visibility = (string) ($data['visibility_type'] ?? 'public');
                $targets = $visibility === 'vendor_group'
                    ? $this->vendorDirectory->validateTargets(
                        $member,
                        (array) ($data['tenant_vendor_category_ids'] ?? []),
                        (array) ($data['tenant_vendor_ids'] ?? []),
                        true,
                    )
                    : ['categoryIds' => [], 'vendorIds' => [], 'categoryNames' => []];
                if ($request->hasFile('cover_image')) {
                    $storedCover = $this->storeCover($request, $member);
                }

                $procurement = Procurement::query()->create([
                    'consortium_id' => $member->consortium_id,
                    'think_tank_member_id' => $member->id,
                    'think_tank_procurement_plan_id' => $plan->id,
                    'procurement_owner_type' => 'think_tank',
                    'oversight_status' => 'no_objection_obtained',
                    'title' => trim((string) ($data['title'] ?? '')) ?: $item->title,
                    'reference_no' => $item->item_code,
                    'description' => $this->richText->sanitizeForStorage($data['description'] ?? null)
                        ?: $this->richText->sanitizeForStorage($item->description ?: $item->title),
                    'fiscal_year' => $this->fiscalYearNumber((string) $plan->fiscal_year),
                    'estimated_budget' => $item->estimated_amount,
                    'application_start_date' => $data['application_start_date'] ?? null,
                    'application_end_date' => $data['application_end_date'] ?? null,
                    'status' => 'draft',
                    'visibility_type' => $visibility,
                    'vendor_categories' => $targets['categoryNames'] ?: null,
                    'tenant_vendor_category_ids' => $targets['categoryIds'] ?: null,
                    'tenant_vendor_ids' => $targets['vendorIds'] ?: null,
                    'cover_image_path' => $storedCover,
                    'publication_version' => 1,
                    'created_by' => $request->user()->id,
                ]);

                $this->createMethodForm($procurement, $item, $request);
                $this->copyPlanningDocuments($procurement, $item, $request, $storedPaths);
                $this->persistDocuments($request, $procurement, $storedPaths);

                $item->update([
                    'procurement_id' => $procurement->id,
                    'updated_by' => $request->user()->id,
                ]);
                $this->workflow->event(
                    $plan,
                    $item,
                    $request->user(),
                    'item_execution_draft_created',
                    $item->status,
                    $item->status,
                    null,
                    ['procurement_id' => $procurement->id, 'notification_suppressed' => true],
                );

                return $procurement;
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPaths($storedPaths, $storedCover ? [$storedCover] : []);
            throw $exception;
        }

        return ThinkTankApiResponse::success(
            $this->execution->execution($this->ownedExecution($request, (string) $procurement->id), $request->user()),
            201,
            'Procurement execution draft created from the approved plan item.',
        );
    }

    public function update(Request $request, string $execution): JsonResponse
    {
        $this->assertJsonObject($request);
        $this->rejectUnexpectedBody($request, [
            'lock_token', 'title', 'description', 'application_start_date',
            'application_end_date', 'visibility_type', 'tenant_vendor_category_ids', 'tenant_vendor_ids',
        ]);
        $data = $request->validate([
            'lock_token' => ['required', 'string', 'size:64'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:50000'],
            'application_start_date' => ['nullable', 'date_format:Y-m-d'],
            'application_end_date' => ['nullable', 'date_format:Y-m-d'],
            'visibility_type' => ['required', Rule::in(['public', 'vendor_group'])],
            'tenant_vendor_category_ids' => ['nullable', 'array', 'max:50'],
            'tenant_vendor_category_ids.*' => ['uuid', 'distinct'],
            'tenant_vendor_ids' => ['nullable', 'array', 'max:100'],
            'tenant_vendor_ids.*' => ['uuid', 'distinct'],
        ]);
        $member = $this->member($request);

        DB::transaction(function () use ($request, $member, $execution, $data): void {
            $procurement = $this->lockedExecution($member, $execution);
            $this->execution->assertLockToken($procurement, $data['lock_token']);
            abort_unless($this->execution->canEdit($procurement), 422, 'Only an execution draft without submissions can be edited.');
            $this->assertDateWindow($data['application_start_date'] ?? null, $data['application_end_date'] ?? null);
            $targets = $data['visibility_type'] === 'vendor_group'
                ? $this->vendorDirectory->validateTargets(
                    $member,
                    (array) ($data['tenant_vendor_category_ids'] ?? []),
                    (array) ($data['tenant_vendor_ids'] ?? []),
                    true,
                )
                : ['categoryIds' => [], 'vendorIds' => [], 'categoryNames' => []];
            $description = $this->richText->sanitizeForStorage($data['description']);
            if ($description === null) {
                throw ValidationException::withMessages([
                    'description' => ['Enter a meaningful procurement description.'],
                ]);
            }

            $procurement->update([
                'title' => trim($data['title']),
                'description' => $description,
                'application_start_date' => $data['application_start_date'] ?? null,
                'application_end_date' => $data['application_end_date'] ?? null,
                'visibility_type' => $data['visibility_type'],
                'vendor_categories' => $targets['categoryNames'] ?: null,
                'tenant_vendor_category_ids' => $targets['categoryIds'] ?: null,
                'tenant_vendor_ids' => $targets['vendorIds'] ?: null,
            ]);
            $this->execution->bumpLockVersion($procurement);
            $this->executionEvent($procurement, $request, 'item_execution_draft_updated');
        });

        return ThinkTankApiResponse::success(
            $this->execution->execution($this->ownedExecution($request, $execution), $request->user()),
            200,
            'Procurement execution draft updated.',
        );
    }

    public function updateForm(Request $request, string $execution): JsonResponse
    {
        $this->assertJsonObject($request);
        $this->rejectUnexpectedBody($request, ['lock_token', 'name', 'fields']);
        $this->assertFormRowsHaveOnly($request);
        $data = $request->validate([
            'lock_token' => ['required', 'string', 'size:64'],
            'name' => ['required', 'string', 'max:255'],
            'fields' => ['required', 'array', 'min:1', 'max:'.DynamicProcurementFormCatalog::MAX_CUSTOM_FIELDS],
            'fields.*.key' => ['nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::in(ThinkTankProcurementExecutionService::FIELD_TYPES)],
            'fields.*.required' => ['required', 'boolean'],
            'fields.*.options' => ['nullable', 'array', 'max:'.DynamicProcurementFormCatalog::MAX_OPTIONS_PER_FIELD],
            'fields.*.options.*' => ['string', 'max:'.DynamicProcurementFormCatalog::MAX_OPTION_LENGTH],
            'fields.*.help_text' => ['nullable', 'string', 'max:1000'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.validation' => ['nullable', 'array'],
            'fields.*.validation.min' => ['nullable', 'numeric'],
            'fields.*.validation.max' => ['nullable', 'numeric'],
            'fields.*.validation.max_length' => ['nullable', 'integer', 'min:1', 'max:'.DynamicProcurementFormCatalog::MAX_TEXT_LENGTH],
            'fields.*.validation.allowed_extensions' => ['nullable', 'array', 'max:20'],
            'fields.*.validation.allowed_extensions.*' => ['string', Rule::in(ThinkTankProcurementExecutionService::SAFE_DOCUMENT_EXTENSIONS)],
            'fields.*.validation.max_file_size_mb' => ['nullable', 'integer', 'min:1', 'max:'.DynamicProcurementFormCatalog::MAX_FILE_SIZE_MB],
            'fields.*.sort_order' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);
        $fields = $this->validatedFormFields($data['fields']);
        $member = $this->member($request);

        DB::transaction(function () use ($request, $member, $execution, $data, $fields): void {
            $procurement = $this->lockedExecution($member, $execution);
            $this->execution->assertLockToken($procurement, $data['lock_token']);
            abort_unless($this->execution->canEdit($procurement), 422, 'The application form can only be changed while the execution is a draft.');
            $form = $this->lockedExecutionForm($procurement);
            abort_if($form->hasSubmissions(), 422, 'The application form cannot be changed after applications exist.');

            $form->update([
                'name' => trim($data['name']),
                'status' => 'approved',
                'is_active' => false,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);
            $form->ensureGlobalFields();
            $form->fields()->whereNotIn('field_key', DynamicForm::globalFieldKeys())->delete();
            foreach ($fields as $field) {
                DynamicFormField::query()->create([
                    'form_id' => $form->id,
                    'label' => $field['label'],
                    'field_key' => $field['key'],
                    'field_type' => $field['type'],
                    'is_required' => $field['required'],
                    'options' => DynamicFormField::encodeOptionValues($field['options']),
                    'help_text' => $field['help_text'],
                    'placeholder' => $field['placeholder'],
                    'validation_rules' => $field['validation'] ?: null,
                    'sort_order' => $field['sort_order'],
                    'created_by' => $request->user()->id,
                ]);
            }
            $this->execution->bumpLockVersion($procurement);
            $this->executionEvent($procurement, $request, 'item_execution_form_configured', [
                'form_id' => $form->id,
                'custom_field_count' => count($fields),
            ]);
        });

        return ThinkTankApiResponse::success(
            $this->execution->execution($this->ownedExecution($request, $execution), $request->user()),
            200,
            'Application form saved.',
        );
    }

    public function storeDocuments(Request $request, string $execution): JsonResponse
    {
        $this->assertMultipart($request);
        $this->rejectUnexpectedBody($request, ['lock_token', 'documents', 'cover_image']);
        $this->assertDocumentRowsHaveOnly($request, ['name', 'audience', 'file']);
        $data = $request->validate([
            'lock_token' => ['required', 'string', 'size:64'],
            'documents' => ['nullable', 'array', 'max:'.self::MAX_FILES_PER_REQUEST],
            'documents.*.name' => ['required_with:documents.*.file', 'nullable', 'string', 'max:255'],
            'documents.*.audience' => ['nullable', Rule::in([ProcurementDocument::AUDIENCE_BIDDER, ProcurementDocument::AUDIENCE_INTERNAL])],
            'documents.*.file' => ['required_with:documents.*.name', 'file', 'mimes:'.implode(',', ThinkTankProcurementExecutionService::SAFE_DOCUMENT_EXTENSIONS), 'max:20480'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        if (! $request->hasFile('cover_image') && count($request->file('documents', [])) === 0) {
            throw ValidationException::withMessages(['documents' => ['Upload at least one document or a cover image.']]);
        }
        $this->assertUploadEnvelope($request);
        $member = $this->member($request);
        $storedPaths = [];
        $newCover = null;
        $oldCover = null;

        try {
            DB::transaction(function () use ($request, $member, $execution, $data, &$storedPaths, &$newCover, &$oldCover): void {
                $procurement = $this->lockedExecution($member, $execution);
                $this->execution->assertLockToken($procurement, $data['lock_token']);
                abort_unless($this->execution->canEdit($procurement), 422, 'Documents can only be changed while the execution is a draft.');
                if ($request->hasFile('cover_image')) {
                    $oldCover = $this->verifiedCoverPath($procurement);
                    $newCover = $this->storeCover($request, $member);
                    $procurement->cover_image_path = $newCover;
                    $procurement->save();
                }
                $this->persistDocuments($request, $procurement, $storedPaths);
                $this->execution->bumpLockVersion($procurement);
                $this->executionEvent($procurement, $request, 'item_execution_documents_added', [
                    'document_count' => count($request->file('documents', [])),
                    'cover_replaced' => $newCover !== null,
                ]);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredPaths($storedPaths, $newCover ? [$newCover] : []);
            throw $exception;
        }

        if ($oldCover && $oldCover !== $newCover) {
            Storage::disk('public')->delete($oldCover);
        }

        return ThinkTankApiResponse::success(
            $this->execution->execution($this->ownedExecution($request, $execution), $request->user()),
            200,
            'Procurement files saved.',
        );
    }

    public function destroyDocument(Request $request, string $execution, string $document): JsonResponse
    {
        $this->assertJsonObject($request);
        $this->rejectUnexpectedBody($request, ['lock_token']);
        $data = $request->validate(['lock_token' => ['required', 'string', 'size:64']]);
        $member = $this->member($request);
        $deletedPath = null;

        DB::transaction(function () use ($request, $member, $execution, $document, $data, &$deletedPath): void {
            $procurement = $this->lockedExecution($member, $execution);
            $this->execution->assertLockToken($procurement, $data['lock_token']);
            abort_unless($this->execution->canEdit($procurement), 422, 'Documents can only be removed while the execution is a draft.');
            $record = ProcurementDocument::query()
                ->where('procurement_id', $procurement->id)
                ->whereKey($document)
                ->lockForUpdate()
                ->firstOrFail();
            $deletedPath = $this->verifiedDocumentPath($procurement, $record, false);
            $record->delete();
            $this->execution->bumpLockVersion($procurement);
            $this->executionEvent($procurement, $request, 'item_execution_document_removed', [
                'document_id' => (string) $record->id,
            ]);
        });

        if ($deletedPath) {
            Storage::disk('local')->delete($deletedPath);
        }

        return ThinkTankApiResponse::success(
            $this->execution->execution($this->ownedExecution($request, $execution), $request->user()),
            200,
            'Procurement document removed.',
        );
    }

    public function destroyCover(Request $request, string $execution): JsonResponse
    {
        $this->assertJsonObject($request);
        $this->rejectUnexpectedBody($request, ['lock_token']);
        $data = $request->validate(['lock_token' => ['required', 'string', 'size:64']]);
        $member = $this->member($request);
        $deletedPath = null;

        DB::transaction(function () use ($request, $member, $execution, $data, &$deletedPath): void {
            $procurement = $this->lockedExecution($member, $execution);
            $this->execution->assertLockToken($procurement, $data['lock_token']);
            abort_unless($this->execution->canEdit($procurement), 422, 'The cover image can only be removed while the execution is a draft.');
            $deletedPath = $this->verifiedCoverPath($procurement);
            $procurement->update(['cover_image_path' => null]);
            $this->execution->bumpLockVersion($procurement);
            $this->executionEvent($procurement, $request, 'item_execution_cover_removed');
        });

        if ($deletedPath) {
            Storage::disk('public')->delete($deletedPath);
        }

        return ThinkTankApiResponse::success(
            $this->execution->execution($this->ownedExecution($request, $execution), $request->user()),
            200,
            'Cover image removed.',
        );
    }

    public function document(Request $request, string $execution, string $document): BinaryFileResponse
    {
        $this->rejectUnexpectedQuery($request, []);
        $procurement = $this->ownedExecution($request, $execution);
        $record = ProcurementDocument::query()
            ->where('procurement_id', $procurement->id)
            ->whereKey($document)
            ->firstOrFail();
        $path = $this->verifiedDocumentPath($procurement, $record);
        $disk = Storage::disk('local');
        $root = realpath($disk->path(''));
        $absolute = realpath($disk->path($path));
        abort_unless(is_string($root) && is_string($absolute) && is_file($absolute) && $this->pathIsInside($absolute, $root), 404);

        try {
            SystemAuditLog::query()->create([
                'user_id' => $request->user()->id,
                'module' => 'think_tank_procurement_execution',
                'action' => 'download_execution_document',
                'action_message' => 'Downloaded procurement execution document',
                'description' => 'Authorized tenant procurement execution document download.',
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route_name' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status_code' => 200,
                'payload' => [
                    'procurement_id' => (string) $procurement->id,
                    'document_id' => (string) $record->id,
                    'audience' => (string) $record->audience,
                ],
            ]);
        } catch (Throwable) {
            // The authorized download must remain available if auxiliary audit storage is unavailable.
        }

        $response = response()->download($absolute, $this->safeFileName($record->original_name), [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], 'attachment');
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->addCacheControlDirective('no-cache');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    public function cover(Request $request, string $execution): BinaryFileResponse
    {
        $this->rejectUnexpectedQuery($request, []);
        $procurement = $this->ownedExecution($request, $execution);
        $path = $this->verifiedCoverPath($procurement);
        abort_unless($path !== null, 404);

        $disk = Storage::disk('public');
        $root = realpath($disk->path(''));
        $absolute = realpath($disk->path($path));
        abort_unless(is_string($root) && is_string($absolute) && is_file($absolute) && $this->pathIsInside($absolute, $root), 404);

        $mime = (string) ($disk->mimeType($path) ?: 'application/octet-stream');
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        $response = response()->file($absolute, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->addCacheControlDirective('no-cache');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    public function publish(Request $request, string $execution): JsonResponse
    {
        $this->assertJsonObject($request);
        $this->rejectUnexpectedBody($request, ['lock_token', 'confirmation']);
        $data = $request->validate([
            'lock_token' => ['required', 'string', 'size:64'],
            'confirmation' => ['required', 'accepted'],
        ]);
        $member = $this->member($request);

        DB::transaction(function () use ($request, $member, $execution, $data): void {
            $procurement = $this->lockedExecution($member, $execution);
            $this->execution->assertLockToken($procurement, $data['lock_token']);
            abort_unless($procurement->status === 'draft', 422, 'Only a draft procurement execution can be published.');
            $item = ThinkTankProcurementItem::query()
                ->where('procurement_id', $procurement->id)
                ->whereHas('plan', fn ($plans) => $plans->where('think_tank_member_id', $member->id))
                ->with(['plan', 'documents'])
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($item->status === ThinkTankProcurementItem::STATUS_NO_OBJECTION, 422, 'The source item is no longer ready for execution.');
            if ($procurement->visibility_type === 'vendor_group') {
                $this->vendorDirectory->validateTargets(
                    $member,
                    (array) $procurement->tenant_vendor_category_ids,
                    (array) $procurement->tenant_vendor_ids,
                    true,
                );
            }
            $procurement->load(['thinkTankPlanningItem.documents', 'forms.fields', 'documents']);
            $incomplete = collect($this->execution->publishChecklist($procurement))->where('complete', false);
            if ($incomplete->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'publication' => $incomplete->pluck('message')->values()->all(),
                ]);
            }
            $form = $this->lockedExecutionForm($procurement);
            $form->update([
                'status' => 'approved',
                'is_active' => true,
                'approved_at' => $form->approved_at ?: now(),
                'approved_by' => $form->approved_by ?: $request->user()->id,
            ]);
            $procurement->update([
                'status' => 'published',
                'publication_version' => max(1, (int) $procurement->publication_version),
                'recalled_at' => null,
                'recalled_by' => null,
                'recall_reason' => null,
            ]);
            $this->execution->bumpLockVersion($procurement);
            $previous = $item->status;
            $item->update([
                'status' => ThinkTankProcurementItem::STATUS_PUBLISHED,
                'source_activity_status' => ThinkTankProcurementItem::ACTIVITY_STATUS_WORLD_BANK_APPROVED,
                'updated_by' => $request->user()->id,
            ]);
            $this->workflow->event($item->plan, $item, $request->user(), 'item_execution_created', $previous, $item->status, null, [
                'procurement_id' => $procurement->id,
                'publication_version' => $procurement->publication_version,
            ]);
        });

        return ThinkTankApiResponse::success(
            $this->execution->execution($this->ownedExecution($request, $execution), $request->user()),
            200,
            'Procurement opportunity published for applications.',
        );
    }

    public function recall(Request $request, string $execution, EvaluationReworkGuard $reworkGuard): JsonResponse
    {
        $this->assertJsonObject($request);
        $this->rejectUnexpectedBody($request, ['lock_token', 'recall_reason']);
        $data = $request->validate([
            'lock_token' => ['required', 'string', 'size:64'],
            'recall_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $member = $this->member($request);

        DB::transaction(function () use ($request, $member, $execution, $data, $reworkGuard): void {
            $scoped = $this->executionQuery($member)->whereKey($execution)->firstOrFail();
            $this->execution->assertLockToken($scoped, $data['lock_token']);
            $procurement = $reworkGuard->lockAndAssertNoPendingRework(
                $scoped->id,
                'Complete or resolve all pending evaluation rework before recalling this procurement publication.',
            );
            abort_unless((string) $procurement->think_tank_member_id === (string) $member->id && $procurement->procurement_owner_type === 'think_tank', 404);
            $this->execution->assertLockToken($procurement, $data['lock_token']);
            abort_unless($procurement->status === 'published', 422, 'Only a published procurement opportunity can be recalled.');
            $item = ThinkTankProcurementItem::query()->where('procurement_id', $procurement->id)->with('plan')->lockForUpdate()->firstOrFail();
            $procurement->update([
                'status' => 'recalled',
                'recalled_at' => now(),
                'recalled_by' => $request->user()->id,
                'recall_reason' => trim($data['recall_reason']),
            ]);
            $this->execution->bumpLockVersion($procurement);
            $procurement->submissions()
                ->where('status', '<>', FormSubmission::STATUS_WITHDRAWN)
                ->update(['status' => FormSubmission::STATUS_REVISION_REQUESTED, 'vendor_response' => null, 'updated_at' => now()]);
            $this->workflow->event($item->plan, $item, $request->user(), 'item_publication_recalled', 'published', 'recalled', trim($data['recall_reason']), [
                'procurement_id' => $procurement->id,
                'application_count' => $procurement->submissions()->count(),
                'publication_version' => $procurement->publication_version,
            ]);
        });

        $notificationsQueued = $this->publicationNotifications
            ->queue($this->ownedExecution($request, $execution), 'recalled', true);

        return ThinkTankApiResponse::success(
            $this->execution->execution($this->ownedExecution($request, $execution), $request->user()),
            200,
            'Procurement opportunity recalled.',
            ['notificationsQueued' => $notificationsQueued],
        );
    }

    public function republish(Request $request, string $execution): JsonResponse
    {
        $this->assertJsonObject($request);
        $this->rejectUnexpectedBody($request, ['lock_token', 'application_start_date', 'application_end_date']);
        $data = $request->validate([
            'lock_token' => ['required', 'string', 'size:64'],
            'application_start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'application_end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:application_start_date'],
        ]);
        $member = $this->member($request);

        DB::transaction(function () use ($request, $member, $execution, $data): void {
            $procurement = $this->lockedExecution($member, $execution);
            $this->execution->assertLockToken($procurement, $data['lock_token']);
            abort_unless($procurement->status === 'recalled', 422, 'Only a recalled procurement opportunity can be republished.');
            if ($procurement->visibility_type === 'vendor_group') {
                $this->vendorDirectory->validateTargets(
                    $member,
                    (array) $procurement->tenant_vendor_category_ids,
                    (array) $procurement->tenant_vendor_ids,
                    true,
                );
            }
            $item = ThinkTankProcurementItem::query()->where('procurement_id', $procurement->id)->with('plan')->lockForUpdate()->firstOrFail();
            abort_unless($item->status === ThinkTankProcurementItem::STATUS_PUBLISHED, 422, 'The linked planning item is no longer in execution.');
            $fromVersion = max(1, (int) $procurement->publication_version);
            $procurement->update([
                'status' => 'published',
                'application_start_date' => $data['application_start_date'],
                'application_end_date' => $data['application_end_date'],
                'publication_version' => $fromVersion + 1,
                'republished_at' => now(),
            ]);
            $this->execution->bumpLockVersion($procurement);
            $this->workflow->event($item->plan, $item, $request->user(), 'item_publication_republished', 'recalled', 'published', $procurement->recall_reason, [
                'procurement_id' => $procurement->id,
                'previous_publication_version' => $fromVersion,
                'publication_version' => $fromVersion + 1,
                'application_start_date' => $data['application_start_date'],
                'application_end_date' => $data['application_end_date'],
            ]);
        });

        $notificationsQueued = $this->publicationNotifications
            ->queue($this->ownedExecution($request, $execution), 'republished');

        return ThinkTankApiResponse::success(
            $this->execution->execution($this->ownedExecution($request, $execution), $request->user()),
            200,
            'Procurement opportunity republished.',
            ['notificationsQueued' => $notificationsQueued],
        );
    }

    private function createMethodForm(Procurement $procurement, ThinkTankProcurementItem $item, Request $request): void
    {
        $methodCode = $this->planning->methodCode((string) $item->procurement_method, $item->procurement_category) ?: 'other';
        $form = DynamicForm::query()->create([
            'name' => $item->title.' Application Form',
            'applies_to' => 'procurement',
            'status' => 'approved',
            'is_active' => false,
            'procurement_id' => $procurement->id,
            'created_by' => $request->user()->id,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);
        foreach ($this->execution->suggestedFields($methodCode) as $field) {
            DynamicFormField::query()->create([
                'form_id' => $form->id,
                'label' => $field['label'],
                'field_key' => $field['key'],
                'field_type' => $field['type'],
                'is_required' => $field['required'],
                'options' => DynamicFormField::encodeOptionValues($field['options']),
                'help_text' => $field['help_text'],
                'placeholder' => $field['placeholder'],
                'validation_rules' => $field['validation'] ?: null,
                'sort_order' => 100 + (int) $field['sort_order'],
                'created_by' => $request->user()->id,
            ]);
        }
    }

    /** @param array<int, string> $storedPaths */
    private function copyPlanningDocuments(
        Procurement $procurement,
        ThinkTankProcurementItem $item,
        Request $request,
        array &$storedPaths,
    ): void {
        foreach ($item->documents as $source) {
            $sourcePath = str_replace('\\', '/', trim((string) $source->file_path));
            $expected = "think-tank-procurement/{$item->plan_id}/{$item->id}/";
            if ($sourcePath === '' || str_contains($sourcePath, '..') || ! str_starts_with($sourcePath, $expected)) {
                throw new ThinkTankApiException('DOCUMENT_COPY_FAILED', 'A planning document has an invalid storage reference.', 422);
            }
            if (! Storage::disk('local')->exists($sourcePath)) {
                throw new ThinkTankApiException('DOCUMENT_COPY_FAILED', 'A planning document required by this execution is unavailable.', 422);
            }
            $extension = Str::lower(pathinfo((string) $source->original_name, PATHINFO_EXTENSION));
            $path = "procurements/{$procurement->id}/documents/".Str::uuid().($extension !== '' ? '.'.$extension : '');
            if (! Storage::disk('local')->copy($sourcePath, $path)) {
                throw new ThinkTankApiException('DOCUMENT_COPY_FAILED', 'A planning document could not be copied to the execution record.', 500);
            }
            $storedPaths[] = $path;
            $procurement->documents()->create([
                'document_name' => $source->document_name,
                'original_name' => $this->safeFileName($source->original_name),
                'file_path' => $path,
                'mime_type' => $source->mime_type,
                'file_size' => (int) $source->file_size,
                'audience' => $source->document_type === 'tor'
                    ? ProcurementDocument::AUDIENCE_BIDDER
                    : ProcurementDocument::AUDIENCE_INTERNAL,
                'uploaded_by' => $request->user()->id,
            ]);
        }
    }

    /** @param array<int, string> $storedPaths */
    private function persistDocuments(Request $request, Procurement $procurement, array &$storedPaths): void
    {
        foreach ($request->file('documents', []) as $index => $row) {
            $file = is_array($row) ? ($row['file'] ?? null) : null;
            if (! $file) {
                continue;
            }
            $extension = Str::lower((string) $file->getClientOriginalExtension());
            $path = $file->storeAs(
                "procurements/{$procurement->id}/documents",
                Str::uuid().($extension !== '' ? '.'.$extension : ''),
                'local',
            );
            if (! $path) {
                throw new ThinkTankApiException('DOCUMENT_STORAGE_FAILED', 'A procurement document could not be stored.', 500);
            }
            $storedPaths[] = $path;
            $input = (array) $request->input("documents.{$index}", []);
            $procurement->documents()->create([
                'document_name' => trim((string) ($input['name'] ?? '')) ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'original_name' => $this->safeFileName($file->getClientOriginalName()),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => (int) $file->getSize(),
                'audience' => $input['audience'] ?? ProcurementDocument::AUDIENCE_BIDDER,
                'uploaded_by' => $request->user()->id,
            ]);
        }
    }

    private function storeCover(Request $request, ConsortiumThinkTank $member): string
    {
        $file = $request->file('cover_image');
        $extension = Str::lower((string) $file->getClientOriginalExtension());
        $path = $file->storeAs("procurement-covers/{$member->id}", Str::uuid().'.'.$extension, 'public');
        if (! $path) {
            throw new ThinkTankApiException('COVER_STORAGE_FAILED', 'The cover image could not be stored.', 500);
        }

        return $path;
    }

    /** @param array<int, array<string, mixed>> $fields
     * @return array<int, array<string, mixed>>
     */
    private function validatedFormFields(array $fields): array
    {
        $normalized = [];
        $keys = [];
        $sortPositions = [];
        foreach (array_values($fields) as $index => $field) {
            $key = trim((string) ($field['key'] ?? '')) ?: Str::slug((string) $field['label'], '_');
            if ($key === '' || mb_strlen($key) > 80 || ! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
                throw ValidationException::withMessages(["fields.{$index}.key" => ['Use a unique key of no more than 80 characters beginning with a letter.']]);
            }
            if (in_array($key, DynamicProcurementFormCatalog::RESERVED_FIELD_KEYS, true)) {
                throw ValidationException::withMessages(["fields.{$index}.key" => ['This key is reserved by the procurement application workflow.']]);
            }
            if (in_array($key, $keys, true)) {
                throw ValidationException::withMessages(["fields.{$index}.key" => ['Each application form field key must be unique.']]);
            }
            $keys[] = $key;
            $requestedSort = (int) ($field['sort_order'] ?? (110 + ($index * 10)));
            if (in_array($requestedSort, $sortPositions, true)) {
                throw ValidationException::withMessages(["fields.{$index}.sort_order" => ['Each form field sort position must be unique.']]);
            }
            $sortPositions[] = $requestedSort;
            $type = (string) $field['type'];
            $rawOptions = collect((array) ($field['options'] ?? []))
                ->map(fn ($option): string => trim((string) $option))
                ->filter()
                ->values();
            $options = $rawOptions
                ->unique(fn (string $option): string => Str::lower($option))
                ->values()
                ->all();
            if ($rawOptions->count() !== count($options)) {
                throw ValidationException::withMessages(["fields.{$index}.options" => ['Each answer choice must be unique, ignoring letter case.']]);
            }
            $isChoice = in_array($type, DynamicProcurementFormCatalog::CHOICE_TYPES, true);
            if ($isChoice && count($options) < 2) {
                throw ValidationException::withMessages(["fields.{$index}.options" => ['Enter at least two unique choices.']]);
            }
            if (! $isChoice && $options !== []) {
                throw ValidationException::withMessages(["fields.{$index}.options" => ['Only choice fields can define answer options.']]);
            }
            $validation = collect((array) ($field['validation'] ?? []))
                ->reject(fn (mixed $value): bool => $value === null || $value === '' || $value === [])
                ->all();
            $unexpectedValidation = array_diff(
                array_keys($validation),
                DynamicProcurementFormCatalog::validationKeysFor($type),
            );
            if ($unexpectedValidation !== []) {
                throw ValidationException::withMessages([
                    "fields.{$index}.validation" => ['These validation settings do not apply to the selected answer type: '.implode(', ', $unexpectedValidation).'.'],
                ]);
            }
            if (isset($validation['min'], $validation['max']) && (float) $validation['min'] > (float) $validation['max']) {
                throw ValidationException::withMessages(["fields.{$index}.validation.max" => ['The maximum must be greater than or equal to the minimum.']]);
            }
            if (in_array($type, DynamicProcurementFormCatalog::UPLOAD_TYPES, true)) {
                $safe = DynamicProcurementFormCatalog::extensionsFor($type);
                $requested = collect((array) ($validation['allowed_extensions'] ?? $safe))
                    ->map(fn (mixed $extension): string => Str::lower(trim((string) $extension)))
                    ->unique()
                    ->values()
                    ->all();
                $invalidExtensions = array_values(array_diff($requested, $safe));
                if ($invalidExtensions !== []) {
                    throw ValidationException::withMessages([
                        "fields.{$index}.validation.allowed_extensions" => ['Unsupported file extensions: '.implode(', ', $invalidExtensions).'.'],
                    ]);
                }
                $validation['allowed_extensions'] = $requested ?: $safe;
                $validation['max_file_size_mb'] = (int) ($validation['max_file_size_mb'] ?? (
                    $type === 'image'
                        ? DynamicProcurementFormCatalog::DEFAULT_IMAGE_SIZE_MB
                        : DynamicProcurementFormCatalog::DEFAULT_FILE_SIZE_MB
                ));
            }
            $placeholder = $this->nullableText($field['placeholder'] ?? null);
            if ($placeholder !== null && ! in_array($type, DynamicProcurementFormCatalog::PLACEHOLDER_TYPES, true)) {
                throw ValidationException::withMessages([
                    "fields.{$index}.placeholder" => ['The selected answer type does not use placeholder text.'],
                ]);
            }
            $normalized[] = [
                'key' => $key,
                'label' => trim((string) $field['label']),
                'type' => $type,
                'required' => (bool) $field['required'],
                'options' => $options,
                'help_text' => $this->nullableText($field['help_text'] ?? null),
                'placeholder' => $placeholder,
                'validation' => $validation,
                '_requested_sort' => $requestedSort,
                '_input_order' => $index,
            ];
        }
        $uploadFieldCount = collect($normalized)
            ->whereIn('type', DynamicProcurementFormCatalog::UPLOAD_TYPES)
            ->count();
        if ($uploadFieldCount > DynamicProcurementFormCatalog::MAX_UPLOAD_FIELDS) {
            throw ValidationException::withMessages([
                'fields' => [
                    'An application form may contain no more than '
                    .DynamicProcurementFormCatalog::MAX_UPLOAD_FIELDS
                    .' file or image upload fields.',
                ],
            ]);
        }
        $totalOptionCount = collect($normalized)->sum(fn (array $field): int => count($field['options']));
        if ($totalOptionCount > DynamicProcurementFormCatalog::MAX_TOTAL_OPTIONS) {
            throw ValidationException::withMessages([
                'fields' => [
                    'The application form may contain no more than '
                    .DynamicProcurementFormCatalog::MAX_TOTAL_OPTIONS
                    .' answer choices across all choice fields.',
                ],
            ]);
        }
        usort($normalized, fn (array $left, array $right): int => [
            $left['_requested_sort'], $left['_input_order'],
        ] <=> [
            $right['_requested_sort'], $right['_input_order'],
        ]);

        return collect($normalized)->values()->map(function (array $field, int $index): array {
            unset($field['_requested_sort'], $field['_input_order']);
            $field['sort_order'] = 110 + ($index * 10);

            return $field;
        })->all();
    }

    private function executionEvent(Procurement $procurement, Request $request, string $action, array $metadata = []): void
    {
        $item = ThinkTankProcurementItem::query()->where('procurement_id', $procurement->id)->with('plan')->firstOrFail();
        $this->workflow->event($item->plan, $item, $request->user(), $action, $item->status, $item->status, null, [
            'procurement_id' => $procurement->id,
            'notification_suppressed' => true,
            ...$metadata,
        ]);
    }

    private function executionQuery(ConsortiumThinkTank $member)
    {
        return Procurement::query()
            ->where('procurement_owner_type', 'think_tank')
            ->where('think_tank_member_id', $member->id)
            ->whereNotNull('think_tank_procurement_plan_id');
    }

    private function eligibleQuery(ConsortiumThinkTank $member)
    {
        return ThinkTankProcurementItem::query()
            ->where('status', ThinkTankProcurementItem::STATUS_NO_OBJECTION)
            ->whereNull('procurement_id')
            ->whereNotNull('no_objection_date')
            ->where(function ($ready): void {
                $ready->where(function ($reference): void {
                    $reference->whereNotNull('no_objection_reference')
                        ->where('no_objection_reference', '<>', '');
                })->orWhereHas('documents', fn ($documents) => $documents->where('document_type', 'no_objection'));
            })
            ->whereHas('plan', fn ($plans) => $plans
                ->where('think_tank_member_id', $member->id)
                ->where('status', ThinkTankProcurementPlan::STATUS_APPROVED));
    }

    private function ownedExecution(Request $request, string $id): Procurement
    {
        return $this->executionQuery($this->member($request))
            ->with(['thinkTankPlanningItem.plan', 'thinkTankPlanningItem.documents', 'forms.fields', 'documents'])
            ->whereKey($id)
            ->firstOrFail();
    }

    private function lockedExecution(ConsortiumThinkTank $member, string $id): Procurement
    {
        return $this->executionQuery($member)->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function lockedExecutionForm(Procurement $procurement): DynamicForm
    {
        $forms = DynamicForm::query()
            ->where('procurement_id', $procurement->id)
            ->lockForUpdate()
            ->limit(2)
            ->get();
        if ($forms->count() !== 1) {
            throw new ThinkTankApiException(
                'FORM_INTEGRITY_ERROR',
                'This procurement does not have one authoritative application form. Contact support before continuing.',
                409,
            );
        }

        return $forms->first();
    }

    private function member(Request $request): ConsortiumThinkTank
    {
        $member = $request->attributes->get('think_tank.membership');
        abort_unless($member instanceof ConsortiumThinkTank, 403);

        return $member;
    }

    /** @return array{canView: bool, canManage: bool} */
    private function permissions(Request $request): array
    {
        $manage = $request->user()->hasPermission('think_tank.procurement_plans.manage');

        return [
            'canView' => $manage || $request->user()->hasPermission('think_tank.procurement_plans.view'),
            'canManage' => $manage,
        ];
    }

    private function applyMethodFilter($query, ConsortiumThinkTank $member, string $method, bool $items = false): void
    {
        if ($method === '') {
            return;
        }
        $matchingItemIds = ThinkTankProcurementItem::query()
            ->whereHas('plan', fn ($plans) => $plans->where('think_tank_member_id', $member->id))
            ->get(['id', 'procurement_method', 'procurement_category'])
            ->filter(function (ThinkTankProcurementItem $item) use ($method): bool {
                $code = $this->planning->methodCode((string) $item->procurement_method, $item->procurement_category) ?: 'other';

                return $code === $method;
            })
            ->pluck('id');
        if ($items) {
            $query->whereIn('id', $matchingItemIds);
        } else {
            $query->whereHas('thinkTankPlanningItem', fn ($planningItems) => $planningItems->whereIn('id', $matchingItemIds));
        }
    }

    /** @return array<int, string> */
    private function methodCodes(): array
    {
        return collect($this->execution->methodTemplates())->pluck('code')->all();
    }

    /** @return array<int, array{value: string, label: string}> */
    private function methodOptions(): array
    {
        return collect($this->execution->methodTemplates())
            ->map(fn (array $method): array => ['value' => $method['code'], 'label' => $method['label']])
            ->all();
    }

    private function fiscalYearNumber(string $value): int
    {
        return preg_match('/20\d{2}/', $value, $match) ? (int) $match[0] : (int) now()->format('Y');
    }

    private function assertDateWindow(?string $start, ?string $end): void
    {
        if (($start === null) !== ($end === null)) {
            throw ValidationException::withMessages(['application_end_date' => ['Set both the application start date and closing date.']]);
        }
        if ($start && $end && $end < $start) {
            throw ValidationException::withMessages(['application_end_date' => ['The closing date must be on or after the start date.']]);
        }
    }

    private function assertUploadEnvelope(Request $request): void
    {
        $files = collect($request->file('documents', []))
            ->map(fn ($row) => is_array($row) ? ($row['file'] ?? null) : null)
            ->filter();
        if ($request->hasFile('cover_image')) {
            $files->push($request->file('cover_image'));
        }
        if ($files->count() > self::MAX_FILES_PER_REQUEST) {
            throw ValidationException::withMessages(['documents' => ['Upload no more than '.self::MAX_FILES_PER_REQUEST.' files at a time.']]);
        }
        if ((int) $files->sum(fn ($file): int => (int) $file->getSize()) > self::MAX_BYTES_PER_REQUEST) {
            throw ValidationException::withMessages(['documents' => ['The combined upload must not exceed 60 MB.']]);
        }
    }

    private function assertDocumentRowsHaveOnly(Request $request, array $allowed): void
    {
        foreach ((array) $request->input('documents', []) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $unexpected = array_diff(array_keys($row), $allowed);
            if ($unexpected !== []) {
                throw ValidationException::withMessages(["documents.{$index}" => ['Unexpected document fields: '.implode(', ', $unexpected).'.']]);
            }
        }
    }

    private function assertFormRowsHaveOnly(Request $request): void
    {
        $fieldKeys = ['key', 'label', 'type', 'required', 'options', 'help_text', 'placeholder', 'validation', 'sort_order'];
        $validationKeys = ['min', 'max', 'max_length', 'allowed_extensions', 'max_file_size_mb'];
        foreach ((array) $request->input('fields', []) as $index => $field) {
            if (! is_array($field)) {
                continue;
            }
            $unexpected = array_diff(array_keys($field), $fieldKeys);
            if ($unexpected !== []) {
                throw ValidationException::withMessages(["fields.{$index}" => ['Unexpected form field properties: '.implode(', ', $unexpected).'.']]);
            }
            $configuration = $field['validation'] ?? [];
            if (is_array($configuration)) {
                $unexpectedValidation = array_diff(array_keys($configuration), $validationKeys);
                if ($unexpectedValidation !== []) {
                    throw ValidationException::withMessages(["fields.{$index}.validation" => ['Unexpected validation properties: '.implode(', ', $unexpectedValidation).'.']]);
                }
            }
        }
    }

    private function verifiedDocumentPath(Procurement $procurement, ProcurementDocument $document, bool $mustExist = true): string
    {
        abort_unless((string) $document->procurement_id === (string) $procurement->id, 404);
        $path = str_replace('\\', '/', trim((string) $document->file_path));
        $expected = "procurements/{$procurement->id}/documents/";
        abort_unless($path !== '' && ! str_contains($path, '..') && str_starts_with($path, $expected), 404);
        if ($mustExist) {
            abort_unless(Storage::disk('local')->exists($path), 404, 'Procurement document file not found.');
        }

        return $path;
    }

    private function verifiedCoverPath(Procurement $procurement): ?string
    {
        $path = str_replace('\\', '/', trim((string) $procurement->cover_image_path));
        if ($path === '') {
            return null;
        }
        $expected = "procurement-covers/{$procurement->think_tank_member_id}/";
        abort_unless(! str_contains($path, '..') && str_starts_with($path, $expected), 422, 'The stored cover image reference is invalid.');

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

    private function safeFileName(mixed $value): string
    {
        $name = basename(str_replace('\\', '/', trim((string) $value)));
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? '';
        $name = trim($name, " .\t\n\r\0\x0B");

        return mb_substr($name !== '' && ! in_array($name, ['.', '..'], true) ? $name : 'procurement-document', 0, 180);
    }

    /** @param array<int, string> $localPaths
     * @param array<int, string> $publicPaths
     */
    private function deleteStoredPaths(array $localPaths, array $publicPaths = []): void
    {
        foreach ($localPaths as $path) {
            Storage::disk('local')->delete($path);
        }
        foreach ($publicPaths as $path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private function pagination($paginator): array
    {
        return [
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    /** @param array<int, string> $allowed */
    private function rejectUnexpectedQuery(Request $request, array $allowed): void
    {
        $unexpected = array_diff(array_keys($request->query->all()), $allowed);
        if ($unexpected !== []) {
            throw ValidationException::withMessages(collect($unexpected)
                ->mapWithKeys(fn (string $key): array => [$key => ['This query parameter is not allowed.']])->all());
        }
    }

    /** @param array<int, string> $allowed */
    private function rejectUnexpectedBody(Request $request, array $allowed): void
    {
        $this->rejectUnexpectedQuery($request, []);
        $unexpected = array_diff(array_keys($request->all()), $allowed);
        if ($unexpected !== []) {
            throw ValidationException::withMessages(collect($unexpected)
                ->mapWithKeys(fn (string $key): array => [$key => ['This field is not allowed.']])->all());
        }
    }

    private function assertJsonObject(Request $request): void
    {
        $this->rejectUnexpectedQuery($request, []);
        if (! $request->isJson()) {
            throw new ThinkTankApiException('JSON_BODY_REQUIRED', 'This operation requires an application/json request body.', 415);
        }
        if (! is_object(json_decode((string) $request->getContent()))) {
            throw new ThinkTankApiException('JSON_OBJECT_REQUIRED', 'The JSON request body must be an object.', 422);
        }
    }

    private function assertMultipart(Request $request): void
    {
        $this->rejectUnexpectedQuery($request, []);
        if (! str_starts_with(Str::lower((string) $request->header('Content-Type')), 'multipart/form-data')) {
            throw new ThinkTankApiException('MULTIPART_BODY_REQUIRED', 'This operation requires a multipart/form-data request body.', 415);
        }
    }
}
