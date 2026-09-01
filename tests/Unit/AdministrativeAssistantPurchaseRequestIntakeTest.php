<?php

$purchaseRequestIntakeRoot = dirname(__DIR__, 2);

it('registers the assistant purchase request intake workspace and navigation', function () use ($purchaseRequestIntakeRoot) {
    $routes = file_get_contents($purchaseRequestIntakeRoot.'/routes/web.php');
    $layout = file_get_contents($purchaseRequestIntakeRoot.'/resources/views/layouts/administrative-assistant.blade.php');
    $redirectMiddleware = file_get_contents($purchaseRequestIntakeRoot.'/app/Http/Middleware/RedirectAdministrativeAssistantToPortal.php');

    expect($routes)
        ->toContain('AdministrativeAssistantPurchaseRequestController')
        ->toContain("Route::middleware(['auth', 'administrative.assistant'])")
        ->toContain("->name('purchase-requests.create')")
        ->toContain("->name('purchase-requests.store')")
        ->toContain("->name('purchase-requests.show')")
        ->toContain("->name('purchase-requests.documents.download')");

    expect($layout)
        ->toContain("route('administrative-assistant.purchase-requests.create')")
        ->toContain("request()->routeIs('administrative-assistant.purchase-requests.*')")
        ->toContain('Create PR');

    expect($redirectMiddleware)
        ->toContain("'administrative-assistant.*'")
        ->toContain('Administrative Assistant accounts can only use their focused workspace.');
});

it('keeps assistant submissions as server-owned intakes instead of finance commitments', function () use ($purchaseRequestIntakeRoot) {
    $controller = file_get_contents($purchaseRequestIntakeRoot.'/app/Http/Controllers/AdministrativeAssistantPurchaseRequestController.php');
    $intake = file_get_contents($purchaseRequestIntakeRoot.'/app/Models/PurchaseRequestIntake.php');
    $migration = file_get_contents($purchaseRequestIntakeRoot.'/database/migrations/2026_09_01_000001_create_purchase_request_intakes.php');

    expect($controller)
        ->toContain('PurchaseRequestIntake::create([')
        ->toContain("'reference_no' => PurchaseRequestIntake::generateReference()")
        ->toContain("'created_by' => \$user->id")
        ->toContain("'governance_node_id' => \$user->governance_node_id ?: null")
        ->toContain("'status' => PurchaseRequestIntake::STATUS_SUBMITTED")
        ->toContain('DB::transaction(')
        ->not->toContain('PurchaseRequest::create(')
        ->not->toContain('BudgetCommitment::create(');

    expect($intake)
        ->toContain("public const STATUS_SUBMITTED = 'submitted'")
        ->toContain("public const STATUS_CONVERTED = 'converted'")
        ->toContain("'APR-'.now()->format('Y').'-'.Str::upper(Str::random(6))")
        ->toContain("return \$this->belongsTo(User::class, 'created_by')")
        ->toContain("return \$this->hasMany(PurchaseRequestIntakeItem::class, 'intake_id')")
        ->toContain("return \$this->hasMany(PurchaseRequestIntakeDocument::class, 'intake_id')");

    expect($migration)
        ->toContain("Schema::create('purchase_request_intakes'")
        ->toContain("Schema::create('purchase_request_intake_items'")
        ->toContain("Schema::create('purchase_request_intake_documents'")
        ->toContain("->constrained('users')")
        ->toContain("->constrained('myb_purchase_requests')")
        ->toContain("->unique('converted_purchase_request_id', 'pr_intakes_converted_pr_unique')")
        ->toContain('->cascadeOnDelete()');
});

it('enforces intake ownership and bounded private document storage', function () use ($purchaseRequestIntakeRoot) {
    $controller = file_get_contents($purchaseRequestIntakeRoot.'/app/Http/Controllers/AdministrativeAssistantPurchaseRequestController.php');

    expect($controller)
        ->toContain("->where('created_by', \$request->user()->id)")
        ->toContain('assertOwnedByCurrentUser($request, $intake)')
        ->toContain('(string) $intake->created_by === (string) $request->user()->id')
        ->toContain('(string) $document->intake_id === (string) $intake->id')
        ->toContain("Str::startsWith(str_replace('\\\\', '/', \$document->file_path), \$expectedDirectory)")
        ->toContain("Storage::disk('local')")
        ->toContain("'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0'")
        ->toContain("'X-Content-Type-Options' => 'nosniff'")
        ->toContain("'documents' => ['nullable', 'array', 'max:'.self::MAX_DOCUMENTS]")
        ->toContain('MAX_COMBINED_DOCUMENT_SIZE_BYTES')
        ->toContain("Storage::disk('local')->delete(\$storedPaths)")
        ->not->toContain("Storage::disk('public')");
});

it('hands submitted intakes to scoped finance conversion exactly once', function () use ($purchaseRequestIntakeRoot) {
    $purchaseRequestController = file_get_contents($purchaseRequestIntakeRoot.'/app/Http/Controllers/PurchaseRequestController.php');
    $commitmentController = file_get_contents($purchaseRequestIntakeRoot.'/app/Http/Controllers/BudgetCommitmentController.php');
    $purchaseRequestModel = file_get_contents($purchaseRequestIntakeRoot.'/app/Models/PurchaseRequest.php');
    $financeIndex = file_get_contents($purchaseRequestIntakeRoot.'/resources/views/finance/purchase-requests/index.blade.php');
    $financeForm = file_get_contents($purchaseRequestIntakeRoot.'/resources/views/finance/commitments/create.blade.php');
    $financeShow = file_get_contents($purchaseRequestIntakeRoot.'/resources/views/finance/purchase-requests/show.blade.php');

    expect($purchaseRequestController)
        ->toContain("->where('status', PurchaseRequestIntake::STATUS_SUBMITTED)")
        ->toContain('assertPurchaseRequestIntakeInScope($intake)')
        ->toContain('(string) $document->intake_id === (string) $intake->id')
        ->toContain("Str::startsWith(str_replace('\\\\', '/', \$document->file_path), \$expectedDirectory)")
        ->toContain("Storage::disk('local')");

    expect($commitmentController)
        ->toContain("'purchase_request_intake_id'    => 'nullable|uuid|exists:purchase_request_intakes,id'")
        ->toContain('abort_unless(Str::isUuid($intakeId), 404)')
        ->toContain('lockForUpdate()')
        ->toContain("'status' => PurchaseRequestIntake::STATUS_CONVERTED")
        ->toContain("'converted_purchase_request_id' => \$purchaseRequest->id")
        ->toContain("'converted_by' => Auth::id()")
        ->toContain("'converted_at' => now()");

    expect($financeIndex)
        ->toContain('Assistant PR intakes')
        ->toContain("route('finance.purchase-requests.create', ['intake' => \$intake->id])")
        ->toContain('Complete PR');

    expect($financeForm)
        ->toContain('Complete Assistant PR Intake')
        ->toContain('name="purchase_request_intake_id"')
        ->toContain("route('finance.purchase-request-intakes.documents.download'");

    expect($purchaseRequestModel)
        ->toContain('public function sourceIntake(): HasOne')
        ->toContain("return \$this->hasOne(PurchaseRequestIntake::class, 'converted_purchase_request_id')");

    expect($financeShow)
        ->toContain('Administrative Assistant intake')
        ->toContain('Original supporting documents');
});
