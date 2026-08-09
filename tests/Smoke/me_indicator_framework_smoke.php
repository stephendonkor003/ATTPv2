<?php

use App\Http\Controllers\MeConfigurationController;
use App\Http\Controllers\MeIndicatorController;
use App\Http\Controllers\MeKnowledgeEvidenceController;
use App\Models\Indicator;
use App\Models\IndicatorLevel;
use App\Models\IndicatorResult;
use App\Models\IndicatorTarget;
use App\Models\IndicatorUnit;
use App\Models\MeKnowledgeEvidenceItem;
use App\Models\MeDisaggregationDimension;
use App\Models\MeRepositoryFolder;
use App\Models\Program;
use App\Models\Project;
use App\Models\ReportingFrequency;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

foreach ([
    ['myb_indicators', 'indicator_code'],
    ['myb_indicators', 'responsible_user_id'],
    ['myb_indicators', 'project_component_id'],
    ['myb_indicators', 'results_level'],
    ['myb_indicators', 'data_collection_method'],
    ['myb_indicators', 'means_of_verification_id'],
    ['me_indicator_targets', 'target_context'],
] as [$table, $column]) {
    if (! Schema::hasColumn($table, $column)) {
        throw new RuntimeException("Missing required {$table}.{$column} column. Run migrations first.");
    }
}

$admin = User::query()
    ->whereHas('role', fn ($query) => $query->where('name', 'System Admin'))
    ->firstOrFail();
$responsiblePerson = User::query()
    ->whereKeyNot($admin->id)
    ->where(function ($query) {
        $query->whereNull('user_type')
            ->orWhere('user_type', '!=', 'funding_partner');
    })
    ->first() ?? $admin;

$unit = IndicatorUnit::query()
    ->whereNotNull('portfolio_id')
    ->where('is_active', true)
    ->firstOrFail();
$frequency = ReportingFrequency::query()
    ->where('portfolio_id', $unit->portfolio_id)
    ->where('is_active', true)
    ->indicatorCadences()
    ->firstOrFail();
$portfolio = Sector::query()->findOrFail($unit->portfolio_id);
$level = IndicatorLevel::query()
    ->where('portfolio_id', $portfolio->id)
    ->where('is_active', true)
    ->first();

$app['auth']->guard()->setUser($admin);
$controller = $app->make(MeIndicatorController::class);
$configurationController = $app->make(MeConfigurationController::class);
$repositoryController = $app->make(MeKnowledgeEvidenceController::class);
$suffix = Str::upper(Str::random(10));

$requestForUser = function (User $user, string $method, string $uri, array $input = []) use ($app): Request {
    $app['auth']->guard()->setUser($user);
    $request = Request::create($uri, $method, $input);
    $request->setUserResolver(fn () => $user);
    $app->instance('request', $request);

    return $request;
};
$requestFor = fn (string $method, string $uri, array $input = []): Request => $requestForUser(
    $admin,
    $method,
    $uri,
    $input
);

$jsonRequestFor = function (string $method, string $uri, array $input = []) use ($requestFor): Request {
    $request = $requestFor($method, $uri, $input);
    $request->headers->set('Accept', 'application/json');
    $request->headers->set('X-Requested-With', 'XMLHttpRequest');

    return $request;
};

$basePayload = [
    'portfolio_id' => $portfolio->id,
    'indicator_code' => 'IND-CLIENT-MUST-NOT-CONTROL',
    'name' => "Framework smoke indicator {$suffix}",
    'definition' => 'Measures whether the simplified results framework stores its complete core profile.',
    'unit_id' => $unit->id,
    'baseline_value' => 12.5,
    'target_value' => 25,
    'extra_target' => 40,
    'frequency_of_reporting_id' => $frequency->id,
    'data_collection_method' => 'Household survey and administrative data extract',
    'results_level' => 'pdo',
    'responsible_user_id' => $responsiblePerson->id,
    'baseline_type' => 'year',
    'baseline_year' => '2026',
    'methodology' => 'Legacy methodology retained during focused edits',
    'notes' => 'Legacy optional notes retained during focused edits',
    'primary_source_type' => 'link',
];
$creationDimensionIds = MeDisaggregationDimension::query()
    ->whereIn('code', ['country', 'priority_theme'])
    ->orderBy('sort_order')
    ->pluck('id')
    ->all();
$basePayload['disaggregation_configuration_present'] = 1;
$basePayload['dimensions'] = $creationDimensionIds;
$basePayload['required_dimensions'] = $creationDimensionIds;

DB::beginTransaction();

try {
    $hierarchyProgram = Program::query()->create([
        'sector_id' => $portfolio->id,
        'name' => "Indicator hierarchy smoke program {$suffix}",
        'description' => 'Temporary programme used to verify portfolio resolution for an indicator owner.',
        'created_by' => $admin->id,
    ]);
    $component = Project::query()->create([
        'program_id' => $hierarchyProgram->id,
        'project_id' => (string) Str::uuid(),
        'name' => "Indicator project component {$suffix}",
        'description' => 'Temporary project component for indicator classification.',
        'created_by' => $admin->id,
    ]);
    $inlineFolderName = "Inline indicator evidence {$suffix}";
    $inlineFolderResponse = $repositoryController->storeFolder($jsonRequestFor(
        'POST',
        '/budget/me/knowledge-and-evidence-repository/folders',
        [
            'indicator_creation' => 1,
            'portfolio_id' => $portfolio->id,
            'name' => $inlineFolderName,
            'description' => 'Created inside the indicator editor without navigating away.',
        ]
    ));
    $inlineFolderData = $inlineFolderResponse->getData(true)['data'] ?? [];
    $inlineFolder = MeRepositoryFolder::query()->findOrFail($inlineFolderData['id'] ?? null);
    if ($inlineFolderResponse->getStatusCode() !== 201
        || (string) $inlineFolder->portfolio_id !== (string) $portfolio->id
        || $inlineFolder->indicators()->exists()
        || ($inlineFolderData['label'] ?? null) !== $inlineFolderName.' (0 documents)') {
        throw new RuntimeException('Inline evidence-folder creation did not return an unlinked selectable folder.');
    }
    $evidenceTitle = "Indicator MOV {$suffix}";
    $repositoryResponse = $repositoryController->store($requestFor(
        'POST',
        '/budget/me/knowledge-and-evidence-repository',
        [
            'folder_id' => $inlineFolder->id,
            'title' => $evidenceTitle,
            'document_type' => 'means_of_verification',
            'external_url' => 'https://example.test/me/mov',
        ]
    ));
    $evidence = MeKnowledgeEvidenceItem::query()->where('title', $evidenceTitle)->firstOrFail();
    if (! str_contains($repositoryResponse->getTargetUrl(), 'knowledge-and-evidence-repository')
        || $evidence->external_url !== 'https://example.test/me/mov') {
        throw new RuntimeException('The Knowledge and Evidence Repository did not persist a selectable MOV.');
    }
    $basePayload['project_component_id'] = $component->id;
    $basePayload['means_of_verification_id'] = $evidence->id;
    $basePayload['means_of_verification_folder_id'] = $inlineFolder->id;
    $foreignPortfolio = Sector::query()->whereKeyNot($portfolio->id)->firstOrFail();
    $foreignProgram = Program::query()->create([
        'sector_id' => $foreignPortfolio->id,
        'name' => "Cross-portfolio smoke program {$suffix}",
        'description' => 'Temporary programme that must be rejected by the selected portfolio boundary.',
        'created_by' => $admin->id,
    ]);
    $foreignUnit = IndicatorUnit::query()->create([
        'portfolio_id' => $foreignPortfolio->id,
        'name' => "Cross-portfolio unit {$suffix}",
        'symbol' => 'CPU',
        'description' => 'Must not be selectable for another portfolio.',
        'sort_order' => 990,
        'is_active' => true,
        'created_by' => $admin->id,
    ]);
    $foreignFrequency = ReportingFrequency::query()->create([
        'portfolio_id' => $foreignPortfolio->id,
        'name' => "Cross-portfolio frequency {$suffix}",
        'code' => "CPF_{$suffix}",
        'interval_unit' => 'month',
        'interval_value' => 1,
        'frequency_in_days' => 30,
        'description' => 'Must not be selectable for another portfolio.',
        'sort_order' => 990,
        'is_active' => true,
        'created_by' => $admin->id,
    ]);
    $portfolioManagerRole = Role::query()->where('name', 'Portfolio Manager')->firstOrFail();
    $portfolioManager = User::query()->create([
        'name' => "Portfolio scope smoke manager {$suffix}",
        'email' => 'portfolio-scope-'.Str::lower($suffix).'@example.test',
        'password' => Str::random(40),
        'role_id' => $portfolioManagerRole->id,
        'user_type' => 'portfolio_manager',
    ]);
    $portfolio->update([
        'portfolio_manager_user_id' => $portfolioManager->id,
        'portfolio_manager_name' => $portfolioManager->name,
        'portfolio_manager_email' => $portfolioManager->email,
    ]);

    $unitResponse = $configurationController->unitsStore($jsonRequestFor(
        'POST',
        '/budget/me/units',
        [
            'portfolio_id' => $portfolio->id,
            'name' => "Inline smoke unit {$suffix}",
            'symbol' => 'ISU',
            'description' => 'Created without leaving the indicator form.',
            'is_active' => 1,
        ]
    ));
    $unitResponseData = $unitResponse->getData(true)['data'] ?? [];
    $inlineUnit = IndicatorUnit::query()->findOrFail($unitResponseData['id'] ?? null);
    if ($unitResponse->getStatusCode() !== 201
        || ! $inlineUnit->is_active
        || (string) $inlineUnit->portfolio_id !== (string) $portfolio->id
        || ($unitResponseData['label'] ?? null) !== "Inline smoke unit {$suffix} (ISU)") {
        throw new RuntimeException('Inline unit creation did not return an active selectable option.');
    }

    $frequencyResponse = $configurationController->frequenciesStore($jsonRequestFor(
        'POST',
        '/budget/me/frequencies',
        [
            'portfolio_id' => $portfolio->id,
            'name' => "Inline smoke frequency {$suffix}",
            'code' => "ISF_{$suffix}",
            'interval_unit' => 'month',
            'interval_value' => 6,
            'description' => 'Created without leaving the indicator form.',
            'is_active' => 1,
        ]
    ));
    $frequencyResponseData = $frequencyResponse->getData(true)['data'] ?? [];
    $inlineFrequency = ReportingFrequency::query()->findOrFail($frequencyResponseData['id'] ?? null);
    if ($frequencyResponse->getStatusCode() !== 201
        || ! $inlineFrequency->is_active
        || (string) $inlineFrequency->portfolio_id !== (string) $portfolio->id
        || $inlineFrequency->frequency_in_days !== 180
        || ($frequencyResponseData['label'] ?? null) !== "Inline smoke frequency {$suffix}") {
        throw new RuntimeException('Inline reporting-frequency creation did not return a selectable cadence.');
    }

    try {
        $configurationController->unitsStore($jsonRequestFor(
            'POST',
            '/budget/me/units',
            [
                'portfolio_id' => $portfolio->id,
                'name' => $inlineUnit->name,
                'is_active' => 1,
            ]
        ));
        throw new RuntimeException('Inline unit creation accepted a duplicate portfolio unit.');
    } catch (ValidationException $exception) {
        if (! array_key_exists('name', $exception->errors())) {
            throw new RuntimeException('Inline duplicate validation was not returned on the unit name.');
        }
    }

    try {
        $configurationController->unitsStore($jsonRequestFor(
            'POST',
            '/budget/me/units',
            [
                'name' => "Unscoped inline unit {$suffix}",
                'is_active' => 1,
            ]
        ));
        throw new RuntimeException('Inline unit creation accepted a record without portfolio scope.');
    } catch (ValidationException $exception) {
        if (! array_key_exists('portfolio_id', $exception->errors())) {
            throw new RuntimeException('Missing inline portfolio scope was not reported correctly.');
        }
    }

    $htmlFallbackResponse = $configurationController->unitsStore($requestFor(
        'POST',
        '/budget/me/units',
        [
            'portfolio_id' => $portfolio->id,
            'name' => "Full-page fallback unit {$suffix}",
            'is_active' => 1,
        ]
    ));
    if ($htmlFallbackResponse->getStatusCode() !== 302
        || ! str_contains($htmlFallbackResponse->getTargetUrl(), '/budget/me/units')) {
        throw new RuntimeException('The full-page unit creation fallback no longer redirects normally.');
    }

    $unit = $inlineUnit;
    $frequency = $inlineFrequency;
    $basePayload['unit_id'] = $unit->id;
    $basePayload['frequency_of_reporting_id'] = $frequency->id;

    $missingPortfolioPayload = $basePayload;
    $missingPortfolioPayload['name'] = "Missing portfolio smoke indicator {$suffix}";
    unset($missingPortfolioPayload['portfolio_id']);
    try {
        $controller->store($requestFor('POST', '/budget/me/indicators', $missingPortfolioPayload));
        throw new RuntimeException('Indicator creation accepted a payload without its mandatory portfolio.');
    } catch (ValidationException $exception) {
        if (! array_key_exists('portfolio_id', $exception->errors())) {
            throw new RuntimeException('Missing indicator portfolio was not reported on portfolio_id.');
        }
    }

    $crossHierarchyPayload = $basePayload;
    $crossHierarchyPayload['name'] = "Cross-hierarchy smoke indicator {$suffix}";
    $crossHierarchyPayload['owner_reference'] = 'program:'.$foreignProgram->id;
    try {
        $controller->store($requestFor('POST', '/budget/me/indicators', $crossHierarchyPayload));
        throw new RuntimeException('Indicator creation accepted a hierarchy owner from another portfolio.');
    } catch (ValidationException $exception) {
        if (($exception->errors()['owner_reference'][0] ?? null) !== 'The selected owner must belong to the selected portfolio.') {
            throw new RuntimeException('Cross-portfolio hierarchy rejection did not use the owner_reference contract.');
        }
    }

    $crossConfigurationPayload = $basePayload;
    $crossConfigurationPayload['name'] = "Cross-configuration smoke indicator {$suffix}";
    $crossConfigurationPayload['unit_id'] = $foreignUnit->id;
    $crossConfigurationPayload['frequency_of_reporting_id'] = $foreignFrequency->id;
    try {
        $controller->store($requestFor('POST', '/budget/me/indicators', $crossConfigurationPayload));
        throw new RuntimeException('Indicator creation accepted unit and frequency records from another portfolio.');
    } catch (ValidationException $exception) {
        if (! array_key_exists('unit_id', $exception->errors())
            || ! array_key_exists('frequency_of_reporting_id', $exception->errors())) {
            throw new RuntimeException('Cross-portfolio configuration rejection did not identify both dependent fields.');
        }
    }

    $unauthorizedPortfolioPayload = $basePayload;
    $unauthorizedPortfolioPayload['name'] = "Unauthorized portfolio smoke indicator {$suffix}";
    $unauthorizedPortfolioPayload['portfolio_id'] = $foreignPortfolio->id;
    try {
        $controller->store($requestForUser(
            $portfolioManager,
            'POST',
            '/budget/me/indicators',
            $unauthorizedPortfolioPayload
        ));
        throw new RuntimeException('Portfolio Manager created an indicator outside the assigned portfolio.');
    } catch (HttpException $exception) {
        if ($exception->getStatusCode() !== 403
            || $exception->getMessage() !== 'You do not have access to the selected portfolio.') {
            throw new RuntimeException('Unassigned portfolio access did not return the expected 403 response.');
        }
    }

    foreach ([
        $missingPortfolioPayload['name'],
        $crossHierarchyPayload['name'],
        $crossConfigurationPayload['name'],
        $unauthorizedPortfolioPayload['name'],
    ] as $rejectedName) {
        if (Indicator::query()->where('name', $rejectedName)->exists()) {
            throw new RuntimeException("Rejected indicator [{$rejectedName}] left a partial record behind.");
        }
    }

    $controller->store($requestFor('POST', '/budget/me/indicators', $basePayload));

    $indicator = Indicator::query()
        ->where('name', $basePayload['name'])
        ->with(['setupTarget', 'disaggregationRequirements.dimension'])
        ->firstOrFail();

    if ($indicator->indicator_code !== $basePayload['indicator_code']) {
        throw new RuntimeException('The authorized user-defined indicator code was not persisted.');
    }
    if ((string) $indicator->responsible_user_id !== (string) $responsiblePerson->id
        || json_decode((string) $indicator->responsible_party, true) !== [(string) $responsiblePerson->id]) {
        throw new RuntimeException('The single responsible person was not persisted compatibly.');
    }
    if ($indicator->definitions !== $basePayload['definition']
        || $indicator->data_collection_method !== $basePayload['data_collection_method']
        || (float) $indicator->extra_target !== 40.0
        || (string) $indicator->project_component_id !== (string) $component->id
        || (string) $indicator->means_of_verification_id !== (string) $evidence->id
        || $indicator->results_level !== 'pdo') {
        throw new RuntimeException('The enhanced indicator profile was not persisted.');
    }
    if ($indicator->indicatorable_type !== Sector::class
        || (string) $indicator->indicatorable_id !== (string) $portfolio->id) {
        throw new RuntimeException('Blank hierarchy owner did not default to the mandatory selected portfolio.');
    }
    if (! $indicator->repositoryFolders()->whereKey($inlineFolder->id)->exists()) {
        throw new RuntimeException('Saving the indicator did not link its inline-created evidence folder.');
    }
    if ($indicator->disaggregationRequirements->count() !== 2
        || $indicator->disaggregationRequirements->contains(fn ($requirement) => ! $requirement->is_required)
        || $indicator->disaggregationRequirements->pluck('dimension.code')->sort()->values()->all() !== ['country', 'priority_theme']) {
        throw new RuntimeException('Indicator creation did not persist its required disaggregation categories.');
    }

    $repositoryView = $repositoryController->index($requestFor(
        'GET',
        '/budget/me/rebuild/knowledge-and-evidence-repository?q='.rawurlencode($evidenceTitle)
    ));
    $repositoryHtml = $repositoryView->with('errors', new ViewErrorBag)->render();
    if (! str_contains($repositoryHtml, $evidenceTitle)
        || ! str_contains($repositoryHtml, 'Linked indicators')
        || $repositoryView->getData()['items']->total() !== 1) {
        throw new RuntimeException('The Knowledge and Evidence Repository did not render the linked MOV.');
    }
    try {
        $repositoryController->destroy($evidence);
        throw new RuntimeException('A repository item linked as an MOV was deleted.');
    } catch (ValidationException $exception) {
        if (! array_key_exists('evidence', $exception->errors())) {
            throw new RuntimeException('Linked MOV deletion did not return the expected validation error.');
        }
    }

    $dimensions = MeDisaggregationDimension::query()
        ->whereIn('code', ['gender', 'age_group', 'geographic_scope'])
        ->pluck('id', 'code');
    $dimensionIds = [
        $dimensions['gender'],
        $dimensions['age_group'],
        $dimensions['geographic_scope'],
    ];
    $controller->updateDisaggregations(
        $requestFor('PUT', "/budget/me/indicators/{$indicator->id}/disaggregations", [
            'dimensions' => $dimensionIds,
            'required_dimensions' => $dimensionIds,
        ]),
        $indicator
    );
    $indicator->load('disaggregationRequirements.dimension');
    if ($indicator->disaggregationRequirements->count() !== 3
        || $indicator->disaggregationRequirements->contains(fn ($requirement) => ! $requirement->is_required)) {
        throw new RuntimeException('The selected combined disaggregation requirements were not persisted.');
    }

    $collectIndicators = new ReflectionMethod($controller, 'collectIndicatorsWithRelations');
    $reportIndicators = $collectIndicators->invoke($controller, (string) $component->id);
    $statusBuilder = new ReflectionMethod($controller, 'buildStatusRow');
    $statusRows = $reportIndicators
        ->map(fn (Indicator $reportIndicator) => $statusBuilder->invoke($controller, $reportIndicator))
        ->keyBy('id');
    $reportBuilder = new ReflectionMethod($controller, 'buildManagementReportRows');
    $reportRows = $reportBuilder->invoke(
        $controller,
        $reportIndicators,
        $statusRows,
        User::query()->pluck('name', 'id'),
        null
    );
    $reportRow = $reportRows->firstWhere('indicator_name', $indicator->name);
    if (($reportRow['project_component'] ?? null) !== $component->project_id.' - '.$component->name
        || ($reportRow['indicator_level'] ?? null) !== 'PDO'
        || ($reportRow['disaggregation'] ?? null) !== 'Gender × Age group × Geographic scope'
        || ($reportRow['means_of_verification'] ?? null) !== $evidence->folder->name
        || ($reportRow['data_collection_method'] ?? null) !== $basePayload['data_collection_method']) {
        throw new RuntimeException('Component, classification, disaggregation, collection method or MOV is missing from management reporting.');
    }

    $setupTarget = $indicator->setupTarget;
    if (! $setupTarget
        || $setupTarget->target_context !== Indicator::SETUP_TARGET_CONTEXT
        || $setupTarget->period_type !== 'custom'
        || $setupTarget->period_label !== 'Framework target'
        || (float) $setupTarget->target_value !== 25.0
        || (string) $setupTarget->unit_id !== (string) $unit->id) {
        throw new RuntimeException('The setup target was not synchronized into me_indicator_targets.');
    }

    $firstCode = $indicator->indicator_code;
    $secondPayload = $basePayload;
    $secondPayload['indicator_code'] = 'IND-SECOND-'.Str::upper(substr($suffix, 0, 8));
    $secondPayload['name'] = "Second framework smoke indicator {$suffix}";
    $secondPayload['owner_reference'] = 'program:'.$hierarchyProgram->id;
    $controller->store($requestFor('POST', '/budget/me/indicators', $secondPayload));
    $secondIndicator = Indicator::query()->where('name', $secondPayload['name'])->with('indicatorable')->firstOrFail();
    if ($secondIndicator->indicator_code === $firstCode) {
        throw new RuntimeException('Two indicators received the same human Indicator ID.');
    }
    if ($secondIndicator->indicatorable_type !== Program::class
        || (string) $secondIndicator->indicatorable_id !== (string) $hierarchyProgram->id) {
        throw new RuntimeException('The selected programme hierarchy owner was not persisted polymorphically.');
    }
    $hierarchyResolver = new ReflectionMethod($controller, 'resolveHierarchyForIndicator');
    $resolvedHierarchy = $hierarchyResolver->invoke($controller, $secondIndicator);
    if (($resolvedHierarchy['portfolio_key'] ?? null) !== strtolower((string) $portfolio->id)
        || ($resolvedHierarchy['portfolio'] ?? null) !== $portfolio->name
        || ($resolvedHierarchy['owner_type'] ?? null) !== 'Program'
        || ! str_contains((string) ($resolvedHierarchy['program'] ?? ''), $hierarchyProgram->name)) {
        throw new RuntimeException('The persisted programme owner did not resolve back to its selected portfolio hierarchy.');
    }

    $originalTargetId = $setupTarget->id;
    $updatePayload = [
        'portfolio_id' => $portfolio->id,
        'indicator_code' => 'IND-UPDATED-'.Str::upper(substr($suffix, 0, 8)),
        'indicator_code_change_reason' => 'Smoke test of the authorized indicator-code audit trail.',
        'name' => "Updated framework smoke indicator {$suffix}",
        'definition' => 'Updated plain-language measurement definition.',
        'unit_id' => $unit->id,
        'baseline_value' => 13,
        'target_value' => 30,
        'frequency_of_reporting_id' => $frequency->id,
        'data_collection_method' => 'Updated survey and administrative data collection method',
        'project_component_id' => $component->id,
        'means_of_verification_id' => $evidence->id,
        'results_level' => 'intermediate_results',
        'responsible_user_id' => $responsiblePerson->id,
    ];
    $controller->update(
        $requestFor('PUT', "/budget/me/indicators/{$indicator->id}", $updatePayload),
        $indicator
    );

    $indicator->refresh()->load('setupTarget');
    if ($indicator->indicator_code !== $updatePayload['indicator_code']) {
        throw new RuntimeException('The authorized indicator-code change was not persisted.');
    }
    if ($indicator->methodology !== $updatePayload['data_collection_method']
        || $indicator->data_collection_method !== $updatePayload['data_collection_method']
        || (float) $indicator->extra_target !== 40.0
        || $indicator->results_level !== 'intermediate_results'
        || $indicator->notes !== $basePayload['notes']
        || $indicator->baseline_year !== $basePayload['baseline_year']
        || $indicator->baseline_type !== $basePayload['baseline_type']
        || (string) $indicator->indicatorable_id !== (string) $portfolio->id) {
        throw new RuntimeException('A focused update erased legacy optional indicator data.');
    }
    if ($indicator->primary_source !== 'link:'.$updatePayload['data_collection_method']) {
        throw new RuntimeException('A focused update did not preserve the legacy data-source type.');
    }
    if ((string) $indicator->setupTarget?->id !== (string) $originalTargetId
        || (float) $indicator->setupTarget?->target_value !== 30.0
        || IndicatorTarget::query()
            ->where('indicator_id', $indicator->id)
            ->where('target_context', Indicator::SETUP_TARGET_CONTEXT)
            ->count() !== 1) {
        throw new RuntimeException('Updating the setup target created duplicates instead of synchronizing it.');
    }

    $controller->storeData(
        $requestFor('POST', "/budget/me/indicators/{$indicator->id}/data", [
            'period_type' => 'custom',
            'period_label' => 'Framework target',
            'target_value' => 99,
            'actual_value' => 20,
            'data_source' => 'Smoke performance entry',
        ]),
        $indicator
    );
    $indicator->refresh()->load('setupTarget');
    if ((float) $indicator->setupTarget?->target_value !== 30.0
        || ! IndicatorTarget::query()
            ->where('indicator_id', $indicator->id)
            ->whereNull('target_context')
            ->where('period_label', 'Framework target')
            ->where('target_value', 99)
            ->exists()) {
        throw new RuntimeException('A periodic data-entry target overwrote the framework setup target.');
    }

    $reportedResult = IndicatorResult::query()
        ->where('indicator_id', $indicator->id)
        ->where('period_label', 'Framework target')
        ->latest()
        ->firstOrFail();
    $indicator->load('targets');
    $targetMatcher = new ReflectionMethod($controller, 'findMatchingTargetForResult');
    $matchedPeriodicTarget = $targetMatcher->invoke($controller, $indicator, $reportedResult);
    if ((float) $matchedPeriodicTarget?->target_value !== 99.0
        || $matchedPeriodicTarget?->target_context !== null) {
        throw new RuntimeException('Performance reporting matched the framework target instead of its periodic target.');
    }

    $invalidName = "Invalid framework smoke indicator {$suffix}";
    try {
        $controller->store($requestFor('POST', '/budget/me/indicators', ['name' => $invalidName]));
        throw new RuntimeException('An incomplete core indicator profile was accepted.');
    } catch (ValidationException $exception) {
        $missingFields = array_diff([
            'portfolio_id',
            'project_component_id',
            'results_level',
            'definition',
            'unit_id',
            'baseline_value',
            'target_value',
            'frequency_of_reporting_id',
            'data_collection_method',
            'means_of_verification_folder_id',
            'responsible_user_id',
        ], array_keys($exception->errors()));

        if ($missingFields !== []) {
            throw new RuntimeException('Core field validation is missing: '.implode(', ', $missingFields));
        }
    }
    if (Indicator::query()->where('name', $invalidName)->exists()) {
        throw new RuntimeException('Validation left a partial indicator record behind.');
    }

    $registrySearch = strtolower((string) $indicator->indicator_code);
    $indexView = $controller->index($requestFor(
        'GET',
        '/budget/me/indicators?q='.rawurlencode($registrySearch).'&component_id='.$component->id,
        ['q' => $registrySearch, 'component_id' => $component->id]
    ));
    $viewData = $indexView->getData();
    foreach ([
        'indicators',
        'editingIndicator',
        'editingOwnerReference',
        'editingPortfolioId',
        'ownerPortfolioMap',
        'ownerComponentMap',
        'editingResponsibleUserIds',
        'editingPrimarySourceValue',
        'editingDataCollectionMethod',
        'editingTargetValue',
        'users',
        'units',
        'frequencies',
        'portfolios',
        'programs',
        'projects',
        'activities',
        'subActivities',
        'repositoryFolders',
        'componentOptions',
        'componentCounts',
        'componentFilter',
        'disaggregationDimensions',
        'summary',
        'search',
        'showForm',
        'ownerRequired',
        'frequencyIntervalOptions',
    ] as $viewVariable) {
        if (! array_key_exists($viewVariable, $viewData)) {
            throw new RuntimeException("The lightweight index contract is missing {$viewVariable}.");
        }
    }
    foreach (['statusRows', 'managementReportRows', 'dataEntryRows', 'reviewRows'] as $removedVariable) {
        if (array_key_exists($removedVariable, $viewData)) {
            throw new RuntimeException("The indicator registry still eagerly loads {$removedVariable}.");
        }
    }
    if ($viewData['indicators']->count() !== 1
        || (string) $viewData['indicators']->first()->id !== (string) $indicator->id
        || $viewData['search'] !== $registrySearch
        || (string) $viewData['componentFilter'] !== (string) $component->id
        || $viewData['showForm'] !== false
        || ($viewData['summary']['complete'] ?? 0) < 2
        || ($viewData['summary']['needs_attention'] ?? -1) < 0
        || ! $viewData['users']->isEmpty()
        || ! $viewData['units']->isEmpty()) {
        throw new RuntimeException('Search, pagination, or deferred form loading is not working.');
    }

    $editView = $controller->index($requestFor(
        'GET',
        "/budget/me/indicators?edit={$indicator->id}"
    ));
    $editData = $editView->getData();
    if ($editData['showForm'] !== true
        || (string) $editData['editingIndicator']?->id !== (string) $indicator->id
        || (string) $editData['editingPortfolioId'] !== (string) $portfolio->id
        || (float) $editData['editingTargetValue'] !== 30.0
        || $editData['units']->isEmpty()
        || $editData['frequencies']->isEmpty()
        || $editData['users']->isEmpty()
        || ! $editData['units']->contains('id', $inlineUnit->id)
        || ! $editData['frequencies']->contains('id', $inlineFrequency->id)
        || ! $editData['repositoryFolders']->contains('id', $evidence->folder_id)
        || ($editData['frequencyIntervalOptions']['month'] ?? null) !== 'Month') {
        throw new RuntimeException('The create/edit form contract was not loaded on demand.');
    }

    $registryHtml = $indexView->with('errors', new ViewErrorBag)->render();
    $editHtml = $editView->with('errors', new ViewErrorBag)->render();
    foreach ([
        'Portfolio',
        'Project component',
        'Indicator ID',
        'Indicator name',
        'Results level',
        'Definition',
        'Unit of measurement',
        'Surplus target',
        'Required disaggregation',
        'ATTP priority theme',
        'Baseline',
        'Target',
        'Reporting frequency',
        'Data Collection Method/Data Source',
        'Means of Verification',
        'Responsible person',
    ] as $requiredLabel) {
        if (! str_contains($editHtml, $requiredLabel)) {
            throw new RuntimeException("The focused indicator form is missing {$requiredLabel}.");
        }
    }
    foreach ([
        'Aggregation across reporting periods',
        'Cross-think-tank consolidation',
        'name="aggregation_method"',
        'name="organization_rollup_method"',
    ] as $removedAggregationControl) {
        if (str_contains($editHtml, $removedAggregationControl)) {
            throw new RuntimeException("The focused indicator form still exposes {$removedAggregationControl}.");
        }
    }
    foreach ([
        'New unit',
        'New folder',
        'indicatorUnitCreateModal',
        'indicatorEvidenceFolderCreateModal',
        'indicatorFrequencyCreateModal',
        route('budget.me-configuration.units.store'),
        route('budget.me.knowledge-evidence.folders.store'),
        route('budget.me-configuration.frequencies.store'),
        'data-inline-config-form',
        'data-inline-selection-status',
    ] as $inlineCreationMarker) {
        if (! str_contains($editHtml, $inlineCreationMarker)) {
            throw new RuntimeException("The inline configuration UI is missing {$inlineCreationMarker}.");
        }
    }
    $indicatorFormStart = strpos($editHtml, 'data-indicator-form');
    $indicatorFormEnd = strpos($editHtml, '</form>', $indicatorFormStart ?: 0);
    $indicatorFormHtml = $indicatorFormStart !== false && $indicatorFormEnd !== false
        ? substr($editHtml, $indicatorFormStart, $indicatorFormEnd - $indicatorFormStart)
        : '';
    if (! preg_match(
        '/<select(?=[^>]*id="indicator-portfolio")(?=[^>]*name="portfolio_id")(?=[^>]*\srequired(?:\s|>))(?=[^>]*data-indicator-portfolio)[^>]*>/s',
        $indicatorFormHtml
    )
        || ! str_contains($indicatorFormHtml, 'id="indicator-portfolio-help"')
        || ! str_contains($indicatorFormHtml, 'data-indicator-owner')
        || ! str_contains($indicatorFormHtml, 'data-portfolio-id="'.$portfolio->id.'"')) {
        throw new RuntimeException('The indicator form does not expose its required portfolio-first UI contract.');
    }
    $frequencySelectStart = strpos($indicatorFormHtml, 'id="indicator-frequency"');
    $frequencySelectEnd = strpos($indicatorFormHtml, '</select>', $frequencySelectStart ?: 0);
    $frequencySelectHtml = $frequencySelectStart !== false && $frequencySelectEnd !== false
        ? substr($indicatorFormHtml, $frequencySelectStart, $frequencySelectEnd - $frequencySelectStart)
        : '';
    foreach (['Monthly', 'Quarterly', 'Semi-Annual', 'Annual'] as $allowedCadence) {
        if (! str_contains($frequencySelectHtml, $allowedCadence)) {
            throw new RuntimeException("The indicator frequency selector is missing {$allowedCadence}.");
        }
    }
    foreach (['Second', 'Minute', 'Hour', 'Week', 'Once', 'Quinquennial'] as $forbiddenCadence) {
        if (str_contains($frequencySelectHtml, '>'.$forbiddenCadence.'<')) {
            throw new RuntimeException("The indicator frequency selector still exposes {$forbiddenCadence}.");
        }
    }
    $unitSelectStart = strpos($indicatorFormHtml, 'id="indicator-unit"');
    $unitSelectEnd = strpos($indicatorFormHtml, '</select>', $unitSelectStart ?: 0);
    $unitSelectHtml = $unitSelectStart !== false && $unitSelectEnd !== false
        ? substr($indicatorFormHtml, $unitSelectStart, $unitSelectEnd - $unitSelectStart)
        : '';
    if (! str_contains($unitSelectHtml, $inlineUnit->name)
        || str_contains($unitSelectHtml, $inlineUnit->name.' ('.$inlineUnit->symbol.')')
        || str_contains($unitSelectHtml, $inlineUnit->name.' — '.$portfolio->name)) {
        throw new RuntimeException('The indicator unit selector is not displaying the exact configured unit name.');
    }
    foreach (['name="dimensions[]"', 'name="required_dimensions[]"', 'disaggregation_configuration_present'] as $marker) {
        if (! str_contains($indicatorFormHtml, $marker)) {
            throw new RuntimeException("The indicator form is missing disaggregation marker {$marker}.");
        }
    }
    if (str_contains($indicatorFormHtml, 'Approved aggregation method')) {
        throw new RuntimeException('The indicator form still confuses period aggregation with disaggregation.');
    }
    if (str_contains($indicatorFormHtml, '>Methodology<')
        || str_contains($indicatorFormHtml, '>Primary Source Type<')) {
        throw new RuntimeException('Legacy Methodology or Primary Source Type labels remain in the indicator editor.');
    }
    $unitModalStart = strpos($editHtml, 'id="indicatorUnitCreateModal"');
    if ($indicatorFormStart === false
        || $indicatorFormEnd === false
        || $unitModalStart === false
        || $unitModalStart < $indicatorFormEnd) {
        throw new RuntimeException('An inline configuration modal was nested inside the indicator form.');
    }
    if (! str_contains($registryHtml, $indicator->indicator_code)
        || ! str_contains($registryHtml, 'Indicator register')
        || ! str_contains($registryHtml, 'Disaggregation')
        || ! str_contains($registryHtml, 'Gender')) {
        throw new RuntimeException('The focused indicator register did not render the saved indicator.');
    }

    echo "ME_INDICATOR_FRAMEWORK_OK\n";
} finally {
    DB::rollBack();
}
