<?php

use App\Models\DynamicForm;
use App\Models\DynamicFormField;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use App\Services\ThinkTankProcurementApiService;
use App\Services\ThinkTankProcurementExecutionService;
use App\Services\DynamicProcurementSubmissionValidation;
use App\Services\DynamicProcurementSubmissionFileService;
use App\Support\DynamicProcurementFormCatalog;
use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

function bootThinkTankProcurementExecutionApplication(): array
{
    if (Container::getInstance()->bound(Kernel::class)) {
        return [Container::getInstance(), false];
    }

    $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    return [$application, true];
}

function procurementExecutionService(): ThinkTankProcurementExecutionService
{
    return new ThinkTankProcurementExecutionService(new ThinkTankProcurementApiService);
}

it('provides method-specific bidder forms and a stable imported method fallback', function () {
    [, $bootedHere] = bootThinkTankProcurementExecutionApplication();

    try {
        $service = procurementExecutionService();
        $templates = collect($service->methodTemplates())->keyBy('code');

        expect($templates->keys()->all())->toContain(
            'rfq', 'rfb', 'qcbs_fbs_lcs', 'cqs', 'cds', 'indv',
            'direct_goods', 'direct_selection', 'other'
        )
            ->and(collect($templates['rfq']['suggestedFields'])->pluck('key')->all())
            ->toContain('signed_quotation', 'price_schedule', 'specification_compliance', 'delivery_schedule')
            ->and(collect($templates['rfb']['suggestedFields'])->pluck('key')->all())
            ->toContain('signed_quotation', 'price_schedule', 'specification_compliance', 'delivery_schedule')
            ->and(collect($templates['direct_selection']['suggestedFields'])->pluck('key')->all())
            ->toContain('signed_quotation', 'price_schedule', 'specification_compliance', 'delivery_schedule')
            ->and(collect($templates['indv']['suggestedFields'])->pluck('key')->all())
            ->toContain('curriculum_vitae', 'technical_approach', 'financial_quote')
            ->and(collect($templates['qcbs_fbs_lcs']['suggestedFields'])->pluck('key')->all())
            ->toContain('technical_proposal', 'financial_proposal', 'key_experts')
            ->and($templates['other']['label'])->toBe('Other / imported method');

        $plan = (new ThinkTankProcurementPlan)->forceFill([
            'id' => '91000000-0000-4000-8000-000000000001',
            'plan_code' => 'TT-PP-2026-TEST',
        ]);
        $item = (new ThinkTankProcurementItem)->forceFill([
            'id' => '91000000-0000-4000-8000-000000000002',
            'plan_id' => $plan->id,
            'item_code' => 'TT-PP-2026-TEST-001',
            'title' => 'Imported opportunity',
            'procurement_method' => 'Legacy special method',
            'estimated_amount' => 25000,
            'currency' => 'USD',
            'status' => ThinkTankProcurementItem::STATUS_NO_OBJECTION,
        ]);
        $item->setRelation('plan', $plan);
        $item->setRelation('noObjectionRecorder', null);

        expect($service->eligibleItem($item)['procurementMethod'])->toMatchArray([
            'code' => 'other',
            'label' => 'Legacy special method',
        ]);
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('accepts dated no-objection evidence when a textual reference is unavailable', function () {
    [, $bootedHere] = bootThinkTankProcurementExecutionApplication();

    try {
        $service = procurementExecutionService();
        $evidence = (new ProcurementDocument)->forceFill([
            'id' => '91000000-0000-4000-8000-000000000003',
            'audience' => ProcurementDocument::AUDIENCE_BIDDER,
        ]);
        $noObjection = new \App\Models\ThinkTankProcurementDocument;
        $noObjection->forceFill([
            'id' => '91000000-0000-4000-8000-000000000004',
            'document_type' => 'no_objection',
        ]);
        $item = (new ThinkTankProcurementItem)->forceFill([
            'id' => '91000000-0000-4000-8000-000000000005',
            'status' => ThinkTankProcurementItem::STATUS_NO_OBJECTION,
            'no_objection_reference' => null,
            'no_objection_date' => now()->subDay()->toDateString(),
        ]);
        $item->setRelation('documents', new EloquentCollection([$noObjection]));
        $name = (new DynamicFormField)->forceFill([
            'id' => '91000000-0000-4000-8000-000000000006',
            'field_key' => 'official_name',
        ]);
        $email = (new DynamicFormField)->forceFill([
            'id' => '91000000-0000-4000-8000-000000000007',
            'field_key' => 'official_email',
        ]);
        $proposal = (new DynamicFormField)->forceFill([
            'id' => '91000000-0000-4000-8000-000000000008',
            'field_key' => 'technical_proposal',
        ]);
        $form = (new DynamicForm)->forceFill([
            'id' => '91000000-0000-4000-8000-000000000009',
            'status' => 'approved',
        ]);
        $form->setRelation('fields', new EloquentCollection([$name, $email, $proposal]));
        $procurement = (new Procurement)->forceFill([
            'id' => '91000000-0000-4000-8000-000000000010',
            'title' => 'Opportunity',
            'description' => 'Complete procurement description.',
            'status' => 'draft',
            'visibility_type' => 'public',
            'application_start_date' => now()->toDateString(),
            'application_end_date' => now()->addWeek()->toDateString(),
        ]);
        $procurement->setRelation('thinkTankPlanningItem', $item);
        $procurement->setRelation('forms', new EloquentCollection([$form]));
        $procurement->setRelation('documents', new EloquentCollection([$evidence]));

        $steps = collect($service->publishChecklist($procurement))->keyBy('key');

        expect($steps['no_objection']['complete'])->toBeTrue()
            ->and($steps->every(fn (array $step): bool => $step['complete']))->toBeTrue();

        $item->setRelation('documents', new EloquentCollection);
        $stepsWithoutEvidence = collect($service->publishChecklist($procurement))->keyBy('key');
        expect($stepsWithoutEvidence['no_objection']['complete'])->toBeFalse();
    } finally {
        Carbon::setTestNow();
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('binds execution lock tokens to the current procurement state', function () {
    [, $bootedHere] = bootThinkTankProcurementExecutionApplication();
    $oldKey = config('app.key');
    config(['app.key' => 'base64:think-tank-execution-test-key']);

    try {
        $service = procurementExecutionService();
        $procurement = (new Procurement)->forceFill([
            'id' => '91000000-0000-4000-8000-000000000011',
            'title' => 'Original title',
            'status' => 'draft',
            'updated_at' => '2026-10-01 08:00:00',
        ]);
        $token = $service->lockToken($procurement);
        $procurement->title = 'Changed title';

        expect($token)->toHaveLength(64)
            ->and($service->lockToken($procurement))->not->toBe($token)
            ->and(fn () => $service->assertLockToken($procurement, $token))
            ->toThrow(\App\Exceptions\ThinkTankApiException::class);
    } finally {
        config(['app.key' => $oldKey]);
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('registers a tenant-scoped draft build and publication API with private and bidder document boundaries', function () {
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/api/think-tank.php');
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Api/V1/ThinkTank/ProcurementExecutionController.php');
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/ThinkTankProcurementExecutionService.php');
    $publicController = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Procurement/PublicProcurementController.php');
    $vendorController = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Vendor/VendorProcurementController.php');
    $publicationNotifications = file_get_contents(dirname(__DIR__, 2).'/app/Services/ProcurementPublicationNotificationService.php');
    $legacyController = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/ThinkTankProcurementPlanController.php');
    $statusMail = file_get_contents(dirname(__DIR__, 2).'/app/Mail/ThinkTankProcurementStatusMail.php');
    $legacyView = file_get_contents(dirname(__DIR__, 2).'/resources/views/think-tank/procurement-plan-show.blade.php');

    expect($routes)->toContain(
        "Route::get('executions/eligible-items'",
        "Route::post('executions'",
        "Route::put('executions/{execution}/form'",
        "Route::post('executions/{execution}/publish'",
        "Route::post('executions/{execution}/recall'",
        "Route::post('executions/{execution}/republish'",
        "Route::get('executions/{execution}/cover'",
        "permission:think_tank.procurement_plans.manage",
    )->and($controller)->toContain(
        "->where('think_tank_member_id', \$member->id)",
        "->where('procurement_owner_type', 'think_tank')",
        'lockForUpdate()',
        'assertLockToken($procurement',
        "'status' => 'draft'",
        'STATUS_NO_OBJECTION',
        'verifiedDocumentPath',
        "'Content-Security-Policy' => \"default-src 'none'; sandbox\"",
        "'audience' => \$source->document_type === 'tor'",
        "publicationNotifications\n            ->queue",
    )->and($service)->toContain(
        "'tenantVendorCategoryOptions'",
        "'tenantVendorOptions'",
        "document_type === 'no_objection'",
    )->and($publicController)->toContain('bidderFacing()', 'AUDIENCE_BIDDER')
        ->and($vendorController)->toContain('bidderFacing()', 'AUDIENCE_BIDDER')
        ->and($publicationNotifications)->toContain("->where('user_type', 'vendor')", 'VendorProcurementLifecycleMail')
        ->and($publicationNotifications)->not->toContain("where('is_disabled'", "where('is_blacklisted'")
        ->and($legacyController)->toContain('ProcurementPublicationNotificationService', 'Direct one-step publication has been retired')
        ->and($statusMail)->toContain("'/procurement/executions/'.\$procurementId", 'Str::isUuid($procurementId)')
        ->and($legacyView)->toContain('/procurement/executions/create?item=');

    $guardLock = strpos($controller, 'lockAndAssertNoPendingRework');
    $lockedRecheck = strpos($controller, 'assertLockToken($procurement', $guardLock ?: 0);
    expect($guardLock)->not->toBeFalse()
        ->and($lockedRecheck)->not->toBeFalse()
        ->and($lockedRecheck)->toBeGreaterThan($guardLock);
});

it('uses one strict dynamic-form validator for public and vendor-group applications', function () {
    $number = (new DynamicFormField)->forceFill([
        'field_key' => 'quoted_amount',
        'field_type' => 'number',
        'is_required' => true,
        'validation_rules' => ['min' => 100, 'max' => 1000],
    ]);
    $image = (new DynamicFormField)->forceFill([
        'field_key' => 'sample_image',
        'field_type' => 'image',
        'is_required' => false,
        'validation_rules' => ['allowed_extensions' => ['png', 'exe'], 'max_file_size_mb' => 3],
    ]);
    $choice = (new DynamicFormField)->forceFill([
        'field_key' => 'delivery_option',
        'field_type' => 'select',
        'is_required' => true,
        'options' => "Immediate\nScheduled",
    ]);
    $date = (new DynamicFormField)->forceFill([
        'field_key' => 'available_from',
        'field_type' => 'date',
        'is_required' => true,
    ]);
    $form = new DynamicForm;
    $form->setRelation('fields', new EloquentCollection([$number, $image, $choice, $date]));
    $rules = (new DynamicProcurementSubmissionValidation)->rules($form);

    expect($rules['quoted_amount'])->toContain('required', 'numeric', 'min:100', 'max:1000')
        ->and($rules['sample_image'])->toContain('nullable', 'file', 'image', 'mimes:png', 'max:3072')
        ->and($rules['delivery_option'][2])->toBeInstanceOf(\Illuminate\Validation\Rules\In::class)
        ->and($rules['available_from'])->toContain('required', 'date_format:Y-m-d');

    $public = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Procurement/PublicProcurementController.php');
    $vendor = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Vendor/VendorProcurementController.php');
    $vendorView = file_get_contents(dirname(__DIR__, 2).'/resources/views/vendor/procurements/show.blade.php');
    expect($public)->toContain('$submissionValidation->rules($form)')
        ->and($vendor)->toContain('$submissionValidation->rules($form)')
        ->and($vendor)->toContain("in_array(\$field->field_type, ['file', 'image'], true)")
        ->and($vendorView)->toContain(
            '$field->optionValues()',
            "\$field->field_type === 'radio'",
            "\$field->field_type === 'checkbox'",
            "\$field->field_type === 'boolean'",
            "['file', 'image']",
            'allowed_extensions',
            'max_file_size_mb',
            '@readonly($isIdentityField)',
        );
});

it('publishes one authoritative builder catalog and validation rule for every advertised field and file type', function () {
    [, $bootedHere] = bootThinkTankProcurementExecutionApplication();
    Storage::fake('local');
    Storage::fake('public');

    try {
        $metadata = DynamicProcurementFormCatalog::builderMetadata();
        expect(collect($metadata['fieldTypes'])->pluck('value')->all())
            ->toBe(DynamicProcurementFormCatalog::FIELD_TYPES)
            ->and(collect($metadata['fileTypes'])->pluck('extension')->all())
            ->toBe(DynamicProcurementFormCatalog::FILE_EXTENSIONS)
            ->and($metadata['limits'])->toMatchArray([
                'maxCustomFields' => 30,
                'maxUploadFields' => 20,
                'maxTotalOptions' => 500,
                'maxFileSizeMb' => 20,
                'maxSubmissionFiles' => 20,
                'maxSubmissionUploadMb' => 60,
            ])
            ->and($metadata['reservedFieldKeys'])->toContain('vendor_response', 'lock_token', 'confirmation');

        $fields = collect(DynamicProcurementFormCatalog::FIELD_TYPES)->map(function (string $type, int $index): DynamicFormField {
            $options = in_array($type, DynamicProcurementFormCatalog::CHOICE_TYPES, true)
                ? "First\nSecond"
                : null;
            $validation = match ($type) {
                'number' => ['min' => 1, 'max' => 5],
                'text', 'textarea', 'email', 'tel', 'url' => ['max_length' => 500],
                'file' => ['allowed_extensions' => DynamicProcurementFormCatalog::FILE_EXTENSIONS, 'max_file_size_mb' => 20],
                'image' => ['allowed_extensions' => DynamicProcurementFormCatalog::IMAGE_EXTENSIONS, 'max_file_size_mb' => 5],
                default => null,
            };

            return (new DynamicFormField)->forceFill([
                'field_key' => 'field_'.str_replace('-', '_', $type),
                'field_type' => $type,
                'is_required' => $index % 2 === 0,
                'options' => $options,
                'validation_rules' => $validation,
            ]);
        });
        $form = new DynamicForm;
        $form->setRelation('fields', new EloquentCollection($fields->all()));
        $rules = (new DynamicProcurementSubmissionValidation)->rules($form);

        foreach (DynamicProcurementFormCatalog::FIELD_TYPES as $type) {
            expect($rules)->toHaveKey('field_'.str_replace('-', '_', $type));
        }
        expect($rules['field_file'])->toContain(
            'mimes:'.implode(',', DynamicProcurementFormCatalog::FILE_EXTENSIONS),
            'max:20480',
        )->and($rules['field_image'])->toContain(
            'image',
            'mimes:'.implode(',', DynamicProcurementFormCatalog::IMAGE_EXTENSIONS),
            'max:5120',
        )->and($rules)->toHaveKeys(['field_checkbox.*', 'field_multiselect.*']);

        $existing = new Collection([
            'field_file' => (object) ['value' => 'procurement_submissions/existing.pdf'],
        ]);
        Storage::disk('local')->put('procurement_submissions/existing.pdf', 'stored evidence');
        expect((new DynamicProcurementSubmissionValidation)->rules($form, $existing)['field_file'])
            ->toContain('nullable')
            ->not->toContain('required');
        $missingExistingFile = new Collection([
            'field_file' => (object) ['value' => null],
        ]);
        expect((new DynamicProcurementSubmissionValidation)->rules($form, $missingExistingFile)['field_file'])
            ->toContain('required')
            ->not->toContain('nullable');
        $orphanedExistingFile = new Collection([
            'field_file' => (object) ['value' => 'procurement_submissions/missing.pdf'],
        ]);
        expect((new DynamicProcurementSubmissionValidation)->rules($form, $orphanedExistingFile)['field_file'])
            ->toContain('required')
            ->not->toContain('nullable');
        Storage::disk('public')->put('public/procurement_submissions/legacy.pdf', 'legacy evidence');
        $legacyExistingFile = new Collection([
            'field_file' => (object) ['value' => 'public/procurement_submissions/legacy.pdf'],
        ]);
        expect((new DynamicProcurementSubmissionValidation)->rules($form, $legacyExistingFile)['field_file'])
            ->toContain('nullable')
            ->not->toContain('required');
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('fails closed when a stored application form has an unsupported type, duplicate key, or ambiguous choices', function () {
    [, $bootedHere] = bootThinkTankProcurementExecutionApplication();

    $formWith = static function (array $definitions): DynamicForm {
        $form = new DynamicForm;
        $form->setRelation('fields', new EloquentCollection(collect($definitions)
            ->map(fn (array $definition): DynamicFormField => (new DynamicFormField)->forceFill([
                'field_key' => $definition['key'],
                'field_type' => $definition['type'],
                'is_required' => false,
                'options' => $definition['options'] ?? null,
            ]))->all()));

        return $form;
    };

    try {
        $validation = new DynamicProcurementSubmissionValidation;
        expect(fn () => $validation->rules($formWith([
            ['key' => 'unknown_answer', 'type' => 'checkbox_group'],
        ])))->toThrow(ConflictHttpException::class)
            ->and(fn () => $validation->rules($formWith([
                ['key' => 'same_key', 'type' => 'text'],
                ['key' => 'same_key', 'type' => 'textarea'],
            ])))->toThrow(ConflictHttpException::class)
            ->and(fn () => $validation->rules($formWith([
                ['key' => 'ambiguous_choice', 'type' => 'select', 'options' => "Yes\nyes\nNo"],
            ])))->toThrow(ConflictHttpException::class);
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('round trips option punctuation and line breaks without splitting values', function () {
    $options = ['Washington, D.C.', "Delivery\nwithin 14 days", 'Remote / hybrid'];
    $field = (new DynamicFormField)->forceFill([
        'options' => DynamicFormField::encodeOptionValues($options),
    ]);

    expect($field->optionValues())->toBe($options)
        ->and((new DynamicFormField)->forceFill(['options' => "One, Two\nThree"])->optionValues())
        ->toBe(['One', 'Two', 'Three']);
});

it('keeps the advertised upload count within the active PHP request envelope', function () {
    $runtimeLimit = (int) ini_get('max_file_uploads');
    $inputVariableLimit = (int) ini_get('max_input_vars');

    expect(DynamicProcurementFormCatalog::MAX_UPLOAD_FIELDS)
        ->toBe(DynamicProcurementFormCatalog::MAX_SUBMISSION_FILES)
        ->and($runtimeLimit)->toBeGreaterThanOrEqual(DynamicProcurementFormCatalog::MAX_SUBMISSION_FILES)
        ->and($inputVariableLimit)->toBeGreaterThan(
            DynamicProcurementFormCatalog::MAX_TOTAL_OPTIONS
            + DynamicProcurementFormCatalog::MAX_CUSTOM_FIELDS
            + 20,
        );
});

it('enforces the shared upload envelope and keeps submission storage inside its private prefix', function () {
    [, $bootedHere] = bootThinkTankProcurementExecutionApplication();
    Storage::fake('local');
    Storage::fake('public');

    try {
        $upload = (new DynamicFormField)->forceFill([
            'field_key' => 'proposal',
            'field_type' => 'file',
            'is_required' => false,
        ]);
        $newUpload = (new DynamicFormField)->forceFill([
            'field_key' => 'supporting_file',
            'field_type' => 'file',
            'is_required' => false,
        ]);
        $form = new DynamicForm;
        $form->setRelation('fields', new EloquentCollection([$upload, $newUpload]));
        $validation = new DynamicProcurementSubmissionValidation;

        $valid = Request::create('/', 'POST', [], [], [
            'proposal' => UploadedFile::fake()->create('proposal.pdf', 1024, 'application/pdf'),
        ]);
        $validation->assertUploadEnvelope($valid, $form);

        $unexpected = Request::create('/', 'POST', [], [], [
            'not_in_form' => UploadedFile::fake()->create('unknown.pdf', 10, 'application/pdf'),
        ]);
        expect(fn () => $validation->assertUploadEnvelope($unexpected, $form))
            ->toThrow(ValidationException::class);

        $oversized = Request::create('/', 'POST', [], [], [
            'proposal' => [
                UploadedFile::fake()->create('one.pdf', 31 * 1024, 'application/pdf'),
                UploadedFile::fake()->create('two.pdf', 31 * 1024, 'application/pdf'),
            ],
        ]);
        expect(fn () => $validation->assertUploadEnvelope($oversized, $form))
            ->toThrow(ValidationException::class);

        $retainedPath = 'procurement_submissions/retained-large.pdf';
        Storage::disk('local')->put($retainedPath, '');
        $retainedStream = fopen(Storage::disk('local')->path($retainedPath), 'c+b');
        if ($retainedStream === false || ! ftruncate($retainedStream, 59 * 1024 * 1024)) {
            throw new RuntimeException('Could not prepare sparse retained-upload fixture.');
        }
        fclose($retainedStream);
        $replacementRequest = Request::create('/', 'POST', [], [], [
            'supporting_file' => UploadedFile::fake()->create('new.pdf', 2 * 1024, 'application/pdf'),
        ]);
        $existingValues = new Collection([
            'proposal' => (object) ['value' => $retainedPath],
        ]);
        expect(fn () => $validation->assertUploadEnvelope($replacementRequest, $form, $existingValues))
            ->toThrow(ValidationException::class);

        $files = new DynamicProcurementSubmissionFileService;
        $path = $files->store(UploadedFile::fake()->create('stored.pdf', 10, 'application/pdf'));
        expect($path)->toStartWith('procurement_submissions/')
            ->and(Storage::disk('local')->exists($path))->toBeTrue()
            ->and($files->isAllowedPath('procurement_submissions/../.env'))->toBeFalse()
            ->and($files->isAllowedPath('other/private.pdf'))->toBeFalse();
        $files->deleteMany([$path, '../outside']);
        expect(Storage::disk('local')->exists($path))->toBeFalse();
        $legacyPath = 'public/procurement_submissions/legacy.pdf';
        Storage::disk('public')->put($legacyPath, 'legacy evidence');
        expect($files->exists($legacyPath))->toBeTrue();
        $files->deleteMany([$legacyPath]);
        expect(Storage::disk('public')->exists($legacyPath))->toBeFalse();
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('contains tenant application review behind an explicit capability and hardened nested downloads', function () {
    $root = dirname(__DIR__, 2);
    $routes = file_get_contents($root.'/routes/api/think-tank.php');
    $controller = file_get_contents(
        $root.'/app/Http/Controllers/Api/V1/ThinkTank/ProcurementExecutionApplicationController.php',
    );
    $executionService = file_get_contents($root.'/app/Services/ThinkTankProcurementExecutionService.php');
    $user = file_get_contents($root.'/app/Models/User.php');
    $notPartner = file_get_contents($root.'/app/Http/Middleware/EnsureNotFundingPartner.php');

    expect($routes)->toContain(
        "Route::get('executions/{execution}/applications'",
        "Route::get('executions/{execution}/applications/{submission}'",
        "Route::get('executions/{execution}/applications/{submission}/values/{value}/download'",
        "permission:think_tank.procurement.evaluate",
        "executions.applications.values.download",
    )->and($controller)->toContain(
        "->where('think_tank_member_id', \$member->id)",
        "->where('procurement_owner_type', 'think_tank')",
        "->where('procurement_id', \$procurement->id)",
        "->where('form_id', \$form->id)",
        "->where('submission_id', \$record->id)",
        'DynamicProcurementFormCatalog::UPLOAD_TYPES',
        'normalizedAllowedPath',
        'is_link($absolutePath)',
        '! is_file($absolutePath)',
        '! is_readable($absolutePath)',
        "'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0'",
        "'X-Content-Type-Options' => 'nosniff'",
        "answer outside the configured choices",
    )->and($executionService)->toContain("'canReviewApplications' => \$canReviewApplications")
        ->and(substr_count($user, "'think_tank.procurement.evaluate'"))->toBeGreaterThanOrEqual(2)
        ->and($notPartner)->toContain("if (\$request->expectsJson())", "abort(403");
});
