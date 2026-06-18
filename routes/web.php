<?php

 use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CORE / AUTH / GENERAL CONTROLLERS
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\VideoStreamController;
use App\Http\Controllers\{
    DashboardController,
    LandingPageController,
    LanguageController,
    ProfileController,
    ChangePasswordController,
    DataWarehouseController,
    WebsiteVisitAnalyticsController,
    WebsiteVisitTrackerController,
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
	    ApprovedWorkPlanController,
	    PurchaseRequestController,
	    BudgetReportController,
	    ProjectBudgetController,
	    MeConfigurationController,
        MeIndicatorController,
        MeDataSourceController,
        MeSurveyController,
        IndicatorSurveyController,
        PublicIndicatorSurveyController,
        WorldIndicatorsController,
        WorldIndicatorSettingsController,
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
    VendorDeliverableController,
    VendorPurchaseRequestController,
    VendorReportController,
    VendorKnowledgeController,
    VendorThinkTankController,
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
    MemberStateCommunicationAdminController,
    MemberStateQuestionAdminController,
    MemberStateNationalDataReviewController,
};

use App\Http\Controllers\{
    AttpAiGuideController,
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

use App\Http\Controllers\MemberState\{
    MemberStateDashboardController,
    MemberStateCommunicationController,
    MemberStateNationalDataController,
    MemberStateComparisonController,
    MemberStateQuestionController,
    MemberStateCommodityController,
};

/*
|--------------------------------------------------------------------------
| LANGUAGE SWITCHING ROUTES
|--------------------------------------------------------------------------
*/
Route::post('/language/switch/{locale}', [LanguageController::class, 'switch'])->name('language.switch');
Route::get('/language/current', [LanguageController::class, 'current'])->name('language.current');
Route::get('/language/available', [LanguageController::class, 'available'])->name('language.available');

Route::prefix('website-visit-tracker')
    ->name('website-visit-tracker.')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->group(function () {
        Route::post('/start', [WebsiteVisitTrackerController::class, 'start'])->name('start');
        Route::post('/heartbeat', [WebsiteVisitTrackerController::class, 'heartbeat'])->name('heartbeat');
    });

Route::middleware(['auth', 'verified', 'not.funding.partner'])
    ->prefix('system')
    ->name('system.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | MEMBER-STATE COMMUNICATIONS MANAGEMENT
        |--------------------------------------------------------------------------
        */
        Route::prefix('communications')->name('communications.')->group(function () {
            Route::get('/', [MemberStateCommunicationAdminController::class, 'index'])
                ->middleware('permission:communications.view')
                ->name('index');

            Route::post('/{communication}/respond', [MemberStateCommunicationAdminController::class, 'respond'])
                ->middleware('permission:communications.respond')
                ->name('respond');

            Route::get('/{communication}/attachments/{attachment}', [MemberStateCommunicationAdminController::class, 'downloadAttachment'])
                ->middleware('permission:communications.view')
                ->name('attachments.download');
        });

        /*
        |--------------------------------------------------------------------------
        | MEMBER-STATE QUESTION RESPONSE MANAGEMENT
        |--------------------------------------------------------------------------
        */
        Route::prefix('questions')->name('questions.')->group(function () {
            Route::get('/', [MemberStateQuestionAdminController::class, 'index'])
                ->middleware('permission:questions.view')
                ->name('index');

            Route::post('/{question}/respond', [MemberStateQuestionAdminController::class, 'respond'])
                ->middleware('permission:questions.respond')
                ->name('respond');
        });

        /*
        |--------------------------------------------------------------------------
        | MEMBER-STATE NATIONAL DATA REVIEW
        |--------------------------------------------------------------------------
        */
        Route::prefix('national-data-reviews')->name('national-data-reviews.')->group(function () {
            Route::get('/', [MemberStateNationalDataReviewController::class, 'index'])
                ->middleware('permission:national_data.review')
                ->name('index');

            Route::post('/{entry}/status', [MemberStateNationalDataReviewController::class, 'updateStatus'])
                ->middleware('permission:national_data.approve')
                ->name('status.update');
        });

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

            Route::post('/bulk-login-access', [UserAccessController::class, 'bulkLoginAccess'])
                ->name('bulk-login-access');

            Route::get('/{user}/edit', [UserAccessController::class, 'edit'])
                ->name('edit');

            Route::put('/{user}', [UserAccessController::class, 'update'])
                ->name('update');

            Route::delete('/{user}', [UserAccessController::class, 'destroy'])
                ->name('destroy');

         /* ===============================
         | ? ADD THIS ROUTE
         | Inline Role Update
         =============================== */
        Route::put('/{user}/role', [UserAccessController::class, 'updateRole'])
            ->name('role.update');

        Route::post('/{user}/reset-password', [UserAccessController::class, 'resetPassword'])
            ->name('reset-password');

        Route::post('/{user}/block-login', [UserAccessController::class, 'blockLogin'])
            ->name('block-login');

        Route::post('/{user}/unblock-login', [UserAccessController::class, 'unblockLogin'])
            ->name('unblock-login');


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

        /*
        |--------------------------------------------------------------------------
        | ATTP AI GUIDE (TAWK.TO INTEGRATION)
        |--------------------------------------------------------------------------
        */
        Route::middleware('permission:users.manage')->prefix('attp-ai-guide')->name('attp-ai-guide.')->group(function () {

            Route::get('/settings', [AttpAiGuideController::class, 'settings'])
                ->name('settings');

            Route::put('/settings', [AttpAiGuideController::class, 'updateSettings'])
                ->name('update');

            Route::post('/test-widget', [AttpAiGuideController::class, 'testWidget'])
                ->name('test');
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

          Route::get('execution/dashboard/export/pdf', [MasterDashboard::class, 'exportExecutionDashboardPdf'])
            ->middleware('permission:finance.executions.view')
            ->name('execution.dashboard.export.pdf');




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

        Route::get('funders/{funder}/pdf', [FunderController::class, 'pdf'])
            ->middleware('permission:finance.funders.view')
            ->name('funders.pdf');

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
            ->middleware('permission:finance.governance_structure.view|finance.governance_structure.manage')
            ->name('governance.index');

        Route::post('governance-structure/levels', [GovernanceStructureController::class, 'storeLevel'])
            ->middleware('permission:finance.governance_structure.create|finance.governance_structure.manage')
            ->name('governance.levels.store');

        Route::put('governance-structure/levels/{level}', [GovernanceStructureController::class, 'updateLevel'])
            ->middleware('permission:finance.governance_structure.edit|finance.governance_structure.manage')
            ->name('governance.levels.update');

        Route::delete('governance-structure/levels/{level}', [GovernanceStructureController::class, 'destroyLevel'])
            ->middleware('permission:finance.governance_structure.delete|finance.governance_structure.manage')
            ->name('governance.levels.destroy');

        Route::get('governance-structure/levels/{level}/edit', [GovernanceStructureController::class, 'editLevel'])
            ->middleware('permission:finance.governance_structure.edit|finance.governance_structure.manage')
            ->name('governance.levels.edit');

        Route::post('governance-structure/nodes', [GovernanceStructureController::class, 'storeNode'])
            ->middleware('permission:finance.governance_structure.create|finance.governance_structure.manage')
            ->name('governance.nodes.store');

        Route::put('governance-structure/nodes/{node}', [GovernanceStructureController::class, 'updateNode'])
            ->middleware('permission:finance.governance_structure.edit|finance.governance_structure.manage')
            ->name('governance.nodes.update');

        Route::delete('governance-structure/nodes/{node}', [GovernanceStructureController::class, 'destroyNode'])
            ->middleware('permission:finance.governance_structure.delete|finance.governance_structure.manage')
            ->name('governance.nodes.destroy');

        Route::get('governance-structure/nodes/{node}/edit', [GovernanceStructureController::class, 'editNode'])
            ->middleware('permission:finance.governance_structure.edit|finance.governance_structure.manage')
            ->name('governance.nodes.edit');

        Route::post('governance-structure/lines', [GovernanceStructureController::class, 'storeLine'])
            ->middleware('permission:finance.governance_structure.create|finance.governance_structure.manage')
            ->name('governance.lines.store');

        Route::put('governance-structure/lines/{line}', [GovernanceStructureController::class, 'updateLine'])
            ->middleware('permission:finance.governance_structure.edit|finance.governance_structure.manage')
            ->name('governance.lines.update');

        Route::delete('governance-structure/lines/{line}', [GovernanceStructureController::class, 'destroyLine'])
            ->middleware('permission:finance.governance_structure.delete|finance.governance_structure.manage')
            ->name('governance.lines.destroy');

        Route::get('governance-structure/lines/{line}/edit', [GovernanceStructureController::class, 'editLine'])
            ->middleware('permission:finance.governance_structure.edit|finance.governance_structure.manage')
            ->name('governance.lines.edit');

        Route::post('governance-structure/assignments', [GovernanceStructureController::class, 'storeAssignment'])
            ->middleware('permission:finance.governance_structure.create|finance.governance_structure.manage')
            ->name('governance.assignments.store');

        Route::put('governance-structure/assignments/{assignment}', [GovernanceStructureController::class, 'updateAssignment'])
            ->middleware('permission:finance.governance_structure.edit|finance.governance_structure.manage')
            ->name('governance.assignments.update');

        Route::delete('governance-structure/assignments/{assignment}', [GovernanceStructureController::class, 'destroyAssignment'])
            ->middleware('permission:finance.governance_structure.delete|finance.governance_structure.manage')
            ->name('governance.assignments.destroy');

        Route::get('governance-structure/assignments/{assignment}/edit', [GovernanceStructureController::class, 'editAssignment'])
            ->middleware('permission:finance.governance_structure.edit|finance.governance_structure.manage')
            ->name('governance.assignments.edit');

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

        /* =====================================================
         | AWP - APPROVED WORK PLAN
         ===================================================== */

        Route::get('awp', [ApprovedWorkPlanController::class, 'index'])
            ->middleware('permission:finance.awp.view')
            ->name('awp.index');

        Route::get('awp/create', [ApprovedWorkPlanController::class, 'create'])
            ->middleware('permission:finance.awp.create')
            ->name('awp.create');

        Route::get('awp/export/pdf', [ApprovedWorkPlanController::class, 'exportRegistryPdf'])
            ->middleware('permission:finance.awp.view')
            ->name('awp.export.pdf');

        Route::get('awp/export/excel', [ApprovedWorkPlanController::class, 'exportRegistryExcel'])
            ->middleware('permission:finance.awp.view')
            ->name('awp.export.excel');

        Route::post('awp/create/from-allocations', [ApprovedWorkPlanController::class, 'storeAllocationSheet'])
            ->middleware('permission:finance.awp.create')
            ->name('awp.store-from-allocations');

        Route::post('awp/create/manual', [ApprovedWorkPlanController::class, 'storeManualSheet'])
            ->middleware('permission:finance.awp.create')
            ->name('awp.store-manual');

        Route::post('awp', [ApprovedWorkPlanController::class, 'store'])
            ->middleware('permission:finance.awp.create')
            ->name('awp.store');

        Route::put('awp/folder/rename', [ApprovedWorkPlanController::class, 'renameFolder'])
            ->middleware('permission:finance.awp.edit')
            ->name('awp.folder.rename');

        Route::put('awp/{awp}', [ApprovedWorkPlanController::class, 'update'])
            ->middleware('permission:finance.awp.edit')
            ->name('awp.update');

        Route::post('awp/{awp}/approve', [ApprovedWorkPlanController::class, 'approve'])
            ->middleware('permission:finance.awp.approve')
            ->name('awp.approve');

        Route::post('awp/{awp}/close', [ApprovedWorkPlanController::class, 'close'])
            ->middleware('permission:finance.awp.approve')
            ->name('awp.close');

        Route::post('awp/items/{item}/review', [ApprovedWorkPlanController::class, 'reviewItem'])
            ->middleware('permission:finance.awp.approve')
            ->name('awp.items.review');

        Route::put('awp/items/{item}', [ApprovedWorkPlanController::class, 'updateItem'])
            ->middleware('permission:finance.awp.edit')
            ->name('awp.items.update');

        Route::put('awp/items/{item}/sheet', [ApprovedWorkPlanController::class, 'updateSheetItem'])
            ->middleware('permission:finance.awp.edit')
            ->name('awp.items.sheet.update');

        Route::get('awp/items/{item}/document', [ApprovedWorkPlanController::class, 'downloadItemDocument'])
            ->middleware('permission:finance.awp.view')
            ->name('awp.items.document');

        Route::delete('awp/items/{item}', [ApprovedWorkPlanController::class, 'destroyItem'])
            ->middleware('permission:finance.awp.edit')
            ->name('awp.items.destroy');

        Route::delete('awp/{awp}', [ApprovedWorkPlanController::class, 'destroy'])
            ->middleware('permission:finance.awp.delete')
            ->name('awp.destroy');

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

	        Route::get('commitments/{commitment}/delete-info', [BudgetCommitmentController::class, 'destroyInfo'])
	            ->middleware('permission:finance.commitments.delete')
	            ->name('commitments.destroy-info');

	        Route::delete('commitments/{commitment}', [BudgetCommitmentController::class, 'destroy'])
	            ->middleware('permission:finance.commitments.delete')
	            ->name('commitments.destroy');

	        Route::delete('commitments/{commitment}/force', [BudgetCommitmentController::class, 'forceDestroy'])
	            ->middleware('permission:finance.commitments.delete')
	            ->name('commitments.force-destroy');

	        /* =====================================================
	         | PURCHASE REQUESTS (AUTO-CREATED FROM COMMITMENTS)
	         ===================================================== */

	        Route::get('purchase-requests', [PurchaseRequestController::class, 'index'])
	            ->middleware('permission:finance.purchase_requests.view')
	            ->name('purchase-requests.index');

	        Route::get('purchase-requests/create', [BudgetCommitmentController::class, 'createPurchaseRequest'])
	            ->middleware('permission:finance.commitments.create')
	            ->name('purchase-requests.create');

	        Route::post('purchase-requests', [BudgetCommitmentController::class, 'store'])
	            ->middleware('permission:finance.commitments.create')
	            ->name('purchase-requests.store');

	        Route::get('purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'show'])
	            ->middleware('permission:finance.purchase_requests.view')
	            ->name('purchase-requests.show');

	        Route::get('purchase-requests/{purchaseRequest}/pdf', [PurchaseRequestController::class, 'pdf'])
	            ->middleware('permission:finance.purchase_requests.view')
	            ->name('purchase-requests.pdf');

	        Route::get('purchase-requests/{purchaseRequest}/download', [PurchaseRequestController::class, 'download'])
	            ->middleware('permission:finance.purchase_requests.view')
	            ->name('purchase-requests.download');

	        Route::get('purchase-requests/{purchaseRequest}/edit', [PurchaseRequestController::class, 'edit'])
	            ->middleware('permission:finance.commitments.edit')
	            ->name('purchase-requests.edit');

	        Route::post('purchase-requests/{purchaseRequest}/send', [PurchaseRequestController::class, 'send'])
	            ->middleware('permission:finance.purchase_requests.send')
	            ->name('purchase-requests.send');

	        Route::post('purchase-requests/{purchaseRequest}/line-item-evidence', [PurchaseRequestController::class, 'storeLineItemEvidence'])
	            ->middleware('permission:finance.purchase_orders.create')
	            ->name('purchase-requests.line-item-evidence.store');

	        Route::post('purchase-requests/{purchaseRequest}/attachments', [PurchaseRequestController::class, 'storeAttachments'])
	            ->middleware('permission:finance.commitments.edit')
	            ->name('purchase-requests.attachments.store');

	        Route::get('purchase-requests/{purchaseRequest}/attachments/{attachment}', [PurchaseRequestController::class, 'downloadAttachment'])
	            ->middleware('permission:finance.purchase_requests.view')
	            ->name('purchase-requests.attachments.download');

	        Route::delete('purchase-requests/{purchaseRequest}/attachments/{attachment}', [PurchaseRequestController::class, 'destroyAttachment'])
	            ->middleware('permission:finance.commitments.edit')
	            ->name('purchase-requests.attachments.destroy');

	        Route::post('purchase-requests/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve'])
	            ->middleware('permission:finance.purchase_requests.approve')
	            ->name('purchase-requests.approve');

	        Route::post('purchase-requests/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject'])
	            ->middleware('permission:finance.purchase_requests.approve')
	            ->name('purchase-requests.reject');

	        Route::get('purchase-requests/{purchaseRequest}/delete-info', [PurchaseRequestController::class, 'destroyInfo'])
	            ->middleware('permission:finance.commitments.delete')
	            ->name('purchase-requests.destroy-info');

	        Route::delete('purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'destroy'])
	            ->middleware('permission:finance.commitments.delete')
	            ->name('purchase-requests.destroy');

	        Route::delete('purchase-requests/{purchaseRequest}/force', [PurchaseRequestController::class, 'forceDestroy'])
	            ->middleware('permission:finance.commitments.delete')
	            ->name('purchase-requests.force-destroy');





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

        Route::post('projects/{project}/allocations', [ProjectController::class, 'updateAllocations'])
            ->middleware('permission:project.edit')
            ->name('projects.allocations.update');

        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])
            ->middleware('permission:project.delete')
            ->name('projects.destroy');


        /* =====================================================
         | M&E CONFIGURATION
         ===================================================== */

        // Indicators (centralized setup page)
        Route::get('me/indicators', [MeIndicatorController::class, 'index'])
            ->name('me.indicators.index');
        Route::post('me/indicators', [MeIndicatorController::class, 'store'])
            ->name('me.indicators.store');
        Route::put('me/indicators/{indicator}', [MeIndicatorController::class, 'update'])
            ->name('me.indicators.update');
        Route::delete('me/indicators/{indicator}', [MeIndicatorController::class, 'destroy'])
            ->name('me.indicators.destroy');
        Route::get('me/indicators/report/excel', [MeIndicatorController::class, 'exportManagementExcel'])
            ->name('me.indicators.report.excel');
        Route::get('me/indicators/report/pdf', [MeIndicatorController::class, 'exportManagementPdf'])
            ->name('me.indicators.report.pdf');
        Route::post('me/indicators/{indicator}/survey-link', [IndicatorSurveyController::class, 'generateLink'])
            ->name('me.indicators.survey-link.generate');
        Route::get('me/indicators/{indicator}/survey-responses', [IndicatorSurveyController::class, 'responses'])
            ->name('me.indicators.survey-responses');

        Route::prefix('me/surveys')->name('me.surveys.')->group(function () {
            Route::get('/', [MeSurveyController::class, 'index'])
                ->name('index');
            Route::get('/reports', [MeSurveyController::class, 'reports'])
                ->name('reports');
            Route::post('/reports/export/pdf', [MeSurveyController::class, 'exportReportPdf'])
                ->name('reports.export.pdf');
            Route::get('/responses', [MeSurveyController::class, 'responses'])
                ->name('responses');
            Route::get('/responses/{surveyLink}/explore', [MeSurveyController::class, 'responseDetails'])
                ->name('responses.explore');
            Route::get('/responses/{surveyLink}/explore/export/pdf', [MeSurveyController::class, 'exportResponseDetailsPdf'])
                ->name('responses.explore.export.pdf');
            Route::get('/responses/{surveyLink}/explore/export/excel', [MeSurveyController::class, 'exportResponseDetailsExcel'])
                ->name('responses.explore.export.excel');
            Route::delete('/responses/{response}', [MeSurveyController::class, 'destroyResponse'])
                ->name('responses.destroy');
            Route::delete('/links/{surveyLink}', [MeSurveyController::class, 'destroySurvey'])
                ->name('links.destroy');
            Route::get('/questionnaires', [MeSurveyController::class, 'questionnaires'])
                ->name('questionnaires');
            Route::get('/questionnaires/create', [MeSurveyController::class, 'create'])
                ->name('questionnaires.create');
            Route::get('/questionnaires/{methodology}/edit', [MeSurveyController::class, 'edit'])
                ->name('questionnaires.edit');
            Route::get('/qr-codes', [MeSurveyController::class, 'qrCodes'])
                ->name('qr');
        });

        // Data Source Controller
        Route::get('me/data-sources', [MeDataSourceController::class, 'index'])
            ->name('me.data-sources.index');
        Route::get('me/data-sources/surveys/export', [MeDataSourceController::class, 'exportSurveys'])
            ->name('me.data-sources.surveys.export');
        Route::get('me/data-sources/surveys/{surveyLink}', [MeDataSourceController::class, 'showSurvey'])
            ->name('me.data-sources.surveys.show');
        Route::get('me/data-sources/surveys/{surveyLink}/export', [MeDataSourceController::class, 'exportSurvey'])
            ->name('me.data-sources.surveys.single-export');
        Route::get('me/data-sources/template/bridge', [MeDataSourceController::class, 'downloadGenericTemplate'])
            ->name('me.data-sources.template.generic');
        Route::post('me/data-sources/sync-all', [MeDataSourceController::class, 'manualSyncAll'])
            ->name('me.data-sources.sync-all');
        Route::post('me/data-sources/{indicator}/sync', [MeDataSourceController::class, 'manualSync'])
            ->name('me.data-sources.sync');
        Route::post('me/data-sources/{indicator}/columns-preview', [MeDataSourceController::class, 'previewColumns'])
            ->name('me.data-sources.columns-preview');
        Route::get('me/data-sources/{indicator}/template', [MeDataSourceController::class, 'downloadTemplate'])
            ->name('me.data-sources.template.download');
        Route::get('me/data-sources/{indicator}/raw-data', [MeDataSourceController::class, 'rawData'])
            ->name('me.data-sources.raw-data');

        // World Indicators / Performance (Back Office Controller)
        Route::get('me/world-indicators/settings', [WorldIndicatorSettingsController::class, 'edit'])
            ->middleware('permission:world.indicators.manage')
            ->name('me.world-indicators.settings.edit');
        Route::put('me/world-indicators/settings', [WorldIndicatorSettingsController::class, 'update'])
            ->middleware('permission:world.indicators.manage')
            ->name('me.world-indicators.settings.update');
        Route::post('me/world-indicators/settings/sync-catalog', [WorldIndicatorSettingsController::class, 'syncWorldBankCatalog'])
            ->middleware('permission:world.indicators.manage')
            ->name('me.world-indicators.settings.sync-catalog');
        Route::post('me/world-indicators/settings/sync-data', [WorldIndicatorSettingsController::class, 'syncWorldBankData'])
            ->middleware('permission:world.indicators.manage')
            ->name('me.world-indicators.settings.sync-data');

        // Indicator Levels
        Route::get('me/indicator-levels', [MeConfigurationController::class, 'indicatorLevelsIndex'])
            ->name('me-configuration.indicator-levels.index');

        Route::get('me/indicator-levels/create', [MeConfigurationController::class, 'indicatorLevelsCreate'])
            ->name('me-configuration.indicator-levels.create');

        Route::post('me/indicator-levels', [MeConfigurationController::class, 'indicatorLevelsStore'])
            ->name('me-configuration.indicator-levels.store');

        Route::get('me/indicator-levels/{level}/edit', [MeConfigurationController::class, 'indicatorLevelsEdit'])
            ->name('me-configuration.indicator-levels.edit');

        Route::put('me/indicator-levels/{level}', [MeConfigurationController::class, 'indicatorLevelsUpdate'])
            ->name('me-configuration.indicator-levels.update');

        Route::delete('me/indicator-levels/{level}', [MeConfigurationController::class, 'indicatorLevelsDestroy'])
            ->name('me-configuration.indicator-levels.destroy');

        // Reporting Frequencies
        Route::get('me/frequencies', [MeConfigurationController::class, 'frequenciesIndex'])
            ->name('me-configuration.frequencies.index');

        Route::get('me/frequencies/create', [MeConfigurationController::class, 'frequenciesCreate'])
            ->name('me-configuration.frequencies.create');

        Route::post('me/frequencies', [MeConfigurationController::class, 'frequenciesStore'])
            ->name('me-configuration.frequencies.store');

        Route::get('me/frequencies/{frequency}/edit', [MeConfigurationController::class, 'frequenciesEdit'])
            ->name('me-configuration.frequencies.edit');

        Route::put('me/frequencies/{frequency}', [MeConfigurationController::class, 'frequenciesUpdate'])
            ->name('me-configuration.frequencies.update');

        Route::delete('me/frequencies/{frequency}', [MeConfigurationController::class, 'frequenciesDestroy'])
            ->name('me-configuration.frequencies.destroy');

        // Indicator Units
        Route::get('me/units', [MeConfigurationController::class, 'unitsIndex'])
            ->name('me-configuration.units.index');

        Route::get('me/units/create', [MeConfigurationController::class, 'unitsCreate'])
            ->name('me-configuration.units.create');

        Route::post('me/units', [MeConfigurationController::class, 'unitsStore'])
            ->name('me-configuration.units.store');

        Route::get('me/units/{unit}/edit', [MeConfigurationController::class, 'unitsEdit'])
            ->name('me-configuration.units.edit');

        Route::put('me/units/{unit}', [MeConfigurationController::class, 'unitsUpdate'])
            ->name('me-configuration.units.update');

        Route::delete('me/units/{unit}', [MeConfigurationController::class, 'unitsDestroy'])
            ->name('me-configuration.units.destroy');

        // Definitions / Formulas
        Route::get('me/definitions', [MeConfigurationController::class, 'definitionsIndex'])
            ->name('me-configuration.definitions.index');

        // Methodologies (placeholder)
        Route::get('me/methodologies', [MeConfigurationController::class, 'methodologiesIndex'])
            ->name('me-configuration.methodologies.index');
        Route::get('me/methodologies/create', [MeConfigurationController::class, 'methodologiesCreate'])
            ->name('me-configuration.methodologies.create');
        Route::post('me/methodologies', [MeConfigurationController::class, 'methodologiesStore'])
            ->name('me-configuration.methodologies.store');
        Route::get('me/methodologies/{methodology}/edit', [MeConfigurationController::class, 'methodologiesEdit'])
            ->name('me-configuration.methodologies.edit');
        Route::put('me/methodologies/{methodology}', [MeConfigurationController::class, 'methodologiesUpdate'])
            ->name('me-configuration.methodologies.update');
        Route::delete('me/methodologies/{methodology}', [MeConfigurationController::class, 'methodologiesDestroy'])
            ->name('me-configuration.methodologies.destroy');

        // Definitions CRUD
        Route::get('me/definitions', [MeConfigurationController::class, 'definitionsIndex'])
            ->name('me-configuration.definitions.index');
        Route::get('me/definitions/create', [MeConfigurationController::class, 'definitionsCreate'])
            ->name('me-configuration.definitions.create');
        Route::post('me/definitions', [MeConfigurationController::class, 'definitionsStore'])
            ->name('me-configuration.definitions.store');
        Route::get('me/definitions/{definition}/edit', [MeConfigurationController::class, 'definitionsEdit'])
            ->name('me-configuration.definitions.edit');
        Route::put('me/definitions/{definition}', [MeConfigurationController::class, 'definitionsUpdate'])
            ->name('me-configuration.definitions.update');
        Route::delete('me/definitions/{definition}', [MeConfigurationController::class, 'definitionsDestroy'])
            ->name('me-configuration.definitions.destroy');


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

        Route::get('reports/export/portfolio/pdf', [BudgetReportController::class, 'exportPortfolioPdf'])
            ->middleware('permission:budget.reports.view')
            ->name('reports.portfolio.export.pdf');

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

        Route::get('reports/project-financial-position', [BudgetReportController::class, 'projectFinancialPosition'])
            ->middleware('permission:budget.project_financial_position.view|budget.reports.view')
            ->name('reports.project-financial-position');

        Route::get('reports/project-financial-position/export/pdf', [BudgetReportController::class, 'exportProjectFinancialPositionPdf'])
            ->middleware('permission:budget.project_financial_position.view|budget.reports.view')
            ->name('reports.project-financial-position.export.pdf');

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

        Route::get('budget-summary/dashboard/export/pdf', [AllocationSummaryController::class, 'exportDashboardPdf'])
            ->middleware('permission:budget.summary.view')
            ->name('summary.dashboard.export.pdf');

        Route::get('budget-summary/executive', [AllocationSummaryController::class, 'executiveReports'])
            ->middleware('permission:budget.summary.view')
            ->name('summary.executive');

        Route::get('budget-summary/executive/export/pdf', [AllocationSummaryController::class, 'exportExecutivePdf'])
            ->middleware('permission:budget.summary.view')
            ->name('summary.executive.export.pdf');


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

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::prefix('data-warehouse')
        ->name('data-warehouse.')
        ->group(function () {
            Route::get('/', [DataWarehouseController::class, 'index'])
                ->name('index');
            Route::get('/create', [DataWarehouseController::class, 'create'])
                ->name('create');
            Route::post('/', [DataWarehouseController::class, 'store'])
                ->name('store');
            Route::get('/categories', [DataWarehouseController::class, 'categories'])
                ->name('categories');
            Route::post('/categories', [DataWarehouseController::class, 'storeCategory'])
                ->name('categories.store');
            Route::get('/files/{file}/download', [DataWarehouseController::class, 'download'])
                ->name('files.download');
            Route::get('/modules/{module}', [DataWarehouseController::class, 'showModule'])
                ->name('modules.show');
            Route::get('/knowledge-file/{token}', [DataWarehouseController::class, 'libraryFile'])
                ->name('library.file');
            Route::get('/{record}', [DataWarehouseController::class, 'show'])
                ->name('show');
        });

    Route::prefix('website-visit-analysis')
        ->name('website-visit-analysis.')
        ->middleware('permission:website_visits.view')
        ->group(function () {
            Route::get('/', [WebsiteVisitAnalyticsController::class, 'index'])
                ->name('index');
            Route::get('/activity-performed', [WebsiteVisitAnalyticsController::class, 'activity'])
                ->name('activity');
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

        // ?? GENERIC ROUTE MUST ALWAYS BE LAST
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

        Route::post('{procurement}/draft',
            [ProcurementStatusController::class, 'draft']
        )->name('draft');

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
        Route::get('create', [ProcurementPurchaseOrderController::class, 'create'])
            ->middleware('permission:finance.purchase_orders.create')
            ->name('create');
        Route::post('/', [ProcurementPurchaseOrderController::class, 'store'])
            ->middleware('permission:finance.purchase_orders.create')
            ->name('store');
        Route::get('{purchaseOrder}/edit', [ProcurementPurchaseOrderController::class, 'edit'])
            ->middleware('permission:finance.purchase_orders.create')
            ->name('edit');
        Route::match(['put', 'patch'], '{purchaseOrder}', [ProcurementPurchaseOrderController::class, 'update'])
            ->middleware('permission:finance.purchase_orders.create')
            ->name('update');
        Route::post('{purchaseOrder}/delete', [ProcurementPurchaseOrderController::class, 'destroy'])
            ->middleware('permission:finance.purchase_orders.delete')
            ->name('destroy');
        Route::delete('{purchaseOrder}', [ProcurementPurchaseOrderController::class, 'destroy'])
            ->middleware('permission:finance.purchase_orders.delete')
            ->name('destroy.legacy');
        Route::get('{purchaseOrder}/supporting-document', [ProcurementPurchaseOrderController::class, 'downloadSupportingDocument'])
            ->name('supporting-document');
        Route::get('{purchaseOrder}/line-item-evidence/{evidence}/documents/{document}', [ProcurementPurchaseOrderController::class, 'downloadLineItemEvidenceDocument'])
            ->whereNumber('document')
            ->name('line-item-evidence.document');
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
        Route::get('{disbursement}/edit', [ProcurementDisbursementController::class, 'edit'])
            ->name('edit');
        Route::put('{disbursement}', [ProcurementDisbursementController::class, 'update'])
            ->name('update');
        Route::delete('{disbursement}', [ProcurementDisbursementController::class, 'destroy'])
            ->name('destroy');
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

        Route::delete('/{form}', [DynamicFormController::class, 'destroy'])
            ->name('forms.destroy');

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



         // ?? ATTACH FORM TO PROCUREMENT
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

        Route::post('/screen-all', [ProcurementSubmissionController::class, 'screenAll'])
            ->name('procurement.submissions.screen-all');

        Route::get('/{submission}/screening', [ProcurementSubmissionController::class, 'screeningReport'])
            ->name('procurement.submissions.screening.report');

        Route::post('/{submission}/screen', [ProcurementSubmissionController::class, 'screen'])
            ->name('procurement.submissions.screen');

        Route::post('/{submission}/screening/decision', [ProcurementSubmissionController::class, 'saveScreeningDecision'])
            ->name('procurement.submissions.screening.decision');

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

        Route::get(
            'submissions/{submission}/pdf',
            [PrescreeningEvaluationController::class, 'downloadPdf']
        )->middleware('permission:prescreening.evaluate')
         ->name('prescreening.submissions.pdf');

        Route::get(
            'submissions/{submission}/anonymised-pdf',
            [PrescreeningEvaluationController::class, 'downloadAnonymisedPdf']
        )->middleware('permission:prescreening.evaluate')
         ->name('prescreening.submissions.anonymised-pdf');

        Route::post(
            'submissions/{submission}',
            [PrescreeningEvaluationController::class, 'store']
        )->middleware('permission:prescreening.evaluate')
         ->name('prescreening.submissions.store');

        // ? NEW: REQUEST REWORK
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

Route::prefix('public/me/indicator-surveys')->name('public.me.indicators.surveys.')->group(function () {
    Route::get('/{token}', [PublicIndicatorSurveyController::class, 'show'])
        ->name('show');
    Route::post('/{token}', [PublicIndicatorSurveyController::class, 'submit'])
        ->name('submit');
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

        Route::get('/{evaluation}/preview', [EvaluationController::class, 'preview'])
            ->name('preview');

        Route::get('/{evaluation}/template.pdf', [EvaluationController::class, 'templatePdf'])
            ->name('template.pdf');

        /* ===============================
         | SINGLE EVALUATION
         =============================== */
        Route::get('/{evaluation}', [EvaluationController::class, 'show'])
            ->name('show');

        Route::get('/{evaluation}/edit', [EvaluationController::class, 'edit'])
            ->name('edit');

        Route::put('/{evaluation}/update', [EvaluationController::class, 'update'])
            ->name('update');

        Route::delete('/{evaluation}/delete', [EvaluationController::class, 'destroy'])
            ->name('delete');

        /* ===============================
         | SECTIONS
         =============================== */
        Route::post('/{evaluation}/sec/add',
            [EvaluationSectionController::class, 'store']
        )
            ->name('sec.add');

        Route::put('/sec/{section}/upd',
            [EvaluationSectionController::class, 'update']
        )
            ->name('sec.upd');

        Route::delete('/sec/{section}/del',
            [EvaluationSectionController::class, 'destroy']
        )
            ->name('sec.del');

        /* ===============================
         | CRITERIA
         =============================== */
        Route::post('/sec/{section}/crt/add',
            [EvaluationCriteriaController::class, 'store']
        )
            ->name('crt.add');

        Route::put('/crt/{criteria}/upd',
            [EvaluationCriteriaController::class, 'update']
        )
            ->name('crt.upd');

        Route::delete('/crt/{criteria}/del',
            [EvaluationCriteriaController::class, 'destroy']
        )
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
| PROCUREMENT ? EVALUATION LINKING (STILL PHASE 1)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'not.funding.partner', 'permission:evaluations.manage'])
    ->prefix('procurements')
    ->name('procurements.')
    ->group(function () {

        Route::get('/{procurement}/eval/assign',
            [ProcurementEvaluationController::class, 'create']
        )
            ->name('eval.assign');

        Route::post('/{procurement}/eval/assign',
            [ProcurementEvaluationController::class, 'store']
        )
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

        // ? SAVE SCORES (AUTOSAVE / DRAFT)
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
    '/procurements/{procurement}/site-visit-report/{siteVisit}/pdf',
    [ProcurementSiteVisitReportController::class, 'downloadVisit']
    )
        ->middleware('permission:site_visits.approve')
        ->name('procurements.site-visit-report.pdf');

    Route::get(
    '/procurements/{procurement}/site-visit-report/{siteVisit}/anonymised-pdf',
    [ProcurementSiteVisitReportController::class, 'downloadAnonymisedVisit']
    )
        ->middleware('permission:site_visits.approve')
        ->name('procurements.site-visit-report.anonymised-pdf');


    Route::get(
    '/reports/site-visits',
    [ProcurementSiteVisitReportController::class, 'index']
    )
        ->middleware('permission:site_visits.approve')
        ->name('reports.index');




});












Route::get('/', [LandingPageController::class, 'index'])->name('landing.index');
Route::get('/gallery', [LandingPageController::class, 'gallery'])->name('gallery');
Route::get('/stream/{filename}', [VideoStreamController::class, 'stream'])
    ->where('filename', '[A-Za-z0-9_.+-]+')
    ->name('video.stream');
Route::get('/contact', [LandingPageController::class, 'contact'])->name('landing.contact');
Route::get('/african-map', [LandingPageController::class, 'africanMap'])->name('landing.african_map');
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
Route::get('/impact-map/treaties-information', [ImpactMapController::class, 'treatiesInformation'])
    ->name('impact.treaties.information');
Route::post('/api/impact-map/filter', [ImpactMapController::class, 'getFilteredData'])
    ->middleware('throttle:60,1')
    ->name('impact.filter');
Route::get('/impact-map/download/pdf', [ImpactMapController::class, 'downloadPdf'])
    ->middleware('throttle:10,1')
    ->name('impact.download.pdf');
Route::get('/impact-map/download/excel', [ImpactMapController::class, 'downloadExcel'])
    ->middleware('throttle:10,1')
    ->name('impact.download.excel');
Route::get('/world-indicators-performance', [WorldIndicatorsController::class, 'index'])
    ->name('world.indicators.performance');
Route::get('/api/world-indicators/country-metrics', [WorldIndicatorsController::class, 'countryMetrics'])
    ->middleware('throttle:120,1')
    ->name('world.indicators.country-metrics');
Route::get('/api/world-indicators/topics', [WorldIndicatorsController::class, 'topics'])
    ->middleware('throttle:120,1')
    ->name('world.indicators.topics');
Route::get('/api/world-indicators/indicators', [WorldIndicatorsController::class, 'indicators'])
    ->middleware('throttle:120,1')
    ->name('world.indicators.indicators');
Route::get('/api/world-indicators/countries', [WorldIndicatorsController::class, 'countries'])
    ->middleware('throttle:120,1')
    ->name('world.indicators.countries');
Route::get('/api/world-indicators/continents', [WorldIndicatorsController::class, 'continents'])
    ->middleware('throttle:120,1')
    ->name('world.indicators.continents');
Route::get('/api/world-indicators/compare', [WorldIndicatorsController::class, 'compare'])
    ->middleware('throttle:120,1')
    ->name('world.indicators.compare');
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
        Route::get('/submission/{submission}/anonymised-pdf', [EvaluationReportController::class, 'submissionAnonymisedPdf'])->name('submission.anonymised-pdf');
        Route::get('/procurement/{procurement}', [EvaluationReportController::class, 'procurement'])->name('procurement');
        Route::get('/procurement/{procurement}/pdf', [EvaluationReportController::class, 'procurementPdf'])->name('procurement.pdf');
        Route::get('/consolidated', [EvaluationReportController::class, 'consolidated'])->name('consolidated');
        Route::get('/consolidated/pdf', [EvaluationReportController::class, 'consolidatedPdf'])->name('consolidated.pdf');
    });

Route::get('/callforproposal', [ApplicantController::class, 'create'])->name('applicants.create');
Route::get('/faq', [ApplicantController::class, 'faq'])->name('applicants.faq');
Route::post('/apply', [ApplicantController::class, 'store'])->name('applicants.store');
Route::get('/events', [ApplicantController::class, 'events'])->name('events');

/*
|--------------------------------------------------------------------------
| NEWS & UPDATES (Public – dummy data template, no auth required)
|--------------------------------------------------------------------------
*/
Route::get('/news', [\App\Http\Controllers\PublicNewsController::class, 'index'])->name('news.index');
Route::post('/news/subscribe', [\App\Http\Controllers\PublicNewsController::class, 'subscribe'])->name('news.subscribe');
Route::get('/news/unsubscribe/{token}', [\App\Http\Controllers\PublicNewsController::class, 'unsubscribe'])->name('news.unsubscribe');
Route::get('/news/{post}', [\App\Http\Controllers\PublicNewsController::class, 'show'])->name('news.show');
Route::get('/news/{post}/attachments/{attachment}', [\App\Http\Controllers\PublicNewsController::class, 'download'])->name('news.attachments.download');

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
        Route::get('/create', [VendorManagementController::class, 'create'])->name('create');
        Route::post('/', [VendorManagementController::class, 'store'])->name('store');
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
        Route::get('/purchase-requests', [VendorRequestManagementController::class, 'purchaseRequestsIndex'])
            ->name('purchase-requests.index');
        Route::get('/purchase-requests/{purchaseRequest}', [VendorRequestManagementController::class, 'purchaseRequestsShow'])
            ->name('purchase-requests.show');
        Route::post('/purchase-requests/{purchaseRequest}/respond', [VendorRequestManagementController::class, 'purchaseRequestsRespond'])
            ->middleware('permission:vendor.requests.respond')
            ->name('purchase-requests.respond');
        Route::get('/reports', [VendorRequestManagementController::class, 'reportsIndex'])
            ->name('reports.index');
        Route::get('/reports/{report}', [VendorRequestManagementController::class, 'reportsShow'])
            ->name('reports.show');
        Route::post('/reports/{report}/respond', [VendorRequestManagementController::class, 'reportsRespond'])
            ->middleware('permission:vendor.requests.respond')
            ->name('reports.respond');
        Route::get('/documents/{document}', [VendorRequestManagementController::class, 'downloadDocument'])
            ->name('documents.download');

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

        Route::get('/reports', [PartnerDashboardController::class, 'reports'])
            ->name('reports.index');

        Route::get('/reports/financial-position', [PartnerDashboardController::class, 'financialPosition'])
            ->name('reports.financial-position');

        Route::get('/think-tank-deep-search', [PartnerDashboardController::class, 'thinkTankDeepSearch'])
            ->name('think-tanks.deep-search');

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

            Route::get('/work-plan', [ApprovedWorkPlanController::class, 'partnerIndex'])
                ->name('workplan.index');

            Route::post('/work-plan/items/{item}/review', [ApprovedWorkPlanController::class, 'partnerReviewItem'])
                ->name('workplan.items.review');

            Route::get('/work-plan/items/{item}/document', [ApprovedWorkPlanController::class, 'partnerDownloadItemDocument'])
                ->name('workplan.items.document');
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
        Route::get('/purchase-requests', [VendorPurchaseRequestController::class, 'index'])
            ->name('purchase-requests.index');
        Route::get('/purchase-requests/create', [VendorPurchaseRequestController::class, 'create'])
            ->name('purchase-requests.create');
        Route::post('/purchase-requests', [VendorPurchaseRequestController::class, 'store'])
            ->name('purchase-requests.store');
        Route::get('/purchase-requests/{purchaseRequest}', [VendorPurchaseRequestController::class, 'show'])
            ->name('purchase-requests.show');
        Route::get('/purchase-requests/{purchaseRequest}/documents/{document}', [VendorPurchaseRequestController::class, 'download'])
            ->name('purchase-requests.documents.download');
        Route::get('/reports', [VendorReportController::class, 'index'])
            ->name('reports.index');
        Route::get('/reports/create', [VendorReportController::class, 'create'])
            ->name('reports.create');
        Route::post('/reports', [VendorReportController::class, 'store'])
            ->name('reports.store');
        Route::get('/reports/{report}', [VendorReportController::class, 'show'])
            ->name('reports.show');
        Route::get('/reports/{report}/documents/{document}', [VendorReportController::class, 'download'])
            ->name('reports.documents.download');
        Route::get('/knowledge', [VendorKnowledgeController::class, 'index'])
            ->name('knowledge.index');
        Route::post('/knowledge', [VendorKnowledgeController::class, 'store'])
            ->name('knowledge.store');
        Route::get('/knowledge/documents/{document}', [VendorKnowledgeController::class, 'download'])
            ->name('knowledge.download');
        Route::get('/knowledge/submission-files/{value}', [VendorKnowledgeController::class, 'downloadSubmissionFile'])
            ->name('knowledge.submission-file');
        Route::get('/work-plan', [VendorThinkTankController::class, 'workPlan'])
            ->name('work-plan.index');
        Route::get('/research-report', [VendorThinkTankController::class, 'researchReport'])
            ->name('research-report.index');
        Route::post('/research-report', [VendorThinkTankController::class, 'storeResearchReport'])
            ->name('research-report.store');
        Route::get('/research-report/{output}/download', [VendorThinkTankController::class, 'downloadResearchReport'])
            ->name('research-report.download');
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
        Route::get('/deliverables', [VendorDeliverableController::class, 'index'])
            ->name('deliverables.index');
        Route::get('/deliverables/sheet', [VendorDeliverableController::class, 'sheet'])
            ->name('deliverables.sheet');
        Route::get('/deliverables/template', [VendorDeliverableController::class, 'template'])
            ->name('deliverables.template');
        Route::post('/deliverables/import', [VendorDeliverableController::class, 'import'])
            ->name('deliverables.import');
        Route::post('/deliverables/{deliverable}/approve', [VendorDeliverableController::class, 'approve'])
            ->name('deliverables.approve');
        Route::post('/deliverables/{deliverable}/status', [VendorDeliverableController::class, 'updateStatus'])
            ->name('deliverables.status');
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
    AuFlagshipProjectController,
    TreatyController
};
use App\Http\Controllers\Treaty\{
    MemberStateTreatyController,
    TreatyDocumentController
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

        // Treaties & Agreements
        Route::resource('treaties', TreatyController::class);
        Route::post('treaties/{treaty}/member-state-statuses', [TreatyController::class, 'syncMemberStateStatuses'])
            ->name('treaties.member-state-statuses.sync');

        // AJAX: Get goals by aspiration IDs
        Route::get('goals/by-aspiration', [AuGoalController::class, 'byAspiration'])
            ->name('goals.by-aspiration');
    });

Route::middleware(['auth', 'member.state'])
    ->prefix('member-state')
    ->name('member-state.')
    ->group(function () {
        Route::get('/dashboard', [MemberStateDashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/communications', [MemberStateCommunicationController::class, 'index'])
            ->name('communications.index');
        Route::post('/communications', [MemberStateCommunicationController::class, 'store'])
            ->name('communications.store');
        Route::delete('/communications/{communication}', [MemberStateCommunicationController::class, 'destroy'])
            ->name('communications.destroy');
        Route::get('/communications/{communication}/attachments/{attachment}', [MemberStateCommunicationController::class, 'downloadAttachment'])
            ->name('communications.attachments.download');

        Route::get('/national-data', [MemberStateNationalDataController::class, 'index'])
            ->name('national-data.index');
        Route::post('/national-data', [MemberStateNationalDataController::class, 'store'])
            ->name('national-data.store');
        Route::delete('/national-data/{entry}', [MemberStateNationalDataController::class, 'destroy'])
            ->name('national-data.destroy');

        Route::get('/comparisons', [MemberStateComparisonController::class, 'index'])
            ->name('comparisons.index');

        Route::get('/questions', [MemberStateQuestionController::class, 'index'])
            ->name('questions.index');
        Route::post('/questions', [MemberStateQuestionController::class, 'store'])
            ->name('questions.store');
        Route::delete('/questions/{question}', [MemberStateQuestionController::class, 'destroy'])
            ->name('questions.destroy');

        Route::get('/commodities', [MemberStateCommodityController::class, 'index'])
            ->name('commodities.index');
        Route::post('/commodities/catalog', [MemberStateCommodityController::class, 'storeCommodity'])
            ->name('commodities.catalog.store');
        Route::post('/commodities/trends', [MemberStateCommodityController::class, 'storeTrend'])
            ->name('commodities.trends.store');
        Route::delete('/commodities/trends/{trend}', [MemberStateCommodityController::class, 'destroyTrend'])
            ->name('commodities.trends.destroy');
    });

Route::middleware(['auth', 'member.state'])
    ->prefix('member-state/treaties')
    ->name('member-state.treaties.')
    ->group(function () {
        Route::get('/', [MemberStateTreatyController::class, 'index'])
            ->middleware('permission:member_state.treaties.view')
            ->name('index');
        Route::post('/{treaty}/status', [MemberStateTreatyController::class, 'updateStatus'])
            ->middleware('permission:member_state.treaties.update')
            ->name('status.update');
        Route::post('/{treaty}/resend-proof-service-email', [MemberStateTreatyController::class, 'resendProofServiceEmail'])
            ->middleware('permission:member_state.treaties.update')
            ->name('status.resend-proof-email');
    });

Route::middleware(['auth'])
    ->get('/treaty-statuses/{treatyStatus}/documents/{type}', [TreatyDocumentController::class, 'download'])
    ->whereIn('type', ['signed', 'ratified', 'original'])
    ->name('treaty-statuses.documents.download');

Route::middleware(['auth'])
    ->get('/treaties/supporting-documents/{supportingDocument}/download', [TreatyDocumentController::class, 'downloadSupportingDocument'])
    ->name('treaties.supporting-documents.download');


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

/*
|--------------------------------------------------------------------------
| ATTP Consortium Operations
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'not.funding.partner', 'permission:consortiums.view'])
    ->prefix('consortium-operations')
    ->name('consortium-operations.')
    ->controller(\App\Http\Controllers\ConsortiumOperationsController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('permission:consortiums.manage')->name('store');
        Route::get('/{consortium}', 'show')->name('show');
        Route::post('/{consortium}/members', 'addMember')->middleware('permission:consortiums.manage')->name('members.store');
        Route::post('/{consortium}/workplans', 'addWorkplan')->middleware('permission:consortiums.manage')->name('workplans.store');
        Route::post('/{consortium}/reports', 'storeReport')->middleware('permission:consortiums.reports.submit|consortiums.manage')->name('reports.store');
        Route::post('/{consortium}/allocations', 'addAllocation')->middleware('permission:consortiums.finance.manage')->name('allocations.store');
        Route::post('/{consortium}/disbursements', 'requestDisbursement')->middleware('permission:consortiums.disbursements.request|consortiums.finance.manage')->name('disbursements.store');
        Route::post('/{consortium}/expenses', 'storeExpense')->middleware('permission:consortiums.expenses.submit|consortiums.finance.manage')->name('expenses.store');
        Route::post('/{consortium}/risks', 'addRisk')->middleware('permission:consortiums.risks.manage')->name('risks.store');
    });

Route::middleware(['auth', 'not.funding.partner', 'permission:consortiums.reports.review'])
    ->post('/consortium-operations/reports/{report}/review', [\App\Http\Controllers\ConsortiumOperationsController::class, 'reviewReport'])
    ->name('consortium-operations.reports.review');

Route::middleware(['auth', 'not.funding.partner', 'permission:consortiums.finance.manage'])
    ->post('/consortium-operations/disbursements/{disbursement}/review', [\App\Http\Controllers\ConsortiumOperationsController::class, 'reviewDisbursement'])
    ->name('consortium-operations.disbursements.review');

Route::middleware(['auth', 'not.funding.partner', 'permission:think_tanks.directory.view|think_tanks.funding.view|consortiums.view|finance.purchase_requests.view|finance.commitments.create|finance.purchase_orders.create'])
    ->prefix('think-tanks-admin')
    ->name('think-tanks-admin.')
    ->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\AdminThinkTankController::class, 'dashboard'])->middleware('permission:think_tanks.funding.view|consortiums.view|finance.purchase_requests.view|finance.commitments.create|finance.purchase_orders.create')->name('dashboard');
        Route::get('/consortium-analysis', [\App\Http\Controllers\AdminThinkTankController::class, 'consortiumAnalysis'])->middleware('permission:think_tanks.funding.view|consortiums.view|finance.purchase_requests.view|finance.commitments.create|finance.purchase_orders.create')->name('consortium-analysis');
        Route::get('/think-tank-analysis', [\App\Http\Controllers\AdminThinkTankController::class, 'thinkTankAnalysis'])->middleware('permission:think_tanks.funding.view|consortiums.view|finance.purchase_requests.view|finance.commitments.create|finance.purchase_orders.create')->name('think-tank-analysis');
        Route::get('/consortium-reports', [\App\Http\Controllers\AdminThinkTankController::class, 'consortiumReports'])->middleware('permission:think_tanks.funding.view|consortiums.view|finance.purchase_requests.view|finance.commitments.create|finance.purchase_orders.create')->name('consortium-reports');
        Route::get('/think-tank-reports', [\App\Http\Controllers\AdminThinkTankController::class, 'thinkTankReports'])->middleware('permission:think_tanks.funding.view|consortiums.view|finance.purchase_requests.view|finance.commitments.create|finance.purchase_orders.create')->name('think-tank-reports');
        Route::get('/directory', [\App\Http\Controllers\AdminThinkTankController::class, 'directory'])->middleware('permission:think_tanks.directory.view')->name('directory');
        Route::post('/directory', [\App\Http\Controllers\AdminThinkTankController::class, 'store'])->middleware('permission:think_tanks.directory.create')->name('store');
        Route::get('/funding', [\App\Http\Controllers\AdminThinkTankController::class, 'funding'])->middleware('permission:think_tanks.funding.view')->name('funding');
        Route::get('/funding/record-transfer', [\App\Http\Controllers\AdminThinkTankController::class, 'createFunding'])->middleware('permission:think_tanks.funding.transfer.create')->name('funding.create');
        Route::post('/funding', [\App\Http\Controllers\AdminThinkTankController::class, 'storeFunding'])->middleware('permission:think_tanks.funding.transfer.create')->name('funding.store');
        Route::get('/funding/history', [\App\Http\Controllers\AdminThinkTankController::class, 'fundingHistory'])->middleware('permission:think_tanks.funding.history.view')->name('funding.history');
        Route::put('/funding/transfers/{transfer}', [\App\Http\Controllers\AdminThinkTankController::class, 'updateFundingTransfer'])->middleware('permission:think_tanks.funding.transfer.edit')->name('funding.transfers.update');
        Route::get('/{thinkTank}', [\App\Http\Controllers\AdminThinkTankController::class, 'show'])->middleware('permission:think_tanks.directory.view')->name('show');
        Route::put('/{thinkTank}', [\App\Http\Controllers\AdminThinkTankController::class, 'update'])->middleware('permission:think_tanks.directory.edit')->name('update');
    });

Route::middleware(['auth', 'not.funding.partner', 'permission:news.manage|news.approve|communications.respond'])
    ->prefix('system/news')
    ->name('system.news.')
    ->controller(\App\Http\Controllers\NewsAdminController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->middleware('permission:news.manage|communications.respond')->name('create');
        Route::post('/', 'store')->middleware('permission:news.manage|communications.respond')->name('store');
        Route::get('/{post}/edit', 'edit')->name('edit');
        Route::put('/{post}', 'update')->middleware('permission:news.manage|communications.respond')->name('update');
        Route::post('/{post}/approval', 'approve')->middleware('permission:news.approve|communications.respond')->name('approve');
        Route::delete('/{post}/attachments/{attachment}', 'destroyAttachment')->middleware('permission:news.manage|communications.respond')->name('attachments.destroy');
    });

/*
|--------------------------------------------------------------------------
| Think Tank Portal
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'think.tank', 'permission:think_tank.portal.access'])
    ->prefix('think-tank')
    ->name('think-tank.')
    ->controller(\App\Http\Controllers\ThinkTankPortalController::class)
    ->group(function () {
        Route::get('/dashboard', 'dashboard')->name('dashboard');
        Route::get('/dashboard/download', 'downloadDashboardReport')->middleware('permission:think_tank.dashboard.download')->name('dashboard.download');
        Route::get('/upload-report-finding', 'uploadReportFinding')->middleware('permission:think_tank.reports.submit')->name('upload-report-finding');
        Route::get('/reports', 'reports')->middleware('permission:think_tank.reports.view|think_tank.reports.submit')->name('reports');
        Route::get('/reports/download', 'downloadReports')->middleware('permission:think_tank.reports.download')->name('reports.download');
        Route::post('/reports', 'storeReport')->middleware('permission:think_tank.reports.submit')->name('reports.store');
        Route::get('/research', 'research')->middleware('permission:think_tank.research.view|think_tank.research.submit')->name('research');
        Route::get('/research/download', 'downloadResearch')->middleware('permission:think_tank.research.download')->name('research.download');
        Route::get('/research/{output}/qasc-preview', 'previewResearchQasc')->middleware('permission:think_tank.research.view|think_tank.research.submit')->name('research.qasc.preview');
        Route::post('/research', 'storeResearch')->middleware('permission:think_tank.research.submit')->name('research.store');
        Route::get('/purchase-orders', 'purchaseOrders')->name('purchase-orders');
        Route::post('/purchase-orders', 'storePurchaseOrder')->middleware('permission:think_tank.procurement.manage')->name('purchase-orders.store');
        Route::get('/purchase-orders/{purchaseOrder}', 'showPurchaseOrder')->name('purchase-orders.show');
        Route::post('/purchase-orders/{purchaseOrder}/disbursements/{disbursement}/confirm', 'confirmDisbursementReceipt')->name('purchase-orders.disbursements.confirm');
        Route::get('/purchase-orders/{purchaseOrder}/pdf', 'purchaseOrderPdf')->name('purchase-orders.pdf');
        Route::get('/purchase-orders/{purchaseOrder}/download', 'downloadPurchaseOrder')->name('purchase-orders.download');
        Route::get('/procurement', 'procurement')->middleware('permission:think_tank.procurement.view|think_tank.procurement.manage|think_tank.procurement.evaluate|think_tank.procurement.select')->name('procurement');
        Route::get('/procurement/download', 'downloadProcurement')->middleware('permission:think_tank.procurement.download')->name('procurement.download');
        Route::post('/procurement/plans', 'storeProcurementPlan')->middleware('permission:think_tank.procurement.manage')->name('procurement.plans.store');
        Route::post('/procurement', 'storeProcurement')->middleware('permission:think_tank.procurement.manage')->name('procurement.store');
        Route::get('/procurement/{procurement}/submissions', 'submissions')->middleware('permission:think_tank.procurement.evaluate')->name('procurement.submissions');
        Route::post('/procurement/{procurement}/submissions/{submission}/review', 'reviewSubmission')->middleware('permission:think_tank.procurement.evaluate')->name('procurement.submissions.review');
        Route::post('/procurement/{procurement}/submissions/{submission}/select', 'selectSubmission')->middleware('permission:think_tank.procurement.select')->name('procurement.submissions.select');
    });
