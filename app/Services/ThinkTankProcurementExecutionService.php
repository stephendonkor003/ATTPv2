<?php

namespace App\Services;

use App\Models\DynamicForm;
use App\Models\DynamicFormField;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\ThinkTankProcurementItem;
use App\Models\User;
use App\Support\DynamicProcurementFormCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ThinkTankProcurementExecutionService
{
    public const EXECUTION_STATUSES = ['draft', 'published', 'recalled', 'closed'];

    public const FIELD_TYPES = DynamicProcurementFormCatalog::FIELD_TYPES;

    /** @var array<int, string> */
    public const SAFE_DOCUMENT_EXTENSIONS = DynamicProcurementFormCatalog::FILE_EXTENSIONS;

    public function __construct(private readonly ThinkTankProcurementApiService $planning) {}

    /** @return array<int, array<string, mixed>> */
    public function methodTemplates(): array
    {
        return collect($this->planning->methodTemplates())
            ->map(function (array $method): array {
                $code = (string) $method['code'];

                return [
                    'code' => $code,
                    'label' => (string) $method['label'],
                    'suggestedFields' => collect($this->suggestedFields($code))
                        ->map(fn (array $field): array => $this->fieldDefinition($field))
                        ->values()
                        ->all(),
                ];
            })
            ->push([
                'code' => 'other',
                'label' => 'Other / imported method',
                'suggestedFields' => collect($this->suggestedFields('other'))
                    ->map(fn (array $field): array => $this->fieldDefinition($field))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function formBuilderMetadata(): array
    {
        return DynamicProcurementFormCatalog::builderMetadata();
    }

    /** @return array{categories: array<int, array<string, mixed>>, vendors: array<int, array<string, mixed>>} */
    public function tenantVendorOptions(Procurement $procurement): array
    {
        $tenant = $procurement->thinkTankMember
            ?: $procurement->thinkTankMember()->first();

        if (! $tenant) {
            return ['categories' => [], 'vendors' => []];
        }

        return app(ThinkTankVendorDirectoryService::class)->payload($tenant);
    }

    /** @return array<int, array<string, mixed>> */
    public function suggestedFields(?string $methodCode): array
    {
        $profile = [
            'key' => 'organization_profile',
            'label' => 'Organization Profile',
            'type' => 'file',
            'required' => true,
            'help_text' => 'Upload a PDF or Microsoft Office profile (maximum 20 MB).',
            'validation' => ['allowed_extensions' => ['pdf', 'doc', 'docx'], 'max_file_size_mb' => 20],
        ];
        $quotedAmount = [
            'key' => 'quoted_amount',
            'label' => 'Quoted Amount',
            'type' => 'number',
            'required' => true,
            'placeholder' => 'Enter the total quoted amount',
            'validation' => ['min' => 0],
        ];
        $experience = [
            'key' => 'relevant_experience',
            'label' => 'Relevant Experience',
            'type' => 'textarea',
            'required' => true,
            'placeholder' => 'Summarize assignments relevant to this opportunity',
            'validation' => ['max_length' => 10000],
        ];

        $fields = match ($methodCode) {
            'rfq', 'rfb', 'direct_goods', 'direct_selection' => [
                $profile,
                [
                    'key' => 'signed_quotation',
                    'label' => 'Signed Quotation',
                    'type' => 'file',
                    'required' => true,
                    'validation' => ['allowed_extensions' => ['pdf', 'doc', 'docx'], 'max_file_size_mb' => 20],
                ],
                [
                    'key' => 'price_schedule',
                    'label' => 'Price Schedule',
                    'type' => 'file',
                    'required' => true,
                    'validation' => ['allowed_extensions' => ['pdf', 'xls', 'xlsx'], 'max_file_size_mb' => 20],
                ],
                [
                    'key' => 'specification_compliance',
                    'label' => 'Technical Specifications Compliance',
                    'type' => 'textarea',
                    'required' => true,
                    'validation' => ['max_length' => 10000],
                ],
                [
                    'key' => 'delivery_schedule',
                    'label' => 'Delivery Schedule',
                    'type' => 'text',
                    'required' => true,
                    'validation' => ['max_length' => 500],
                ],
                $quotedAmount,
                $experience,
            ],
            'indv' => [
                [
                    'key' => 'curriculum_vitae',
                    'label' => 'Curriculum Vitae',
                    'type' => 'file',
                    'required' => true,
                    'validation' => ['allowed_extensions' => ['pdf', 'doc', 'docx'], 'max_file_size_mb' => 20],
                ],
                [
                    'key' => 'technical_approach',
                    'label' => 'Technical Approach',
                    'type' => 'file',
                    'required' => true,
                    'validation' => ['allowed_extensions' => ['pdf', 'doc', 'docx'], 'max_file_size_mb' => 20],
                ],
                [
                    'key' => 'financial_quote',
                    'label' => 'Financial Quote',
                    'type' => 'file',
                    'required' => true,
                    'validation' => ['allowed_extensions' => ['pdf', 'xls', 'xlsx'], 'max_file_size_mb' => 20],
                ],
                $quotedAmount,
                [
                    'key' => 'availability',
                    'label' => 'Availability',
                    'type' => 'textarea',
                    'required' => true,
                    'validation' => ['max_length' => 2000],
                ],
                $experience,
            ],
            default => [
                $profile,
                [
                    'key' => 'technical_proposal',
                    'label' => 'Technical Proposal',
                    'type' => 'file',
                    'required' => true,
                    'validation' => ['allowed_extensions' => ['pdf', 'doc', 'docx'], 'max_file_size_mb' => 20],
                ],
                [
                    'key' => 'financial_proposal',
                    'label' => 'Financial Proposal',
                    'type' => 'file',
                    'required' => true,
                    'validation' => ['allowed_extensions' => ['pdf', 'xls', 'xlsx'], 'max_file_size_mb' => 20],
                ],
                [
                    'key' => 'key_experts',
                    'label' => 'Key Experts and Team Composition',
                    'type' => 'textarea',
                    'required' => in_array($methodCode, ['qcbs_fbs_lcs', 'cqs', 'cds'], true),
                    'validation' => ['max_length' => 10000],
                ],
                $quotedAmount,
                $experience,
            ],
        };

        return collect($fields)
            ->values()
            ->map(fn (array $field, int $index): array => [
                ...$field,
                'options' => $field['options'] ?? [],
                'help_text' => $field['help_text'] ?? null,
                'placeholder' => $field['placeholder'] ?? null,
                'sort_order' => ($index + 1) * 10,
            ])
            ->all();
    }

    public function lockToken(Model $model): string
    {
        $attributes = $model->getAttributes();
        ksort($attributes);

        return hash_hmac(
            'sha256',
            $model::class.'|'.$model->getKey().'|'.hash('sha256', serialize($attributes)),
            (string) config('app.key'),
        );
    }

    public function assertLockToken(Model $model, mixed $provided): void
    {
        if (! is_string($provided) || ! hash_equals($this->lockToken($model), trim($provided))) {
            throw new \App\Exceptions\ThinkTankApiException(
                'STALE_WRITE',
                'This procurement execution changed after it was opened. Reload the latest version before saving.',
                409,
            );
        }
    }

    public function bumpLockVersion(Procurement $procurement): void
    {
        $procurement->increment('portal_lock_version');
        $procurement->refresh();
    }

    /** @return array<string, mixed> */
    public function eligibleItem(ThinkTankProcurementItem $item): array
    {
        $item->loadMissing(['plan', 'noObjectionRecorder:id,name']);
        $methodCode = $this->planning->methodCode((string) $item->procurement_method, $item->procurement_category);

        return [
            'id' => (string) $item->id,
            'planId' => (string) $item->plan_id,
            'planCode' => (string) $item->plan?->plan_code,
            'itemCode' => (string) $item->item_code,
            'title' => (string) $item->title,
            'description' => app(ProcurementRichTextService::class)
                ->sanitizeForStorage($item->description),
            'procurementMethod' => [
                'code' => $methodCode ?: 'other',
                'label' => $methodCode
                    ? $this->planning->methodLabel($methodCode)
                    : ((string) $item->procurement_method ?: 'Imported method'),
            ],
            'category' => $item->procurement_category ?: null,
            'estimatedBudget' => (float) $item->estimated_amount,
            'currency' => Str::upper((string) ($item->currency ?: 'USD')),
            'noObjectionReference' => $item->no_objection_reference ?: null,
            'noObjectionDate' => $this->date($item->no_objection_date),
            'readyAt' => $this->dateTime($item->no_objection_recorded_at),
            'lockToken' => $this->planning->lockToken($item),
        ];
    }

    /** @return array<string, mixed> */
    public function executionCard(Procurement $procurement, User $viewer): array
    {
        $procurement->loadMissing(['thinkTankPlanningItem.plan', 'forms.fields', 'documents']);
        $item = $procurement->thinkTankPlanningItem;
        $methodCode = $item
            ? $this->planning->methodCode((string) $item->procurement_method, $item->procurement_category)
            : null;
        $checklist = $this->publishChecklist($procurement);
        $canManage = $viewer->hasPermission('think_tank.procurement_plans.manage');
        $canReviewApplications = $viewer->hasPermission('think_tank.procurement.evaluate');

        return [
            'id' => (string) $procurement->id,
            'status' => (string) $procurement->status,
            'statusLabel' => $this->statusLabel((string) $procurement->status),
            'reference' => $procurement->reference_no ?: null,
            'title' => (string) $procurement->title,
            'fiscalYear' => $procurement->fiscal_year !== null ? (string) $procurement->fiscal_year : null,
            'estimatedBudget' => (float) $procurement->estimated_budget,
            'procurementMethod' => [
                'code' => $methodCode ?: 'other',
                'label' => $methodCode
                    ? $this->planning->methodLabel($methodCode)
                    : ((string) ($item?->procurement_method ?: 'Imported method')),
            ],
            'category' => $item?->procurement_category ?: null,
            'applicationStartDate' => $this->date($procurement->application_start_date),
            'applicationEndDate' => $this->date($procurement->application_end_date),
            'visibilityType' => $procurement->visibility_type ?: 'public',
            'submissionCount' => $procurement->submissions()->count(),
            'publicationVersion' => max(1, (int) $procurement->publication_version),
            'canReviewApplications' => $canReviewApplications,
            'canEdit' => $canManage && $this->canEdit($procurement),
            'canPublish' => $canManage
                && $procurement->status === 'draft'
                && collect($checklist)->every(fn (array $step): bool => (bool) $step['complete']),
            'canRecall' => $canManage && $procurement->status === 'published',
            'canRepublish' => $canManage && $procurement->status === 'recalled',
            'updatedAt' => $this->dateTime($procurement->updated_at),
        ];
    }

    /** @return array<string, mixed> */
    public function execution(Procurement $procurement, User $viewer): array
    {
        $procurement->loadMissing([
            'thinkTankPlanningItem.plan',
            'thinkTankPlanningItem.documents',
            'forms.fields',
            'documents.uploader:id,name',
        ]);
        if ($procurement->forms->count() > 1) {
            throw new \App\Exceptions\ThinkTankApiException(
                'FORM_INTEGRITY_ERROR',
                'This procurement has conflicting application forms. Contact support before continuing.',
                409,
            );
        }
        $form = $procurement->forms->first();
        $item = $procurement->thinkTankPlanningItem;
        $tenantVendorOptions = $this->tenantVendorOptions($procurement);

        return [
            ...$this->executionCard($procurement, $viewer),
            'description' => app(ProcurementRichTextService::class)
                ->sanitizeForStorage($procurement->description),
            'vendorCategories' => array_values((array) $procurement->vendor_categories),
            'tenantVendorCategoryIds' => array_values((array) $procurement->tenant_vendor_category_ids),
            'tenantVendorIds' => array_values((array) $procurement->tenant_vendor_ids),
            'coverImageUrl' => $procurement->cover_image_path
                ? route('api.v1.think-tank.procurement.executions.cover.show', [
                    'execution' => $procurement->id,
                ], false)
                : null,
            'documents' => $procurement->documents->map(fn (ProcurementDocument $document): array => [
                'id' => (string) $document->id,
                'name' => (string) $document->document_name,
                'fileName' => (string) $document->original_name,
                'mimeType' => $document->mime_type ?: null,
                'size' => (int) $document->file_size,
                'audience' => $document->audience ?: ProcurementDocument::AUDIENCE_BIDDER,
                'downloadUrl' => route('api.v1.think-tank.procurement.executions.documents.show', [
                    'execution' => $procurement->id,
                    'document' => $document->id,
                ], false),
                'uploadedAt' => $this->dateTime($document->created_at),
            ])->values()->all(),
            'form' => $form ? [
                'id' => (string) $form->id,
                'name' => (string) $form->name,
                'status' => (string) $form->status,
                'canEdit' => $this->canEdit($procurement) && ! $form->hasSubmissions(),
                'fields' => $form->fields->map(fn (DynamicFormField $field): array => $this->field($field))->values()->all(),
            ] : null,
            'formBuilder' => $this->formBuilderMetadata(),
            'sourceItem' => $item ? [
                'id' => (string) $item->id,
                'planId' => (string) $item->plan_id,
                'planCode' => (string) $item->plan?->plan_code,
                'itemCode' => (string) $item->item_code,
                'title' => (string) $item->title,
                'noObjectionReference' => $item->no_objection_reference ?: null,
                'noObjectionDate' => $this->date($item->no_objection_date),
                'readyAt' => $this->dateTime($item->no_objection_recorded_at),
            ] : null,
            'checklist' => $this->publishChecklist($procurement),
            'tenantVendorCategoryOptions' => $tenantVendorOptions['categories'],
            'tenantVendorOptions' => $tenantVendorOptions['vendors'],
            'lockToken' => $this->lockToken($procurement),
            'createdAt' => $this->dateTime($procurement->created_at),
        ];
    }

    public function canEdit(Procurement $procurement): bool
    {
        return $procurement->status === 'draft' && ! $procurement->submissions()->exists();
    }

    /** @return array<int, array{key: string, label: string, complete: bool, message: string}> */
    public function publishChecklist(Procurement $procurement): array
    {
        $procurement->loadMissing(['thinkTankPlanningItem.documents', 'forms.fields', 'documents']);
        $item = $procurement->thinkTankPlanningItem;
        /** @var DynamicForm|null $form */
        $form = $procurement->forms->sortByDesc('created_at')->first();
        $fieldKeys = $form?->fields?->pluck('field_key') ?? collect();
        $hasDates = $procurement->application_start_date
            && $procurement->application_end_date
            && $procurement->application_end_date->gte($procurement->application_start_date)
            && $procurement->application_end_date->gte(now()->startOfDay());
        $hasBidderDocument = $procurement->documents
            ->contains(fn (ProcurementDocument $document): bool => ($document->audience ?: ProcurementDocument::AUDIENCE_BIDDER) === ProcurementDocument::AUDIENCE_BIDDER);
        $hasForm = $form
            && $form->status === 'approved'
            && $fieldKeys->contains('official_name')
            && $fieldKeys->contains('official_email')
            && $form->fields->count() > count(DynamicForm::GLOBAL_FIELDS);
        $vendorAudienceReady = ($procurement->visibility_type ?: 'public') !== 'vendor_group'
            || count((array) $procurement->tenant_vendor_category_ids) > 0
            || count((array) $procurement->tenant_vendor_ids) > 0;
        $hasNoObjectionEvidence = $item?->documents?->contains(
            fn ($document): bool => (string) $document->document_type === 'no_objection'
        ) ?? false;
        $noObjectionReady = $item
            && in_array($item->status, [
                ThinkTankProcurementItem::STATUS_NO_OBJECTION,
                ThinkTankProcurementItem::STATUS_PUBLISHED,
            ], true)
            && filled($item->no_objection_date)
            && (filled($item->no_objection_reference) || $hasNoObjectionEvidence);

        return [
            [
                'key' => 'no_objection',
                'label' => 'World Bank no-objection recorded',
                'complete' => (bool) $noObjectionReady,
                'message' => $noObjectionReady
                    ? 'The approved planning item is authorized for execution.'
                    : 'A valid World Bank no-objection record is required.',
            ],
            [
                'key' => 'opportunity_details',
                'label' => 'Opportunity details completed',
                'complete' => filled($procurement->title) && filled($procurement->description),
                'message' => filled($procurement->title) && filled($procurement->description)
                    ? 'The title and description are ready.'
                    : 'Add a title and procurement description.',
            ],
            [
                'key' => 'application_window',
                'label' => 'Application window is valid',
                'complete' => (bool) $hasDates,
                'message' => $hasDates
                    ? 'The application dates are complete and the closing date has not passed.'
                    : 'Set a start date and a closing date that is today or later.',
            ],
            [
                'key' => 'application_form',
                'label' => 'Method-appropriate application form configured',
                'complete' => (bool) $hasForm,
                'message' => $hasForm
                    ? 'The approved form includes the protected Name and Email fields plus bidder response fields.'
                    : 'Configure an approved application form with Name, Email and at least one response field.',
            ],
            [
                'key' => 'bidder_documents',
                'label' => 'At least one bidder-facing document',
                'complete' => $hasBidderDocument,
                'message' => $hasBidderDocument
                    ? 'Applicants will receive the intended procurement documents.'
                    : 'Upload at least one document for bidders before publication.',
            ],
            [
                'key' => 'audience',
                'label' => 'Publication audience configured',
                'complete' => $vendorAudienceReady,
                'message' => $vendorAudienceReady
                    ? 'The opportunity audience is configured.'
                    : 'Choose at least one active vendor category for a vendor-group opportunity.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function field(DynamicFormField $field): array
    {
        $validation = (array) $field->validation_rules;

        return [
            'id' => (string) $field->id,
            'key' => (string) $field->field_key,
            'label' => (string) $field->label,
            'type' => (string) $field->field_type,
            'required' => (bool) $field->is_required,
            'options' => $field->optionValues(),
            'helpText' => $field->help_text ?: null,
            'placeholder' => $field->placeholder ?: null,
            'validation' => $this->validationDto($validation),
            'sortOrder' => (int) $field->sort_order,
            'isSystem' => in_array($field->field_key, DynamicForm::globalFieldKeys(), true),
        ];
    }

    /** @param array<string, mixed> $field
     * @return array<string, mixed>
     */
    public function fieldDefinition(array $field): array
    {
        return [
            'id' => null,
            'key' => (string) $field['key'],
            'label' => (string) $field['label'],
            'type' => (string) $field['type'],
            'required' => (bool) ($field['required'] ?? false),
            'options' => array_values((array) ($field['options'] ?? [])),
            'helpText' => $field['help_text'] ?? null,
            'placeholder' => $field['placeholder'] ?? null,
            'validation' => $this->validationDto((array) ($field['validation'] ?? [])),
            'sortOrder' => (int) ($field['sort_order'] ?? 0),
            'isSystem' => false,
        ];
    }

    /** @param array<string, mixed> $validation
     * @return array<string, mixed>
     */
    private function validationDto(array $validation): array
    {
        return collect([
            'min' => $validation['min'] ?? null,
            'max' => $validation['max'] ?? null,
            'maxLength' => $validation['max_length'] ?? null,
            'allowedExtensions' => isset($validation['allowed_extensions'])
                ? array_values((array) $validation['allowed_extensions'])
                : null,
            'maxFileSizeMb' => $validation['max_file_size_mb'] ?? null,
        ])->reject(fn (mixed $value): bool => $value === null)->all();
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'published' => 'Published for applications',
            'recalled' => 'Recalled - action required',
            'closed' => 'Closed',
            default => 'Draft setup',
        };
    }

    private function date(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return method_exists($value, 'toDateString') ? $value->toDateString() : (string) $value;
    }

    private function dateTime(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return method_exists($value, 'toIso8601String') ? $value->toIso8601String() : (string) $value;
    }
}
