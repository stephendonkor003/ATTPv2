<?php

 use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CORE / AUTH / GENERAL CONTROLLERS
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\{
    DashboardController,
    LandingPageController,
    LanguageController,
    ProfileController,
    ChangePasswordController,
    UserController,
    PrescreeningTemplateController,
    PrescreeningCriterionController,
    PrescreeningAssignmentController,
    PrescreeningEvaluationController,
    PrescreeningUserAssignmentController,
    EvaluationPanelPdfController,
    PrescreeningReportController,
    EvaluationReportController,
    SystemAuditController,

};

/*
|--------------------------------------------------------------------------
| PUBLIC / EXTERNAL ACCESS
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\{
    HrPublicController,
    PublicCheckController,
};

/*
|--------------------------------------------------------------------------
| HR & RECRUITMENT MODULE
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\{
    HrController,
    ApplicantController,
    AssignmentController,
    SiteVisitEvaluationController,
};

/*
|--------------------------------------------------------------------------
| EVALUATION & REVIEW MODULES
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\{
    EvaluationController,

    ReportController,
};

/*
|--------------------------------------------------------------------------
| FINANCIAL & COMMITTEE MODULES
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\{
    FinancialController,
    CommitteeController,
    CommitteeMemberController,
    CategoryController,
    BidController,
};

/*
|--------------------------------------------------------------------------
| THINK DATASETS / RESEARCH
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\{
    ThinkDatasetController,
};

/*
|--------------------------------------------------------------------------
| BUDGET & FINANCE MODULE
|--------------------------------------------------------------------------
*/
	use App\Http\Controllers\{
	    SectorController,
	    ProgramController,
	    ProjectController,
	    ActivityController,
	    SubActivityController,
	    // AllocationController,
	    AllocationSummaryController,
	    BudgetAllocationController,
	    BudgetCommitmentController,
	    PurchaseRequestController,
	    BudgetReportController,
	    ProjectBudgetController,
	};

/*
|--------------------------------------------------------------------------
| FUNDING & DEPARTMENTS
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\{
    DepartmentController,
    FunderController,
    ProgramFundingController,
    ProgramFundingDocumentController,
    MasterDashboard,
    GovernanceStructureController,
    EvaluationAssignmentController,
};

use App\Http\Controllers\Vendor\{
    VendorPortalController,
    VendorManagementController,
    VendorRequestManagementController,
    VendorProcurementController,
    VendorCategoryController,
    VendorInvoiceController,
    VendorDisbursementController,
};

/*
|--------------------------------------------------------------------------
| SYSTEM / RBAC MANAGEMENT
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\System\{
    RoleController,
    PermissionController,
    UserAccessController,
};

/*
|--------------------------------------------------------------------------
| PROCUREMENT MODULE (DYNAMIC, FORM-BASED)
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Procurement\{
    ProcurementController,
    ProcurementWorkflowController,
    DynamicFormController,
    FormSubmissionController,
    PrescreeningController,
    EvaluationController as ProcurementEvaluationController,
    ProcurementPermissionController,
    ProcurementAuditController,
    DynamicFormFieldController,
    ProcurementFormAssignmentController,
    PublicProcurementController,
    ProcurementProgramPlanController,
    ProcurementSubmissionController,
    ProcurementPlanController,
    ProcurementInvoiceController,
};

use App\Http\Controllers\Procurement\Settings\{
    GeographicController,
    MethodPlannedController,
    StageController as ProcurementStageController,
    StatusController as ProcurementSettingsStatusController,
    StepStageController,
    StepApprovalController,
};

/*
|--------------------------------------------------------------------------
| LANGUAGE SWITCHING ROUTES
|--------------------------------------------------------------------------
*/
Route::post('/language/switch/{locale}', [LanguageController::class, 'switch'])->name('language.switch');
Route::get('/language/current', [LanguageController::class, 'current'])->name('language.current');
Route::get('/language/available', [LanguageController::class, 'available'])->name('language.available');

Route::middleware(['auth', 'verified', 'not.funding.partner'])
    ->prefix('system')
    ->name('system.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | USERS MANAGEMENT
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:users.manage')->prefix('users')->name('users.')->group(function () {

            Route::get('/', [UserAccessController::class, 'index'])
                ->name('index');

            Route::get('/create', [UserAccessController::class, 'create'])
                ->name('create');

            Route::post('/', [UserAccessController::class, 'store'])
                ->name('store');

            Route::get('/{user}/edit', [UserAccessController::class, 'edit'])
                ->name('edit');

            Route::put('/{user}', [UserAccessController::class, 'update'])
                ->name('update');

            Route::delete('/{user}', [UserAccessController::class, 'destroy'])
                ->name('destroy');

         /* ===============================
         | ✅ ADD THIS ROUTE
         | Inline Role Update
         =============================== */
        Route::put('/{user}/role', [UserAccessController::class, 'updateRole'])
            ->name('role.update');

        Route::post('/{user}/reset-password', [UserAccessController::class, 'resetPassword'])
            ->name('reset-password');


            Route::get('/{user}/permissions', [UserAccessController::class, 'permissions'])
                ->name('permissions');

            Route::post('/{user}/permissions', [UserAccessController::class, 'syncPermissions'])
                ->name('permissions.sync');

        });


        /*
        |--------------------------------------------------------------------------
        | ROLES MANAGEMENT
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:roles.manage')->prefix('roles')->name('roles.')->group(function () {

            Route::get('/', [RoleController::class, 'index'])
                ->name('index');

            Route::get('/create', [RoleController::class, 'create'])
                ->name('create');

            Route::post('/', [RoleController::class, 'store'])
                ->name('store');

            Route::get('/{role}/edit', [RoleController::class, 'edit'])
                ->name('edit');

            Route::put('/{role}', [RoleController::class, 'update'])
                ->name('update');
        });

        /*
        |--------------------------------------------------------------------------
        | PERMISSIONS MANAGEMENT
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:permissions.manage')->prefix('permissions')->name('permissions.')->group(function () {

            Route::get('/', [PermissionController::class, 'index'])
                ->name('index');

            Route::get('/{role}/assign', [PermissionController::class, 'assign'])
                ->name('assign');

            Route::post('/{role}/assign', [PermissionController::class, 'storeAssign'])
                ->name('assign.store');
        });
    });




/*
|--------------------------------------------------------------------------
| PUBLIC JOB APPLICATION ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('careers')->name('careers.')->group(function () {

    // Careers listing page
    Route::get('/', [HrPublicController::class, 'index'])
        ->name('index');

    // Store job application (PUBLIC)
    Route::post('/apply', [HrPublicController::class, 'storeApplication'])
        ->middleware('throttle:30,1')
        ->name('apply.store');

});


  Route::middleware(['auth', 'not.funding.partner'])
    ->prefix('hr')
    ->name('hr.')
    ->group(function () {

        /* =====================================================
         | POSITIONS
         ===================================================== */

        // VIEW
        Route::get('positions', [HrController::class, 'positions'])
            ->middleware('permission:hrm.positions.view')
            ->name('positions.index');

        // CREATE
        Route::post('positions', [HrController::class, 'storePosition'])
            ->middleware('permission:hrm.positions.create')
            ->name('positions.store');

        // UPDATE
        Route::put('positions/{position}', [HrController::class, 'updatePosition'])
            ->middleware('permission:hrm.positions.edit')
            ->name('positions.update');

        // DELETE
        Route::delete('positions/{position}', [HrController::class, 'destroyPosition'])
            ->middleware('permission:hrm.positions.delete')
            ->name('positions.destroy');


        /* =====================================================
         | VACANCIES
         ===================================================== */

        // VIEW
        Route::get('vacancies', [HrController::class, 'vacancies'])
            ->middleware('permission:hrm.vacancies.view')
            ->name('vacancies.index');

        Route::get('vacancies/{vacancy}/applicants', [HrController::class, 'applicants'])
            ->middleware('permission:hrm.vacancies.view')
            ->name('vacancies.applicants');

        // CREATE / MANAGE
        Route::post('vacancies', [HrController::class, 'storeVacancy'])
            ->middleware('permission:hrm.vacancies.create')
            ->name('vacancies.store');

        // WORKFLOW
        Route::post('vacancies/{vacancy}/submit', [HrController::class, 'submitVacancyForApproval'])
            ->middleware('permission:hrm.vacancies.submit')
            ->name('vacancies.submit');

        Route::post('vacancies/{vacancy}/approve', [HrController::class, 'approveVacancy'])
            ->middleware('permission:hr.vacancies.approve')
            ->name('vacancies.approve');

        Route::post('vacancies/{vacancy}/publish', [HrController::class, 'publishVacancy'])
            ->middleware('permission:hr.vacancies.approve')
            ->name('vacancies.publish');

        Route::post('vacancies/{vacancy}/close', [HrController::class, 'closeVacancy'])
            ->middleware('permission:hr.vacancies.approve')
            ->name('vacancies.close');


        /* =====================================================
         | APPLICANTS
         ===================================================== */

        // VIEW
        Route::get('applicants/{applicant}', [HrController::class, 'showApplicant'])
            ->middleware('permission:hr.applicants.view')
            ->name('applicants.show');

        Route::get('applicants/{applicant}/files/{which}', [HrController::class, 'downloadApplicantFile'])
            ->middleware('permission:hr.applicants.view')
            ->name('applicants.files');

        // MANAGEMENT ACTIONS
        Route::post('applicants/{applicant}/shortlist', [HrController::class, 'shortlistApplicant'])
            ->middleware('permission:hr.applicants.hire')
            ->name('applicants.shortlist');

        Route::post('applicants/{applicant}/reject', [HrController::class, 'rejectApplicant'])
            ->middleware('permission:hr.applicants.hire')
            ->name('applicants.reject');

        Route::post(
            'applicants/{applicant}/schedule-interview',
            [HrController::class, 'scheduleInterview']
        )
            ->middleware('permission:hr.applicants.manage')
            ->name('applicants.interview');

        // HIRE
        Route::post('applicants/{applicant}/hire', [HrController::class, 'hireApplicant'])
            ->middleware('permission:hr.applicants.hire')
            ->name('applicants.hire');


        /* =====================================================
         | AI SCORING
         ===================================================== */

        Route::post('applicants/{applicant}/score-ai', [HrController::class, 'scoreApplicantAI'])
            ->middleware('permission:hr.ai.score')
            ->name('applicants.score');

        Route::post('vacancies/{vacancy}/bulk-score', [HrController::class, 'bulkScoreApplicants'])
            ->middleware('permission:hr.ai.score')
            ->name('vacancies.bulkScore');


        /* =====================================================
         | ANALYTICS
         ===================================================== */

        Route::get('analytics', [HrController::class, 'analytics'])
            ->middleware('permission:hr.analytics.view')
            ->name('analytics');
    });




Route::middleware(['auth', 'not.funding.partner', 'permission:finance.access'])
    ->prefix('finance')
    ->name('finance.')
    ->group(function () {



       /* =====================================================
         | AJAX ENDPOINTS (USED BY CREATE COMMITMENT PAGE)
         ===================================================== */

        // Load projects
        Route::get('commitments/ajax/projects',
            [BudgetCommitmentController::class, 'projects']
        );

        // Load activities by project
        Route::get('commitments/ajax/activities/{project}',
            [BudgetCommitmentController::class, 'activities']
        );

        // Load sub-activities by activity
        Route::get('commitments/ajax/sub-activities/{activity}',
            [BudgetCommitmentController::class, 'subActivities']
        );

	        // Load allocation years
	        Route::get('commitments/ajax/allocation-years/{level}/{id}',
	            [BudgetCommitmentController::class, 'allocationYears']
	        );

	        // Allocation breakdown (allocated/committed/remaining by year)
	        Route::get('commitments/ajax/allocation-breakdown/{level}/{id}',
	            [BudgetCommitmentController::class, 'allocationBreakdown']
	        );

        // Remaining budget
        Route::get('commitments/ajax/remaining-budget',
            [BudgetCommitmentController::class, 'remainingBudget']
        );

        // Resources by category
        Route::get('resources/ajax/resources/{category}',
            [BudgetCommitmentController::class, 'resourcesByCategory']
        );

        Route::post('commitments/{commitment}/submit',
            [BudgetCommitmentController::class, 'submit']
        )->name('commitments.submit');



        Route::post('commitments/{commitment}/approve',
            [BudgetCommitmentController::class, 'approve']
        )->name('commitments.approve');

        Route::post('commitments/{commitment}/cancel',
            [BudgetCommitmentController::class, 'cancel']
        )->name('commitments.cancel');
        /* =====================================================
         | RESOURCES
         ===================================================== */

        Route::get('resources/categories', [BudgetCommitmentController::class, 'resourceCategories'])
            ->middleware('permission:finance.resources.view')
            ->name('resources.categories.index');

        Route::post('resources/categories', [BudgetCommitmentController::class, 'storeResourceCategory'])
            ->middleware('permission:finance.resources.create')
            ->name('resources.categories.store');

        Route::put('resources/categories/{category}', [BudgetCommitmentController::class, 'updateResourceCategory'])
            ->middleware('permission:finance.resources.edit')
            ->name('resources.categories.update');

        Route::delete('resources/categories/{category}', [BudgetCommitmentController::class, 'destroyResourceCategory'])
            ->middleware('permission:finance.resources.delete')
            ->name('resources.categories.destroy');

        Route::get('resources/items', [BudgetCommitmentController::class, 'resources'])
            ->middleware('permission:finance.resources.view')
            ->name('resources.items.index');

        Route::post('resources/items', [BudgetCommitmentController::class, 'storeResource'])
            ->middleware('permission:finance.resources.create')
            ->name('resources.items.store');

        Route::put('resources/items/{resource}', [BudgetCommitmentController::class, 'updateResource'])
            ->middleware('permission:finance.resources.edit')
            ->name('resources.items.update');

        Route::delete('resources/items/{resource}', [BudgetCommitmentController::class, 'destroyResource'])
            ->middleware('permission:finance.resources.delete')
            ->name('resources.items.destroy');




          Route::get('execution/dashboard', [MasterDashboard::class, 'executionDashboard'])
            ->middleware('permission:finance.executions.view')
            ->name('execution.dashboard');




        Route::get('execution/data',
            [BudgetCommitmentController::class, 'executionData']
        )->name('execution.data');


        /* =====================================================
         | FUNDERS
         ===================================================== */

        Route::get('funders', [FunderController::class, 'index'])
            ->middleware('permission:finance.funders.view')
            ->name('funders.index');

        Route::get('funders/create', [FunderController::class, 'create'])
            ->middleware('permission:finance.funders.create')
            ->name('funders.create');

        Route::post('funders', [FunderController::class, 'store'])
            ->middleware('permission:finance.funders.create')
            ->name('funders.store');

        Route::get('funders/{funder}', [FunderController::class, 'show'])
            ->middleware('permission:finance.funders.view')
            ->name('funders.show');

        Route::get('funders/{funder}/edit', [FunderController::class, 'edit'])
            ->middleware('permission:finance.funders.edit')
            ->name('funders.edit');

        Route::put('funders/{funder}', [FunderController::class, 'update'])
            ->middleware('permission:finance.funders.edit')
            ->name('funders.update');


        /* =====================================================
         | DEPARTMENTS
         ===================================================== */

        /* =====================================================
         | GOVERNANCE STRUCTURE
         ===================================================== */

        Route::get('governance-structure', [GovernanceStructureController::class, 'index'])
            ->middleware('permission:finance.governance_structure.view')
            ->name('governance.index');

        Route::post('governance-structure/nodes', [GovernanceStructureController::class, 'storeNode'])
            ->middleware('permission:finance.governance_structure.create')
            ->name('governance.nodes.store');

        Route::put('governance-structure/nodes/{node}', [GovernanceStructureController::class, 'updateNode'])
            ->middleware('permission:finance.governance_structure.edit')
            ->name('governance.nodes.update');

        Route::delete('governance-structure/nodes/{node}', [GovernanceStructureController::class, 'destroyNode'])
            ->middleware('permission:finance.governance_structure.delete')
            ->name('governance.nodes.destroy');

        Route::post('governance-structure/lines', [GovernanceStructureController::class, 'storeLine'])
            ->middleware('permission:finance.governance_structure.create')
            ->name('governance.lines.store');

        Route::put('governance-structure/lines/{line}', [GovernanceStructureController::class, 'updateLine'])
            ->middleware('permission:finance.governance_structure.edit')
            ->name('governance.lines.update');

        Route::delete('governance-structure/lines/{line}', [GovernanceStructureController::class, 'destroyLine'])
            ->middleware('permission:finance.governance_structure.delete')
            ->name('governance.lines.destroy');

        Route::post('governance-structure/assignments', [GovernanceStructureController::class, 'storeAssignment'])
            ->middleware('permission:finance.governance_structure.create')
            ->name('governance.assignments.store');

        Route::put('governance-structure/assignments/{assignment}', [GovernanceStructureController::class, 'updateAssignment'])
            ->middleware('permission:finance.governance_structure.edit')
            ->name('governance.assignments.update');

        Route::delete('governance-structure/assignments/{assignment}', [GovernanceStructureController::class, 'destroyAssignment'])
            ->middleware('permission:finance.governance_structure.delete')
            ->name('governance.assignments.destroy');

         /* ===================== DEPARTMENTS ===================== */

        Route::get('departments', [DepartmentController::class, 'index'])
            ->middleware('permission:finance.departments.view')
            ->name('departments.index');

        /* CREATE — MUST COME BEFORE {department} */
        Route::get('departments/create', [DepartmentController::class, 'create'])
            ->middleware('permission:finance.departments.create')
            ->name('departments.create');

        Route::post('departments', [DepartmentController::class, 'store'])
            ->middleware('permission:finance.departments.create')
            ->name('departments.store');

        /* SHOW */
        Route::get('departments/{department}', [DepartmentController::class, 'show'])
            ->middleware('permission:finance.departments.view')
            ->name('departments.show');

        /* EDIT */
        Route::get('departments/{department}/edit', [DepartmentController::class, 'edit'])
            ->middleware('permission:finance.departments.edit')
            ->name('departments.edit');

        Route::put('departments/{department}', [DepartmentController::class, 'update'])
            ->middleware('permission:finance.departments.edit')
            ->name('departments.update');

        /* DELETE */
        Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])
            ->middleware('permission:finance.departments.delete')
            ->name('departments.destroy');


        /* =====================================================
         | PROGRAM FUNDING
         ===================================================== */

         /* =====================================================
    | PROGRAM FUNDING
    ===================================================== */

    /* LIST */
    Route::get('program-funding', [ProgramFundingController::class, 'index'])
        ->middleware('permission:finance.program_funding.view')
        ->name('program-funding.index');

    /* CREATE */
    Route::get('program-funding/create', [ProgramFundingController::class, 'create'])
        ->middleware('permission:finance.program_funding.create')
        ->name('program-funding.create');

    Route::post('program-funding', [ProgramFundingController::class, 'store'])
        ->middleware('permission:finance.program_funding.create')
        ->name('program-funding.store');

    /* SHOW */
    Route::get('program-funding/{programFunding}', [ProgramFundingController::class, 'show'])
        ->middleware('permission:finance.program_funding.view')
        ->name('program-funding.show');

    Route::get('program-funding/{programFunding}/documents/{document}/download', [ProgramFundingDocumentController::class, 'download'])
        ->middleware('permission:finance.program_funding.view')
        ->name('program-funding.documents.download');

    /* EDIT */
    Route::get('program-funding/{programFunding}/edit', [ProgramFundingController::class, 'edit'])
        ->middleware('permission:finance.program_funding.edit')
        ->name('program-funding.edit');

    Route::put('program-funding/{programFunding}', [ProgramFundingController::class, 'update'])
        ->middleware('permission:finance.program_funding.edit')
        ->name('program-funding.update');

    /* DELETE */
    Route::delete('program-funding/{programFunding}', [ProgramFundingController::class, 'destroy'])
        ->middleware('permission:finance.program_funding.delete')
        ->name('program-funding.destroy');


    /* =====================================================
    | WORKFLOW ACTIONS
    ===================================================== */

    /* SUBMIT FOR APPROVAL */
    Route::post('program-funding/{funding}/submit', [ProgramFundingController::class, 'submit'])
        ->middleware('permission:finance.program_funding.submit')
        ->name('program-funding.submit');

    /* APPROVE */
    Route::post('program-funding/{funding}/approve', [ProgramFundingController::class, 'approve'])
        ->middleware('permission:finance.program_funding.approve')
        ->name('program-funding.approve');

    /* REJECT */
    Route::post('program-funding/{funding}/reject', [ProgramFundingController::class, 'reject'])
        ->middleware('permission:finance.program_funding.approve')
        ->name('program-funding.reject');



        /* =====================================================
         | COMMITMENTS
         ===================================================== */

        Route::get('commitments', [BudgetCommitmentController::class, 'index'])
            ->middleware('permission:finance.commitments.view')
            ->name('commitments.index');

        Route::get('commitments/create', [BudgetCommitmentController::class, 'create'])
            ->middleware('permission:finance.commitments.create')
            ->name('commitments.create');

        Route::post('commitments', [BudgetCommitmentController::class, 'store'])
            ->middleware('permission:finance.commitments.create')
            ->name('commitments.store');

        Route::get('commitments/{commitment}', [BudgetCommitmentController::class, 'show'])
            ->middleware('permission:finance.commitments.view')
            ->name('commitments.show');

        Route::get('commitments/{commitment}/edit', [BudgetCommitmentController::class, 'edit'])
            ->middleware('permission:finance.commitments.edit')
            ->name('commitments.edit');

        Route::put('commitments/{commitment}', [BudgetCommitmentController::class, 'update'])
            ->middleware('permission:finance.commitments.edit')
            ->name('commitments.update');

	        Route::delete('commitments/{commitment}', [BudgetCommitmentController::class, 'destroy'])
	            ->middleware('permission:finance.commitments.delete')
	            ->name('commitments.destroy');

	        /* =====================================================
	         | PURCHASE REQUESTS (AUTO-CREATED FROM COMMITMENTS)
	         ===================================================== */

	        Route::get('purchase-requests', [PurchaseRequestController::class, 'index'])
	            ->middleware('permission:finance.purchase_requests.view')
	            ->name('purchase-requests.index');

	        Route::get('purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'show'])
	            ->middleware('permission:finance.purchase_requests.view')
	            ->name('purchase-requests.show');

	        Route::get('purchase-requests/{purchaseRequest}/pdf', [PurchaseRequestController::class, 'pdf'])
	            ->middleware('permission:finance.purchase_requests.view')
	            ->name('purchase-requests.pdf');

	        Route::get('purchase-requests/{purchaseRequest}/download', [PurchaseRequestController::class, 'download'])
	            ->middleware('permission:finance.purchase_requests.view')
	            ->name('purchase-requests.download');

	        Route::post('purchase-requests/{purchaseRequest}/send', [PurchaseRequestController::class, 'send'])
	            ->middleware('permission:finance.purchase_requests.send')
	            ->name('purchase-requests.send');





});



Route::middleware(['auth', 'not.funding.partner'])
    ->prefix('budget')
    ->name('budget.')
    ->group(function () {

        /* =====================================================
         | STRUCTURE: DEPARTMENTS
         ===================================================== */



        /* =====================================================
         | STRUCTURE: SECTORS
         ===================================================== */

        Route::get('sectors', [SectorController::class, 'index'])
            ->middleware('permission:sector.view')
            ->name('sectors.index');

        Route::get('sectors/create', [SectorController::class, 'create'])
            ->middleware('permission:sector.create')
            ->name('sectors.create');

        Route::post('sectors', [SectorController::class, 'store'])
            ->middleware('permission:sector.create')
            ->name('sectors.store');

        Route::get('sectors/{sector}/edit', [SectorController::class, 'edit'])
            ->middleware('permission:sector.edit')
            ->name('sectors.edit');

        Route::put('sectors/{sector}', [SectorController::class, 'update'])
            ->middleware('permission:sector.edit')
            ->name('sectors.update');

        Route::delete('sectors/{sector}', [SectorController::class, 'destroy'])
            ->middleware('permission:sector.delete')
            ->name('sectors.destroy');


        /* =====================================================
         | STRUCTURE: PROGRAMS
         | RBAC handled inside ProgramController
         ===================================================== */

        Route::get('programs', [ProgramController::class, 'index'])
            ->name('programs.index');

        Route::get('programs/create', [ProgramController::class, 'create'])
            ->name('programs.create');

        Route::post('programs', [ProgramController::class, 'store'])
            ->name('programs.store');

        Route::get('programs/{program}', [ProgramController::class, 'show'])
            ->name('programs.show');

        Route::get('programs/{program}/edit', [ProgramController::class, 'edit'])
            ->name('programs.edit');

        Route::put('programs/{program}', [ProgramController::class, 'update'])
            ->name('programs.update');

        Route::delete('programs/{program}', [ProgramController::class, 'destroy'])
            ->name('programs.destroy');


        /* =====================================================
         | STRUCTURE: PROJECTS
         ===================================================== */

        Route::get('projects', [ProjectController::class, 'index'])
            ->middleware('permission:project.view')
            ->name('projects.index');

        Route::get('projects/create', [ProjectController::class, 'create'])
            ->middleware('permission:project.create')
            ->name('projects.create');

        Route::post('projects', [ProjectController::class, 'store'])
            ->middleware('permission:project.create')
            ->name('projects.store');

        Route::get('projects/{project}', [ProjectController::class, 'show'])
            ->middleware('permission:project.view')
            ->name('projects.show');

        Route::get('projects/{project}/edit', [ProjectController::class, 'edit'])
            ->middleware('permission:project.edit')
            ->name('projects.edit');

        Route::put('projects/{project}', [ProjectController::class, 'update'])
            ->middleware('permission:project.edit')
            ->name('projects.update');

        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])
            ->middleware('permission:project.delete')
            ->name('projects.destroy');


        /* =====================================================
         | STRUCTURE: ACTIVITIES
         ===================================================== */

        Route::get('activities', [ActivityController::class, 'index'])
            ->middleware('permission:activities.view')
            ->name('activities.index');

        Route::get('activities/{activity}', [ActivityController::class, 'show'])
            ->middleware('permission:activities.view')
            ->name('activities.show');

        Route::get('activities/create/{project}', [ActivityController::class, 'create'])
            // ->middleware('permission:activities.create')
            ->name('activities.create');

        Route::post('activities', [ActivityController::class, 'store'])
            ->middleware('permission:activities.create')
            ->name('activities.store');

        Route::get('activities/{activity}/edit', [ActivityController::class, 'editAllocations'])
            ->middleware('permission:activities.edit')
            ->name('activities.edit');

        Route::put('activities/{activity}', [ActivityController::class, 'update'])
            ->middleware('permission:activities.edit')
            ->name('activities.update');

        Route::delete('activities/{activity}', [ActivityController::class, 'destroy'])
            ->middleware('permission:activities.delete')
            ->name('activities.destroy');


        /* =====================================================
         | STRUCTURE: SUB-ACTIVITIES
         ===================================================== */

        Route::get('subactivities', [SubActivityController::class, 'index'])
            ->middleware('permission:subactivities.view')
            ->name('subactivities.index');

        Route::get('subactivities/{subactivity}', [SubActivityController::class, 'show'])
            ->middleware('permission:subactivities.view')
            ->name('subactivities.show');

        Route::get('activities/{activity}/subactivities/create', [SubActivityController::class, 'create'])
            ->middleware('permission:subactivities.create')
            ->name('subactivities.create');

        Route::post('subactivities', [SubActivityController::class, 'store'])
            ->middleware('permission:subactivities.create')
            ->name('subactivities.store');

        Route::get('subactivities/{subactivity}/edit', [SubActivityController::class, 'edit'])
            ->middleware('permission:subactivities.edit')
            ->name('subactivities.edit');

        Route::put('subactivities/{subactivity}', [SubActivityController::class, 'update'])
            ->middleware('permission:subactivities.edit')
            ->name('subactivities.update');

        Route::get('subactivities/{subactivity}/edit-allocations', [SubActivityController::class, 'editAllocations'])
            ->middleware('permission:subactivity.edit')
            ->name('subactivities.allocations.edit');

        Route::post('subactivities/{subactivity}/update-allocations', [SubActivityController::class, 'updateAllocations'])
            ->middleware('permission:subactivity.edit')
            ->name('subactivities.allocations.update');

        Route::delete('subactivities/{subactivity}', [SubActivityController::class, 'destroy'])
            ->middleware('permission:subactivity.delete')
            ->name('subactivities.destroy');


        /* =====================================================
         | STRUCTURE: ALLOCATIONS
         ===================================================== */

        // Route::get('allocations', [AllocationController::class, 'index'])
        //     ->middleware('permission:allocation.view')
        //     ->name('allocations.index');

        // Route::get('allocations/{allocation}', [AllocationController::class, 'show'])
        //     ->middleware('permission:allocation.view')
        //     ->name('allocations.show');

        // Route::resource('allocations', AllocationController::class)
        //     ->middleware('permission:allocation.manage')
        //     ->except(['index', 'show']);


        /* =====================================================
         | REPORTS (READ-ONLY)
         ===================================================== */

        Route::get('reports', [BudgetReportController::class, 'index'])
            ->middleware('permission:budget.reports.view')
            ->name('reports.index');

        Route::get('reports/program/{program}', [BudgetReportController::class, 'programReport'])
            ->middleware('permission:program.report')
            ->name('reports.program');

        Route::get('reports/project/{project}', [BudgetReportController::class, 'projectReport'])
            ->middleware('permission:project.report')
            ->name('reports.project');

        Route::get('reports/activity/{activity}', [BudgetReportController::class, 'activityReport'])
            ->middleware('permission:activity.report')
            ->name('reports.activity');

        Route::get('reports/commitments', [BudgetReportController::class, 'commitmentReport'])
            ->middleware('permission:budget.reports.view')
            ->name('reports.commitments');

        Route::match(['get', 'post'], 'reports/commitments/export/pdf', [BudgetReportController::class, 'exportCommitmentPdf'])
            ->middleware('permission:budget.reports.view')
            ->name('reports.commitments.export.pdf');

        Route::get('reports/commitments/export/excel', [BudgetReportController::class, 'exportCommitmentExcel'])
            ->middleware('permission:budget.reports.view')
            ->name('reports.commitments.export.excel');

        Route::get('reports/ifr', [BudgetReportController::class, 'ifrReport'])
            ->middleware('permission:budget.reports.view')
            ->name('reports.ifr');

        Route::match(['get', 'post'], 'reports/ifr/export/pdf', [BudgetReportController::class, 'exportIfrPdf'])
            ->middleware('permission:budget.reports.view')
            ->name('reports.ifr.export.pdf');

        Route::get('reports/ifr/export/excel', [BudgetReportController::class, 'exportIfrExcel'])
            ->middleware('permission:budget.reports.view')
            ->name('reports.ifr.export.excel');


        /* =====================================================
         | EXECUTIVE SUMMARY
         ===================================================== */

        Route::get('budget-summary/dashboard', [AllocationSummaryController::class, 'dashboard'])
            ->middleware('permission:budget.summary.view')
            ->name('summary.dashboard');

        Route::get('budget-summary/executive', [AllocationSummaryController::class, 'executiveReports'])
            ->middleware('permission:budget.summary.view')
            ->name('summary.executive');


        Route::get('reports/export/pdf/{program}',
            [BudgetReportController::class, 'exportProgramPdf']
        )->middleware('permission:program.report')
        ->name('reports.export.pdf');

        Route::get('reports/export/excel/{program}',
            [BudgetReportController::class, 'exportProgramExcel']
        )->middleware('permission:program.report')
        ->name('reports.export.excel');




    });




Route::middleware(['auth', 'verified', 'not.funding.partner'])->group(function () {

    /* =====================================================
     | DASHBOARD (ROLE / PERMISSION BASED)
     ===================================================== */

    Route::middleware('permission:dashboard.access')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');
    });


    /* =====================================================
     | USER PROFILE (SELF-SERVICE)
     | No extra permission needed
     ===================================================== */

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');


    /* =====================================================
     | PASSWORD MANAGEMENT (SELF-SERVICE)
     | No extra permission needed
     ===================================================== */

    Route::get('/change-password', [ChangePasswordController::class, 'show'])
        ->name('password.change.form');

    Route::post('/change-password', [ChangePasswordController::class, 'update'])
        ->name('password.change.update');
});


/* =====================================================
 | COMMITMENTS – AJAX SUPPORT
 ===================================================== */


// Route::middleware(['auth'])->prefix('procurements')->group(function () {

//     /* ===============================
//      | PROCUREMENT CRUD
//      =============================== */
//     Route::get('/', [ProcurementController::class, 'index'])
//         ->name('procurements.index');

//     Route::get('/create', [ProcurementController::class, 'create'])
//         ->name('procurements.create');
//         // ->middleware('can:procurement.create');

//     Route::post('/', [ProcurementController::class, 'store'])
//         ->name('procurements.store');
//         // ->middleware('can:procurement.create');

//     Route::get('/{procurement}', [ProcurementController::class, 'show'])
//          ->name('procurements.show');

// });



// Route::post('/__route_test', function () {
//     return 'ROUTES LOADED';
// });

// Route::middleware('auth')
//     ->prefix('procurements')
//     ->name('procurements.')
//     ->controller(ProcurementWorkflowController::class)
//     ->group(function () {

//         Route::post('{procurement}/approve', 'approve')->name('approve');
//         Route::post('{procurement}/publish', 'publish')->name('publish');
//         Route::post('{procurement}/close', 'close')->name('close');
//         Route::post('{procurement}/award', 'award')->name('award');
// });

Route::middleware(['auth', 'not.funding.partner'])
    ->prefix('procurements')
    ->name('procurements.')
    ->group(function () {

        /* ===============================
         | WORKFLOW ACTIONS (MUST BE FIRST)
         =============================== */

        Route::post('{procurement}/approve',
            [ProcurementWorkflowController::class, 'approve']
        )->name('approve');

        Route::post('{procurement}/publish',
            [ProcurementWorkflowController::class, 'publish']
        )->name('publish');

        Route::post('{procurement}/close',
            [ProcurementWorkflowController::class, 'close']
        )->name('close');

        Route::post('{procurement}/award',
            [ProcurementWorkflowController::class, 'award']
        )->name('award');


        /* ===============================
         | PROCUREMENT CRUD
         =============================== */

        Route::get('/', [ProcurementController::class, 'index'])
            ->name('index');

        Route::get('/create', [ProcurementController::class, 'create'])
            ->name('create');

        Route::post('/', [ProcurementController::class, 'store'])
            ->name('store');

        Route::post('/{procurement}/notify-vendors', [ProcurementController::class, 'notifyVendors'])
            ->middleware('permission:vendor.outreach.send')
            ->name('notify-vendors');

        // ⚠️ GENERIC ROUTE MUST ALWAYS BE LAST
        Route::get('/{procurement}', [ProcurementController::class, 'show'])
            ->name('show');
    });


    use App\Http\Controllers\Procurement\ProcurementStatusController;
    use App\Http\Controllers\Procurement\ProcurementContractNegotiationController;
    use App\Http\Controllers\Procurement\ProcurementPurchaseOrderController;
    use App\Http\Controllers\Procurement\ProcurementDisbursementController;

Route::middleware(['auth', 'not.funding.partner'])
    ->prefix('procurement-status')
    ->name('statusProcurement.')
    ->group(function () {

        Route::post('{procurement}/submit',
            [ProcurementStatusController::class, 'submit']
        )->name('submit');

        Route::post('{procurement}/approve',
            [ProcurementStatusController::class, 'approve']
        )->name('approve');

        Route::post('{procurement}/reject',
            [ProcurementStatusController::class, 'reject']
        )->name('reject');

        Route::post('{procurement}/publish',
            [ProcurementStatusController::class, 'publish']
        )->name('publish');

        Route::post('{procurement}/close',
            [ProcurementStatusController::class, 'close']
        )->name('close');

        Route::post('{procurement}/award',
            [ProcurementStatusController::class, 'award']
        )->name('award');
    });

Route::middleware(['auth', 'not.funding.partner', 'permission:forms.manage'])
    ->prefix('procurement/contract-negotiations')
    ->name('procurement.contract-negotiations.')
    ->group(function () {
        Route::get('/', [ProcurementContractNegotiationController::class, 'index'])
            ->name('index');

        Route::post('{procurement}/{negotiation}/agree', [ProcurementContractNegotiationController::class, 'agree'])
            ->name('agree');

        Route::post('{procurement}/{negotiation}/terminate', [ProcurementContractNegotiationController::class, 'terminate'])
            ->name('terminate');

        Route::post('{procurement}/{negotiation}/documents', [ProcurementContractNegotiationController::class, 'storeDocuments'])
            ->name('documents.store');

        Route::get('{procurement}/{negotiation}/documents/{document}', [ProcurementContractNegotiationController::class, 'downloadDocument'])
            ->name('documents.download');

        Route::get('{procurement}', [ProcurementContractNegotiationController::class, 'show'])
            ->name('show');

        Route::post('{procurement}', [ProcurementContractNegotiationController::class, 'store'])
            ->name('store');
    });

Route::middleware(['auth', 'not.funding.partner', 'permission:finance.purchase_requests.view'])
    ->prefix('procurement/purchase-orders')
    ->name('procurement.purchase-orders.')
    ->group(function () {
        Route::get('/', [ProcurementPurchaseOrderController::class, 'index'])
            ->name('index');
        Route::get('{purchaseOrder}', [ProcurementPurchaseOrderController::class, 'show'])
            ->name('show');
        Route::get('{purchaseOrder}/pdf', [ProcurementPurchaseOrderController::class, 'pdf'])
            ->name('pdf');
        Route::get('{purchaseOrder}/download', [ProcurementPurchaseOrderController::class, 'download'])
            ->name('download');
    });

Route::middleware(['auth', 'not.funding.partner', 'permission:finance.purchase_requests.view'])
    ->prefix('procurement/disbursements')
    ->name('procurement.disbursements.')
    ->group(function () {
        Route::get('/', [ProcurementDisbursementController::class, 'index'])
            ->name('index');
        Route::get('/create', [ProcurementDisbursementController::class, 'create'])
            ->name('create');
        Route::post('/', [ProcurementDisbursementController::class, 'store'])
            ->name('store');
        Route::get('{disbursement}', [ProcurementDisbursementController::class, 'show'])
            ->name('show');
        Route::get('{disbursement}/pdf', [ProcurementDisbursementController::class, 'pdf'])
            ->name('pdf');
        Route::get('{disbursement}/download', [ProcurementDisbursementController::class, 'download'])
            ->name('download');
    });

Route::middleware(['auth', 'not.funding.partner', 'permission:finance.purchase_requests.view'])
    ->prefix('procurement/invoices')
    ->name('procurement.invoices.')
    ->group(function () {
        Route::get('/', [ProcurementInvoiceController::class, 'index'])
            ->name('index');
        Route::get('{invoice}', [ProcurementInvoiceController::class, 'show'])
            ->name('show');
        Route::post('{invoice}/approve', [ProcurementInvoiceController::class, 'approve'])
            ->name('approve');
        Route::post('{invoice}/reject', [ProcurementInvoiceController::class, 'reject'])
            ->name('reject');
        Route::post('{invoice}/purchase-order', [ProcurementInvoiceController::class, 'createPurchaseOrder'])
            ->name('purchase-order');
    });



Route::middleware(['auth', 'not.funding.partner', 'permission:forms.manage'])
    ->prefix('procurement/forms')
    ->group(function () {

        /* ===============================
           FORMS
           =============================== */
        Route::get('/', [DynamicFormController::class, 'index'])
            ->name('forms.index');

        Route::get('/create', [DynamicFormController::class, 'create'])
            ->name('forms.create');

        Route::post('/', [DynamicFormController::class, 'store'])
            ->name('forms.store');

        Route::get('/{form}/edit', [DynamicFormController::class, 'edit'])
            ->name('forms.edit');

        /* ===============================
           FORM FIELDS (BUILDER)
           =============================== */
        Route::post(
            '/{form}/fields',
            [DynamicFormFieldController::class, 'store']
        )->name('forms.fields.store');

        Route::delete(
            '/fields/{field}',
            [DynamicFormFieldController::class, 'destroy']
        )->name('forms.fields.destroy');

        Route::post('{form}/submit', [DynamicFormController::class, 'submit'])
            ->middleware('permission:forms.submit')
            ->name('forms.submit');

        Route::post('{form}/approve', [DynamicFormController::class, 'approve'])
            ->middleware('permission:forms.approve')
            ->name('forms.approve');

        Route::post('{form}/reject', [DynamicFormController::class, 'reject'])
            ->middleware('permission:forms.reject')
            ->name('forms.reject');


        Route::get('forms/attach',
            [ProcurementFormAssignmentController::class, 'create']
        )->name('procurements.forms.attach');

        Route::post('forms/attach',
            [ProcurementFormAssignmentController::class, 'store']
        )->name('procurements.forms.store');



         // 🔗 ATTACH FORM TO PROCUREMENT
        Route::post('attach-form', [ProcurementController::class, 'attachForm'])
            ->name('attach-form');

});


Route::middleware(['auth', 'not.funding.partner'])
    ->prefix('procurement/submissions')
    ->group(function () {

        Route::get(
            '/form/{form}/create',
            [FormSubmissionController::class, 'create']
        )->name('submissions.create');

        Route::post(
            '/form/{form}',
            [FormSubmissionController::class, 'store']
        )->name('submissions.store');

        Route::get(
            '/{submission}',
            [FormSubmissionController::class, 'show']
        )->name('submissions.show');

});






Route::middleware(['auth', 'not.funding.partner', 'can:procurement.audit'])
    ->prefix('procurement/audit')
    ->group(function () {

        Route::get(
            '/',
            [ProcurementAuditController::class, 'index']
        )->name('procurement.audit.index');

});


Route::prefix('procurement/submissions')
    ->middleware(['auth', 'not.funding.partner'])
    ->group(function () {

        // List submissions
        Route::get('/', [ProcurementSubmissionController::class, 'index'])
            // ->middleware('can:procurement.view')
            ->name('procurement.submissions.index');

        // View submission details
        Route::get('/{submission}', [ProcurementSubmissionController::class, 'show'])
            // ->middleware('can:procurement.view')
            ->name('procurement.submissions.show');

        // Secure download/stream of uploaded submission files (private storage)
        Route::get('/{submission}/values/{value}/download', [ProcurementSubmissionController::class, 'downloadValue'])
            ->name('procurement.submissions.values.download');
});



// todays code
Route::middleware(['auth', 'not.funding.partner'])
    ->prefix('prescreening/templates')
    ->name('prescreening.templates.')
    ->group(function () {

        Route::get('/', [PrescreeningTemplateController::class, 'index'])
            ->middleware('permission:prescreening.manage')
            ->name('index');

        Route::get('/create', [PrescreeningTemplateController::class, 'create'])
            ->middleware('permission:prescreening.manage')
            ->name('create');

        Route::post('/', [PrescreeningTemplateController::class, 'store'])
            ->middleware('permission:prescreening.manage')
            ->name('store');

        Route::get('/{template}', [PrescreeningTemplateController::class, 'show'])
            ->middleware('permission:prescreening.manage')
            ->name('show');

        Route::get('/{template}/edit', [PrescreeningTemplateController::class, 'edit'])
            ->middleware('permission:prescreening.manage')
            ->name('edit');

        Route::put('/{template}', [PrescreeningTemplateController::class, 'update'])
            ->middleware('permission:prescreening.manage')
            ->name('update');
    });

Route::middleware(['auth'])
    ->prefix('prescreening/templates/{template}')
    ->name('prescreening.criteria.')
    ->group(function () {

        Route::get('/criteria', [PrescreeningCriterionController::class, 'index'])
            ->middleware('permission:prescreening.manage')
            ->name('index');

        Route::get('/criteria/create', [PrescreeningCriterionController::class, 'create'])
            ->middleware('permission:prescreening.manage')
            ->name('create');

        Route::post('/criteria', [PrescreeningCriterionController::class, 'store'])
            ->middleware('permission:prescreening.manage')
            ->name('store');

        Route::get('/criteria/{criterion}', [PrescreeningCriterionController::class, 'show'])
            ->middleware('permission:prescreening.manage')
            ->name('show');

        Route::get('/criteria/{criterion}/edit', [PrescreeningCriterionController::class, 'edit'])
            ->middleware('permission:prescreening.manage')
            ->name('edit');

        Route::put('/criteria/{criterion}', [PrescreeningCriterionController::class, 'update'])
            ->middleware('permission:prescreening.manage')
            ->name('update');
    });


Route::middleware(['auth'])
    ->prefix('procurements/{procurement}/prescreening')
    ->group(function () {

        Route::get('/', [PrescreeningAssignmentController::class, 'edit'])
            ->middleware('permission:prescreening.manage')
            ->name('procurements.prescreening.edit');

        Route::post('/', [PrescreeningAssignmentController::class, 'store'])
            ->middleware('permission:prescreening.manage')
            ->name('procurements.prescreening.store');
    });






Route::middleware(['auth', 'not.funding.partner'])
    ->prefix('prescreening')
    ->group(function () {

        Route::get(
            'submissions',
            [PrescreeningEvaluationController::class, 'index']
        )->middleware('permission:prescreening.evaluate')
         ->name('prescreening.submissions.index');

        Route::get(
            'submissions/{submission}',
            [PrescreeningEvaluationController::class, 'show']
        )->middleware('permission:prescreening.evaluate')
         ->name('prescreening.submissions.show');

        Route::post(
            'submissions/{submission}',
            [PrescreeningEvaluationController::class, 'store']
        )->middleware('permission:prescreening.evaluate')
         ->name('prescreening.submissions.store');

        // ✅ NEW: REQUEST REWORK
        Route::post(
            'submissions/{submission}/rework',
            [PrescreeningEvaluationController::class, 'requestRework']
        )->middleware('permission:prescreening.request_rework')
         ->name('prescreening.submissions.rework');
    });

Route::middleware(['auth', 'not.funding.partner', 'permission:prescreening.evaluate'])
    ->get('prescreening/my-assignments', [PrescreeningUserAssignmentController::class, 'myAssignments'])
    ->name('prescreening.assignments.my');



Route::middleware(['auth'])
    ->prefix('prescreening/assignments')
    ->group(function () {

        Route::get('/',
            [PrescreeningUserAssignmentController::class, 'index']
        )->middleware('permission:prescreening.manage')
         ->name('prescreening.assignments.index');

        Route::get('/{procurement}',
            [PrescreeningUserAssignmentController::class, 'edit']
        )->middleware('permission:prescreening.manage')
         ->name('prescreening.assignments.edit');

        Route::post('/{procurement}',
            [PrescreeningUserAssignmentController::class, 'store']
        )->middleware('permission:prescreening.manage')
         ->name('prescreening.assignments.store');
    });










    /*
|--------------------------------------------------------------------------
| PUBLIC PROCUREMENT PORTAL
|--------------------------------------------------------------------------
| Accessible without authentication
*/
 /*
|--------------------------------------------------------------------------
| PUBLIC PROCUREMENT APPLICATIONS
|--------------------------------------------------------------------------
*/

Route::prefix('public/procurement')->group(function () {

    Route::get('/', [PublicProcurementController::class, 'index'])
        ->name('public.procurement.index');

    Route::get('/{procurement}', [PublicProcurementController::class, 'show'])
        ->name('public.procurement.show');

    Route::post('/{procurement}/apply', [PublicProcurementController::class, 'submit'])
        ->middleware('throttle:20,1')
        ->name('public.procurement.apply');

});


use App\Http\Controllers\EvaluationSectionController;
use App\Http\Controllers\EvaluationCriteriaController;


/*
|--------------------------------------------------------------------------
| EVALUATION CONFIGURATION (ADMIN)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'not.funding.partner', 'permission:evaluations.manage'])
    ->prefix('evals/config')
    ->name('evals.cfg.')
    ->group(function () {

        /* ===============================
         | MAIN
         =============================== */
        Route::get('/', [EvaluationController::class, 'index'])
            ->name('index');

        Route::get('/new', [EvaluationController::class, 'create'])
            ->name('new');

        Route::post('/store', [EvaluationController::class, 'store'])
            ->name('store');

        /* ===============================
         | SINGLE EVALUATION
         =============================== */
        Route::get('/{evaluation}', [EvaluationController::class, 'show'])
            ->whereNumber('evaluation')
            ->name('show');

        Route::get('/{evaluation}/edit', [EvaluationController::class, 'edit'])
            ->whereNumber('evaluation')
            ->name('edit');

        Route::put('/{evaluation}/update', [EvaluationController::class, 'update'])
            ->whereNumber('evaluation')
            ->name('update');

        Route::delete('/{evaluation}/delete', [EvaluationController::class, 'destroy'])
            ->whereNumber('evaluation')
            ->name('delete');

        /* ===============================
         | SECTIONS
         =============================== */
        Route::post('/{evaluation}/sec/add',
            [EvaluationSectionController::class, 'store']
        )
            ->whereNumber('evaluation')
            ->name('sec.add');

        Route::put('/sec/{section}/upd',
            [EvaluationSectionController::class, 'update']
        )
            ->whereNumber('section')
            ->name('sec.upd');

        Route::delete('/sec/{section}/del',
            [EvaluationSectionController::class, 'destroy']
        )
            ->whereNumber('section')
            ->name('sec.del');

        /* ===============================
         | CRITERIA
         =============================== */
        Route::post('/sec/{section}/crt/add',
            [EvaluationCriteriaController::class, 'store']
        )
            ->whereNumber('section')
            ->name('crt.add');

        Route::put('/crt/{criteria}/upd',
            [EvaluationCriteriaController::class, 'update']
        )
            ->whereNumber('criteria')
            ->name('crt.upd');

        Route::delete('/crt/{criteria}/del',
            [EvaluationCriteriaController::class, 'destroy']
        )
            ->whereNumber('criteria')
            ->name('crt.del');

       Route::get(
            '/panel/pdf/{submission}',
            [EvaluationPanelPdfController::class, 'single']
        )->name('panel.pdf.single');

        Route::get(
            '/panel/pdf/procurement/{procurement}',
            [EvaluationPanelPdfController::class, 'bulk']
        )->name('panel.pdf.bulk');

});


    /*
|--------------------------------------------------------------------------
| PROCUREMENT → EVALUATION LINKING (STILL PHASE 1)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'not.funding.partner', 'permission:evaluations.manage'])
    ->prefix('procurements')
    ->name('procurements.')
    ->group(function () {

        Route::get('/{procurement}/eval/assign',
            [ProcurementEvaluationController::class, 'create']
        )
            ->whereNumber('procurement')
            ->name('eval.assign');

        Route::post('/{procurement}/eval/assign',
            [ProcurementEvaluationController::class, 'store']
        )
            ->whereNumber('procurement')
            ->name('eval.assign.store');
});


use App\Http\Controllers\EvaluationSubmissionController;
use App\Http\Controllers\EvaluationScoringController;

/*
|--------------------------------------------------------------------------
| EVALUATOR SIDE
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'not.funding.partner', 'permission:evaluations.evaluate'])
    ->prefix('my-evaluations')
    ->name('my.eval.')
    ->group(function () {

        // List assignments
        Route::get('/', [EvaluationSubmissionController::class, 'myEvaluations'])
            ->name('index');

        // Start / continue evaluation
        Route::get('/{assignment}/start', [EvaluationSubmissionController::class, 'start'])
            ->name('start');

        // ✅ SAVE SCORES (AUTOSAVE / DRAFT)
        Route::post('/{assignment}/save-scores', [EvaluationSubmissionController::class, 'saveScores'])
            ->name('saveScores');

        // Submit final evaluation
        Route::post('/submit/{assignment}', [EvaluationSubmissionController::class, 'submit'])
            ->name('submit');

        // View submitted evaluation
        Route::get('/{assignment}/view', [EvaluationSubmissionController::class, 'view'])
            ->name('view');

        // Compare evaluators
        Route::get('/{assignment}/compare', [EvaluationSubmissionController::class, 'compare'])
            ->name('compare');

        // Sidebar-safe compare redirect
        Route::get('/compare', [EvaluationSubmissionController::class, 'compareRedirect'])
            ->name('compare.redirect');

        // Send evaluation for rework
        Route::post('/evaluations/{submission}/rework', [EvaluationSubmissionController::class, 'sendForRework'])
            ->name('evaluations.rework');
    });



/*
|--------------------------------------------------------------------------
| SCORING (AJAX)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'not.funding.partner', 'permission:evaluations.evaluate'])
    ->prefix('evaluation/score')
    ->name('eval.score.')
    ->group(function () {

        Route::post('/criteria',
            [EvaluationScoringController::class, 'saveCriteriaScore']
        )->name('criteria');

        Route::post('/section',
            [EvaluationScoringController::class, 'saveSectionNotes']
        )->name('section');
    });

/*
|--------------------------------------------------------------------------
| ADMIN / PROCUREMENT SIDE
|--------------------------------------------------------------------------
*/


Route::middleware(['auth', 'not.funding.partner', 'permission:evaluations.manage'])
    ->prefix('evaluation-assignments')
    ->name('eval.assign.')
    ->group(function () {

        /* ===============================
         | ASSIGNMENT HUB
         =============================== */
        Route::get('/',
            [EvaluationAssignmentController::class, 'hub']
        )->name('hub');

        Route::post('/store',
            [EvaluationAssignmentController::class, 'store']
        )->name('store');

        Route::delete('/{assignment}',
            [EvaluationAssignmentController::class, 'destroy']
        )->name('destroy');
});

Route::middleware(['auth', 'not.funding.partner', 'permission:evaluations.evaluate'])
    ->prefix('evaluation-assignments')
    ->name('eval.assign.')
    ->group(function () {
        /* ===============================
         | EVALUATOR WORKFLOW
         =============================== */

        // List applicants for this assignment
        Route::get('/{assignment}/applicants',
            [EvaluationSubmissionController::class, 'myEvaluations']
        )->name('applicants');

        // Start / continue evaluation (PER APPLICANT)
        Route::get('/{assignment}/start/{applicant}',
            [EvaluationSubmissionController::class, 'start']
        )->name('start');

        // Autosave scores
        Route::post('/{assignment}/save/{applicant}',
            [EvaluationSubmissionController::class, 'saveScores']
        )->name('save');

        // Final submit
        Route::post('/{assignment}/submit/{applicant}',
            [EvaluationSubmissionController::class, 'submit']
        )->name('submit');

        // Read-only view
        Route::get('/{assignment}/view/{applicant}',
            [EvaluationSubmissionController::class, 'view']
        )->name('view');

        // Secure video streaming (identity proof)
        Route::get('/{assignment}/video/{applicant}',
            [EvaluationSubmissionController::class, 'video']
        )->name('video');
    });

Route::middleware(['auth', 'not.funding.partner', 'permission:evaluations.evaluate'])
    ->prefix('panel-evaluations')
    ->name('eval.panel.')
    ->group(function () {

        // Panel Evaluation Dashboard
        Route::get('/',
            [EvaluationSubmissionController::class, 'panelHub']
        )->name('index');

        // (optional, later)
        // Route::get('/data', [EvaluationSubmissionController::class, 'panelData'])
        //     ->name('data');
});


use App\Http\Controllers\{
    SiteVisitController,
    SiteVisitAssignmentController,
    SiteVisitGroupController,
    SiteVisitObservationController,
    SiteVisitMediaController,
    ProcurementSiteVisitReportController
};

Route::middleware(['auth', 'not.funding.partner'])
    ->prefix('site-visits')
    ->name('site-visits.')
    ->group(function () {

    /* =========================
     | MAIN
     ========================= */
    Route::get('/', [SiteVisitController::class, 'index'])
        ->middleware('permission:site_visits.view')
        ->name('index');

    Route::get('/create', [SiteVisitController::class, 'create'])
        ->middleware('permission:site_visits.create')
        ->name('create');

    Route::post('/', [SiteVisitController::class, 'store'])
        ->middleware('permission:site_visits.create')
        ->name('store');

    Route::get('/{siteVisit}', [SiteVisitController::class, 'show'])
        ->name('show');


    /* =========================
     | ASSIGNMENT (ADMIN)
     ========================= */
    Route::post('/{siteVisit}/assign-individual',
        [SiteVisitAssignmentController::class, 'assignIndividual']
    )
        ->middleware('permission:site_visits.approve')
        ->name('assign.individual');

    Route::post('/{siteVisit}/assign-group',
        [SiteVisitGroupController::class, 'assignGroup']
    )
        ->middleware('permission:site_visits.approve')
        ->name('assign.group');


    /* =========================
     | OBSERVATIONS (LEADER)
     ========================= */
    Route::get('/{siteVisit}/observations/create',
        [SiteVisitObservationController::class, 'create']
    )
        ->middleware('permission:site_visits.observe')
        ->name('observations.create');

    Route::post('/{siteVisit}/observations',
        [SiteVisitObservationController::class, 'store']
    )
        ->middleware('permission:site_visits.observe')
        ->name('observations.store');


    /* =========================
     | MEDIA
     ========================= */
    Route::post('/{siteVisit}/media',
        [SiteVisitMediaController::class, 'store']
    )
        ->middleware('permission:site_visits.observe')
        ->name('media.store');

    Route::get('/{siteVisit}/media/{media}/download',
        [SiteVisitMediaController::class, 'download']
    )
        ->name('media.download');


    /* =========================
     | SUBMISSION
     ========================= */
    Route::post('/{siteVisit}/submit',
        [SiteVisitController::class, 'submit']
    )
        ->middleware('permission:site_visits.submit')
        ->name('submit');


    /* =========================
     | APPROVAL
     ========================= */
    Route::post('/{siteVisit}/approve',
        [SiteVisitController::class, 'approve']
    )
        ->middleware('permission:site_visits.approve')
        ->name('approve');



    Route::get(
    '/procurements/{procurement}/site-visit-report',
    [ProcurementSiteVisitReportController::class, 'show']
    )
        ->middleware('permission:site_visits.approve')
        ->name('procurements.site-visit-report');


    Route::get(
    '/reports/site-visits',
    [ProcurementSiteVisitReportController::class, 'index']
    )
        ->middleware('permission:site_visits.approve')
        ->name('reports.index');




});












Route::get('/', [LandingPageController::class, 'index'])->name('landing.index');
Route::get('/contact', [LandingPageController::class, 'contact'])->name('landing.contact');
Route::post('/impact-map/request-information', [LandingPageController::class, 'submitInformationRequest'])
    ->middleware('throttle:20,1')
    ->name('impact.request');

/*
|--------------------------------------------------------------------------
| IMPACT MAP (Real Data from Program Funding)
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\ImpactMapController;

Route::get('/impact-map', [ImpactMapController::class, 'index'])->name('impact.map');
Route::post('/api/impact-map/filter', [ImpactMapController::class, 'getFilteredData'])
    ->middleware('throttle:60,1')
    ->name('impact.filter');
Route::get('/impact-map/download/pdf', [ImpactMapController::class, 'downloadPdf'])
    ->middleware('throttle:10,1')
    ->name('impact.download.pdf');
Route::get('/impact-map/download/excel', [ImpactMapController::class, 'downloadExcel'])
    ->middleware('throttle:10,1')
    ->name('impact.download.excel');
Route::get('/bids/{project}', [LandingPageController::class, 'showBid'])->name('landing.show');
Route::get('/applicants', [ApplicantController::class, 'index'])
    ->middleware('auth')
    ->name('applicants.index');
Route::get('/applicants/{applicant}', [ApplicantController::class, 'show'])
    ->middleware('auth')
    ->name('applicants.show');
Route::get('/applicants/{applicant}/documents/{field}', [ApplicantController::class, 'downloadDocument'])
    ->middleware('auth')
    ->name('applicants.documents.download');
Route::get('/applicants/{applicant}/edit', [ApplicantController::class, 'edit'])
    ->middleware('auth')
    ->name('applicants.edit');
Route::put('/applicants/{applicant}', [ApplicantController::class, 'update'])
    ->middleware('auth')
    ->name('applicants.update');
Route::delete('/applicants/{applicant}', [ApplicantController::class, 'destroy'])
    ->middleware('auth')
    ->name('applicants.destroy');

Route::get('/reports', [ReportController::class, 'index'])
    ->middleware(['auth', 'not.funding.partner', 'permission:prescreening.reports.view_all'])
    ->name('reports.index');

Route::middleware(['auth', 'not.funding.partner', 'permission:prescreening.reports.view_all'])
    ->prefix('reports/prescreening')
    ->name('reports.prescreening.')
    ->group(function () {
        Route::get('/', [PrescreeningReportController::class, 'index'])->name('index');
        Route::get('/submission/{submission}', [PrescreeningReportController::class, 'submission'])->name('submission');
        Route::get('/submission/{submission}/pdf', [PrescreeningReportController::class, 'submissionPdf'])->name('submission.pdf');
        Route::get('/procurement/{procurement}', [PrescreeningReportController::class, 'procurement'])->name('procurement');
        Route::get('/procurement/{procurement}/pdf', [PrescreeningReportController::class, 'procurementPdf'])->name('procurement.pdf');
        Route::get('/consolidated', [PrescreeningReportController::class, 'consolidated'])->name('consolidated');
        Route::get('/consolidated/pdf', [PrescreeningReportController::class, 'consolidatedPdf'])->name('consolidated.pdf');
    });

Route::middleware(['auth', 'not.funding.partner', 'permission:evaluations.view_all'])
    ->prefix('reports/evaluations')
    ->name('reports.evaluations.')
    ->group(function () {
        Route::get('/', [EvaluationReportController::class, 'index'])->name('index');
        Route::get('/submission/{submission}', [EvaluationReportController::class, 'submission'])->name('submission');
        Route::get('/submission/{submission}/pdf', [EvaluationReportController::class, 'submissionPdf'])->name('submission.pdf');
        Route::get('/procurement/{procurement}', [EvaluationReportController::class, 'procurement'])->name('procurement');
        Route::get('/procurement/{procurement}/pdf', [EvaluationReportController::class, 'procurementPdf'])->name('procurement.pdf');
        Route::get('/consolidated', [EvaluationReportController::class, 'consolidated'])->name('consolidated');
        Route::get('/consolidated/pdf', [EvaluationReportController::class, 'consolidatedPdf'])->name('consolidated.pdf');
    });

Route::get('/callforproposal', [ApplicantController::class, 'create'])->name('applicants.create');
Route::get('/faq', [ApplicantController::class, 'faq'])->name('applicants.faq');
Route::post('/apply', [ApplicantController::class, 'store'])->name('applicants.store');
Route::get('/events', [ApplicantController::class, 'events'])->name('events');

Route::middleware(['auth', 'not.funding.partner', 'permission:system.audit.view'])
    ->prefix('system/audit')
    ->name('system.audit.')
    ->group(function () {
        Route::get('/', [SystemAuditController::class, 'index'])->name('index');
    });

/*
|--------------------------------------------------------------------------
| VENDOR MANAGEMENT (ADMIN)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'not.funding.partner', 'permission:vendor.manage'])
    ->prefix('vendors')
    ->name('vendors.')
    ->group(function () {
        Route::get('/', [VendorManagementController::class, 'index'])->name('index');
        Route::get('/template', [VendorManagementController::class, 'template'])->name('template');
        Route::post('/import', [VendorManagementController::class, 'import'])->name('import');
        Route::get('/{vendor}/edit', [VendorManagementController::class, 'edit'])->name('edit');
        Route::put('/{vendor}', [VendorManagementController::class, 'update'])->name('update');
        Route::put('/{vendor}/disable', [VendorManagementController::class, 'disable'])->name('disable');
        Route::put('/{vendor}/enable', [VendorManagementController::class, 'enable'])->name('enable');
        Route::put('/{vendor}/blacklist', [VendorManagementController::class, 'blacklist'])->name('blacklist');
        Route::put('/{vendor}/unblacklist', [VendorManagementController::class, 'unblacklist'])->name('unblacklist');
    });

Route::middleware(['auth', 'not.funding.partner', 'permission:vendor.manage'])
    ->prefix('vendors/categories')
    ->name('vendors.categories.')
    ->group(function () {
        Route::get('/', [VendorCategoryController::class, 'index'])->name('index');
        Route::get('/create', [VendorCategoryController::class, 'create'])->name('create');
        Route::post('/', [VendorCategoryController::class, 'store'])->name('store');
        Route::get('/{category}/edit', [VendorCategoryController::class, 'edit'])->name('edit');
        Route::put('/{category}', [VendorCategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [VendorCategoryController::class, 'destroy'])->name('destroy');
    });

Route::middleware(['auth', 'not.funding.partner', 'permission:vendor.requests.manage'])
    ->prefix('vendors/requests')
    ->name('vendors.requests.')
    ->group(function () {
        Route::get('/messages', [VendorRequestManagementController::class, 'messagesIndex'])
            ->name('messages.index');
        Route::get('/messages/{message}', [VendorRequestManagementController::class, 'messagesShow'])
            ->name('messages.show');
        Route::post('/messages/{message}/respond', [VendorRequestManagementController::class, 'messagesRespond'])
            ->middleware('permission:vendor.requests.respond')
            ->name('messages.respond');

        Route::get('/information', [VendorRequestManagementController::class, 'informationIndex'])
            ->name('information.index');
        Route::get('/information/{requestRecord}', [VendorRequestManagementController::class, 'informationShow'])
            ->name('information.show');
        Route::post('/information/{requestRecord}/respond', [VendorRequestManagementController::class, 'informationRespond'])
            ->middleware('permission:vendor.requests.respond')
            ->name('information.respond');
    });


/*
|--------------------------------------------------------------------------
| FUNDING PARTNER PORTAL
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Partner\{PartnerDashboardController, PartnerRequestController, PartnerProfileController};
use App\Http\Controllers\PartnerRequestManagementController;

Route::middleware(['auth', 'funding.partner', 'permission:partner.dashboard.access'])
    ->prefix('partner')
    ->name('partner.')
    ->group(function () {

        // Dashboard
        Route::get('/dashboard', [PartnerDashboardController::class, 'index'])
            ->name('dashboard');

        // Funded Programs
        Route::middleware('permission:partner.programs.view')->group(function () {
            Route::get('/programs', [PartnerDashboardController::class, 'programs'])
                ->name('programs.index');

            Route::get('/programs/{funding}', [PartnerDashboardController::class, 'showProgram'])
                ->name('programs.show');

            Route::get('/programs/{funding}/report', [PartnerDashboardController::class, 'programReport'])
                ->name('programs.report');

            Route::get('/insights', [PartnerDashboardController::class, 'insights'])
                ->name('insights');
        });

        // Projects (drill-down from programs)
        Route::middleware('permission:partner.projects.view')->group(function () {
            Route::get('/projects/{project}', [PartnerDashboardController::class, 'showProject'])
                ->name('projects.show');
        });

        // Activities (drill-down from projects)
        Route::middleware('permission:partner.projects.view')->group(function () {
            Route::get('/activities/{activity}', [PartnerDashboardController::class, 'showActivity'])
                ->name('activities.show');
        });

        // Document Downloads
        Route::middleware('permission:partner.documents.view')->group(function () {
            Route::get('/documents/{document}/download', [PartnerDashboardController::class, 'downloadDocument'])
                ->name('documents.download');
        });

        // Information Requests (View/Read)
        Route::middleware('permission:partner.requests.view')->group(function () {
            Route::get('/requests', [PartnerRequestController::class, 'index'])
                ->name('requests.index');

            Route::get('/requests/{request}', [PartnerRequestController::class, 'show'])
                ->name('requests.show');
        });

        // Information Requests (Create)
        Route::middleware('permission:partner.requests.create')->group(function () {
            Route::get('/request/create', [PartnerRequestController::class, 'create'])
                ->name('requests.create');

            Route::post('/requests', [PartnerRequestController::class, 'store'])
                ->name('requests.store');
        });

        // Profile Management
        Route::middleware('permission:partner.profile.edit')->group(function () {
            Route::get('/profile/edit', [PartnerProfileController::class, 'edit'])
                ->name('profile.edit');

            Route::put('/profile', [PartnerProfileController::class, 'update'])
                ->name('profile.update');
        });

        // Mark welcome as seen
        Route::post('/welcome/seen', [PartnerDashboardController::class, 'markWelcomeSeen'])
            ->name('welcome.seen');
    });

/*
|--------------------------------------------------------------------------
| VENDOR PORTAL
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])
    ->prefix('vendor')
    ->name('vendor.')
    ->group(function () {
        Route::get('/dashboard', [VendorPortalController::class, 'dashboard'])
            ->name('dashboard');
        Route::get('/procurements', [VendorProcurementController::class, 'index'])
            ->name('procurements.index');
        Route::get('/procurements/{procurement}', [VendorProcurementController::class, 'show'])
            ->name('procurements.show');
        Route::post('/procurements/{procurement}', [VendorProcurementController::class, 'submit'])
            ->name('procurements.submit');
        Route::get('/clarifications', [VendorPortalController::class, 'clarifications'])
            ->name('clarifications');
        Route::get('/submissions', [VendorPortalController::class, 'submissions'])
            ->name('submissions');
        Route::get('/payment-details', [VendorPortalController::class, 'paymentDetails'])
            ->name('payment-details');
        Route::put('/payment-details', [VendorPortalController::class, 'updatePaymentDetails'])
            ->name('payment-details.update');
        Route::get('/payments', [VendorDisbursementController::class, 'index'])
            ->name('payments.index');
        Route::get('/payments/{disbursement}', [VendorDisbursementController::class, 'show'])
            ->name('payments.show');
        Route::get('/payments/{disbursement}/pdf', [VendorDisbursementController::class, 'pdf'])
            ->name('payments.pdf');
        Route::get('/payments/{disbursement}/download', [VendorDisbursementController::class, 'download'])
            ->name('payments.download');
        Route::get('/invoices', [VendorInvoiceController::class, 'index'])
            ->name('invoices.index');
        Route::post('/invoices', [VendorInvoiceController::class, 'store'])
            ->name('invoices.store');
        Route::get('/invoices/{invoice}', [VendorInvoiceController::class, 'show'])
            ->name('invoices.show');
        Route::get('/invoices/{invoice}/pdf', [VendorInvoiceController::class, 'pdf'])
            ->name('invoices.pdf');
        Route::get('/invoices/{invoice}/download', [VendorInvoiceController::class, 'download'])
            ->name('invoices.download');
        Route::post('/messages', [VendorPortalController::class, 'storeMessage'])
            ->name('messages.store');
        Route::post('/information-requests', [VendorPortalController::class, 'storeInformationRequest'])
            ->name('information-requests.store');
        Route::get('/applications/{submission}/edit', [VendorPortalController::class, 'editApplication'])
            ->name('applications.edit');
        Route::put('/applications/{submission}', [VendorPortalController::class, 'updateApplication'])
            ->name('applications.update');
    });

/*
|--------------------------------------------------------------------------
| PARTNER REQUEST MANAGEMENT (Admin Side)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'not.funding.partner', 'permission:partner.requests.manage'])
    ->prefix('finance/partner-requests')
    ->name('finance.partner-requests.')
    ->group(function () {

        Route::get('/', [PartnerRequestManagementController::class, 'index'])
            ->name('index');

        Route::get('/{request}', [PartnerRequestManagementController::class, 'show'])
            ->name('show');

        Route::post('/{request}/respond', [PartnerRequestManagementController::class, 'respond'])
            ->middleware('permission:partner.requests.respond')
            ->name('respond');
    });


/*
|--------------------------------------------------------------------------
| AU MASTER DATA MANAGEMENT
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\AuMasterData\{
    AuMemberStateController,
    AuRegionalBlockController,
    AuAspirationController,
    AuGoalController,
    AuFlagshipProjectController
};

Route::middleware(['auth', 'not.funding.partner'])
    ->prefix('settings/au-master-data')
    ->name('settings.au.')
    ->group(function () {

        // Member States
        Route::resource('member-states', AuMemberStateController::class)
            ->except(['show']);

        // Regional Blocks (RECs)
        Route::resource('regional-blocks', AuRegionalBlockController::class)
            ->except(['show']);

        // Aspirations (Agenda 2063)
        Route::resource('aspirations', AuAspirationController::class)
            ->except(['show']);

        // Goals (Agenda 2063)
        Route::resource('goals', AuGoalController::class)
            ->except(['show']);

        // Flagship Projects
        Route::resource('flagship-projects', AuFlagshipProjectController::class)
            ->except(['show']);

        // AJAX: Get goals by aspiration IDs
        Route::get('goals/by-aspiration', [AuGoalController::class, 'byAspiration'])
            ->name('goals.by-aspiration');
    });


/*
|--------------------------------------------------------------------------
| SECURITY ROUTES (Password Change, OTP Verification)
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Auth\SecurityController;

Route::middleware(['auth'])->prefix('security')->name('security.')->group(function () {

    // Force Password Change
    Route::get('/password/change', [SecurityController::class, 'showPasswordChangeForm'])
        ->name('password.change');

    Route::post('/password/change', [SecurityController::class, 'submitPasswordChange'])
        ->name('password.submit');

    // OTP Verification
    Route::get('/otp/verify', [SecurityController::class, 'showOtpForm'])
        ->name('otp.show');

    Route::post('/otp/verify', [SecurityController::class, 'verifyOtp'])
        ->name('otp.verify');

    Route::post('/otp/resend', [SecurityController::class, 'resendOtp'])
        ->name('otp.resend');
});


/*
|--------------------------------------------------------------------------
| PROCUREMENT STRUCTURE
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'not.funding.partner'])
    ->prefix('procurement/structure')
    ->name('procurement.structure.')
    ->group(function () {
        Route::get('/', [ProcurementProgramPlanController::class, 'index'])->name('index');
        Route::post('/', [ProcurementProgramPlanController::class, 'store'])->name('store');
    });


/*
|--------------------------------------------------------------------------
| PROCUREMENT PLANS MODULE
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'not.funding.partner'])
    ->prefix('procurement/plans')
    ->name('procurement.plans.')
    ->group(function () {

        // Index and create routes
        Route::get('/', [ProcurementPlanController::class, 'index'])->name('index');
        Route::get('/create', [ProcurementPlanController::class, 'create'])->name('create');
        Route::post('/', [ProcurementPlanController::class, 'store'])->name('store');

        // AJAX routes (static paths must come BEFORE parameter routes)
        Route::get('/generate-code', [ProcurementPlanController::class, 'generateCode'])->name('generate-code');
        Route::get('/lookup', [ProcurementPlanController::class, 'lookup'])->name('lookup');
        Route::get('/sheet', [ProcurementPlanController::class, 'sheet'])->name('sheet');
        Route::get('/program-plans/{programPlan}/sheet', [ProcurementPlanController::class, 'programPlanSheet'])
            ->name('program-plans.sheet');
        Route::get('/sub-activities/{activity}', [ProcurementPlanController::class, 'getSubActivities'])->name('sub-activities');
        Route::post('/calculate-end-date', [ProcurementPlanController::class, 'calculateEndDate'])->name('calculate-end-date');

        // Parameter routes (must come LAST)
        Route::get('/{plan}', [ProcurementPlanController::class, 'show'])->name('show');
        Route::get('/{plan}/edit', [ProcurementPlanController::class, 'edit'])->name('edit');
        Route::put('/{plan}', [ProcurementPlanController::class, 'update'])->name('update');
        Route::delete('/{plan}', [ProcurementPlanController::class, 'destroy'])->name('destroy');
        Route::patch('/{plan}/toggle-launch', [ProcurementPlanController::class, 'toggleLaunch'])->name('toggle-launch');
    });

/*
|--------------------------------------------------------------------------
| PROCUREMENT SETTINGS (Sub-modules)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'not.funding.partner'])
    ->prefix('procurement/settings')
    ->name('procurement.settings.')
    ->group(function () {

        // Geographics
        Route::get('geographics/template', [GeographicController::class, 'template'])
            ->name('geographics.template');
        Route::post('geographics/import', [GeographicController::class, 'import'])
            ->name('geographics.import');
        Route::resource('geographics', GeographicController::class)
            ->except(['show']);

        // Method Planned
        Route::get('method-planned/template', [MethodPlannedController::class, 'template'])
            ->name('method-planned.template');
        Route::post('method-planned/import', [MethodPlannedController::class, 'import'])
            ->name('method-planned.import');
        Route::resource('method-planned', MethodPlannedController::class)
            ->except(['show']);

        // Stages
        Route::get('stages/template', [ProcurementStageController::class, 'template'])
            ->name('stages.template');
        Route::post('stages/import', [ProcurementStageController::class, 'import'])
            ->name('stages.import');
        Route::resource('stages', ProcurementStageController::class)
            ->except(['show']);

        // Statuses
        Route::get('statuses/template', [ProcurementSettingsStatusController::class, 'template'])
            ->name('statuses.template');
        Route::post('statuses/import', [ProcurementSettingsStatusController::class, 'import'])
            ->name('statuses.import');
        Route::resource('statuses', ProcurementSettingsStatusController::class)
            ->except(['show']);

        // Step Stages
        Route::get('step-stages/template', [StepStageController::class, 'template'])
            ->name('step-stages.template');
        Route::post('step-stages/import', [StepStageController::class, 'import'])
            ->name('step-stages.import');
        Route::resource('step-stages', StepStageController::class)
            ->except(['show']);

        // Step Approvals
        Route::get('step-approvals/template', [StepApprovalController::class, 'template'])
            ->name('step-approvals.template');
        Route::post('step-approvals/import', [StepApprovalController::class, 'import'])
            ->name('step-approvals.import');
        Route::resource('step-approvals', StepApprovalController::class)
            ->except(['show']);
    });


require __DIR__ . '/auth.php';
