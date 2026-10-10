<?php

use App\Http\Controllers\Api\V1\ThinkTank\AccessLevelController;
use App\Http\Controllers\Api\V1\ThinkTank\AuthenticationController;
use App\Http\Controllers\Api\V1\ThinkTank\FinanceController;
use App\Http\Controllers\Api\V1\ThinkTank\MeController;
use App\Http\Controllers\Api\V1\ThinkTank\MfaController;
use App\Http\Controllers\Api\V1\ThinkTank\MonitoringAssignmentsController;
use App\Http\Controllers\Api\V1\ThinkTank\MonitoringNotificationsController;
use App\Http\Controllers\Api\V1\ThinkTank\MonitoringOverviewController;
use App\Http\Controllers\Api\V1\ThinkTank\MonitoringReportAchievementsController;
use App\Http\Controllers\Api\V1\ThinkTank\MonitoringReportsController;
use App\Http\Controllers\Api\V1\ThinkTank\MonitoringResultsController;
use App\Http\Controllers\Api\V1\ThinkTank\PasswordController;
use App\Http\Controllers\Api\V1\ThinkTank\ProcurementController;
use App\Http\Controllers\Api\V1\ThinkTank\ProcurementExecutionApplicationController;
use App\Http\Controllers\Api\V1\ThinkTank\ProcurementExecutionController;
use App\Http\Controllers\Api\V1\ThinkTank\UserController;
use App\Http\Controllers\Api\V1\ThinkTank\VendorDirectoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/think-tank')
    ->name('api.v1.think-tank.')
    ->middleware(['think.tank.api.no-store', 'think.tank.api.stateful'])
    ->group(function (): void {
        Route::get('auth/session', [AuthenticationController::class, 'session'])
            ->middleware('throttle:120,1,think-tank-session')
            ->name('auth.session');
        Route::post('auth/login', [AuthenticationController::class, 'login'])
            ->middleware('throttle:10,1,think-tank-login')
            ->name('auth.login');
        Route::post('auth/password/forgot', [PasswordController::class, 'forgot'])
            ->middleware('throttle:5,1,think-tank-password-forgot')
            ->name('auth.password.forgot');
        Route::post('auth/password/reset', [PasswordController::class, 'reset'])
            ->middleware('throttle:10,1,think-tank-password-reset')
            ->name('auth.password.reset');
        Route::post('auth/logout', [AuthenticationController::class, 'logout'])
            ->middleware('throttle:30,1,think-tank-logout')
            ->name('auth.logout');
        Route::post('auth/mfa/resend', [MfaController::class, 'resend'])
            ->middleware('throttle:5,1,think-tank-mfa-resend')
            ->name('auth.mfa.resend');
        Route::post('auth/mfa/verify', [MfaController::class, 'verify'])
            ->middleware('throttle:10,1,think-tank-mfa-verify')
            ->name('auth.mfa.verify');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::middleware('think.tank.api.account')->group(function (): void {
                Route::put('auth/password', [PasswordController::class, 'update'])
                    ->middleware('throttle:10,1,think-tank-password-change')
                    ->name('auth.password.update');

                Route::middleware('think.tank.api.ready')->group(function (): void {
                    Route::get('me', MeController::class)->name('me');
                    Route::get('access-levels', AccessLevelController::class)->name('access-levels');

                    Route::prefix('procurement')
                        ->name('procurement.')
                        ->middleware('think.tank.area:procurement_plans')
                        ->group(function (): void {
                            Route::middleware('permission:think_tank.procurement_plans.view|think_tank.procurement_plans.manage')
                                ->group(function (): void {
                                    Route::get('overview', [ProcurementController::class, 'overview'])
                                        ->name('overview');
                                    Route::get('plans', [ProcurementController::class, 'index'])
                                        ->name('plans.index');
                                    Route::get('plans/{plan}', [ProcurementController::class, 'showPlan'])
                                        ->whereUuid('plan')
                                        ->name('plans.show');
                                    Route::get('reports/usage-approvals', [ProcurementController::class, 'usageApprovals'])
                                        ->name('reports.usage-approvals');
                                    Route::get('executions/overview', [ProcurementExecutionController::class, 'overview'])
                                        ->name('executions.overview');
                                    Route::get('executions/eligible-items', [ProcurementExecutionController::class, 'eligibleItems'])
                                        ->name('executions.eligible-items');
                                    Route::get('executions/reports', [ProcurementExecutionController::class, 'reports'])
                                        ->name('executions.reports');
                                    Route::get('executions', [ProcurementExecutionController::class, 'index'])
                                        ->name('executions.index');
                                    Route::get('vendor-directory', [VendorDirectoryController::class, 'index'])
                                        ->name('vendor-directory.index');
                                    Route::middleware('permission:think_tank.procurement.evaluate')
                                        ->group(function (): void {
                                            Route::get('executions/{execution}/applications', [ProcurementExecutionApplicationController::class, 'index'])
                                                ->whereUuid('execution')
                                                ->name('executions.applications.index');
                                            Route::get('executions/{execution}/applications/{submission}', [ProcurementExecutionApplicationController::class, 'show'])
                                                ->whereUuid(['execution', 'submission'])
                                                ->name('executions.applications.show');
                                            Route::get('executions/{execution}/applications/{submission}/values/{value}/download', [ProcurementExecutionApplicationController::class, 'download'])
                                                ->whereUuid(['execution', 'submission', 'value'])
                                                ->name('executions.applications.values.download');
                                        });
                                    Route::get('executions/{execution}/preview', [ProcurementExecutionController::class, 'preview'])
                                        ->whereUuid('execution')
                                        ->name('executions.preview');
                                    Route::get('executions/{execution}/documents/{document}', [ProcurementExecutionController::class, 'document'])
                                        ->whereUuid(['execution', 'document'])
                                        ->name('executions.documents.show');
                                    Route::get('executions/{execution}/cover', [ProcurementExecutionController::class, 'cover'])
                                        ->whereUuid('execution')
                                        ->name('executions.cover.show');
                                    Route::get('executions/{execution}', [ProcurementExecutionController::class, 'show'])
                                        ->whereUuid('execution')
                                        ->name('executions.show');
                                    Route::get('plans/{plan}/items/{item}/documents/{document}', [ProcurementController::class, 'document'])
                                        ->whereUuid(['plan', 'item', 'document'])
                                        ->name('documents.show');
                                });

                            Route::middleware('permission:think_tank.procurement_plans.manage')
                                ->group(function (): void {
                                    Route::post('plans', [ProcurementController::class, 'storePlan'])
                                        ->middleware('throttle:10,1,think-tank-procurement-plan-create')
                                        ->name('plans.store');
                                    Route::patch('plans/{plan}', [ProcurementController::class, 'updatePlan'])
                                        ->whereUuid('plan')
                                        ->middleware('throttle:30,1,think-tank-procurement-plan-update')
                                        ->name('plans.update');
                                    Route::post('plans/{plan}/submit', [ProcurementController::class, 'submitPlan'])
                                        ->whereUuid('plan')
                                        ->middleware('throttle:10,1,think-tank-procurement-plan-submit')
                                        ->name('plans.submit');
                                    Route::post('plans/{plan}/items', [ProcurementController::class, 'storeItem'])
                                        ->whereUuid('plan')
                                        ->middleware('throttle:30,1,think-tank-procurement-item-create')
                                        ->name('items.store');
                                    Route::patch('plans/{plan}/items/{item}', [ProcurementController::class, 'updateItem'])
                                        ->whereUuid(['plan', 'item'])
                                        ->middleware('throttle:30,1,think-tank-procurement-item-update')
                                        ->name('items.update');
                                    Route::delete('plans/{plan}/items/{item}', [ProcurementController::class, 'destroyItem'])
                                        ->whereUuid(['plan', 'item'])
                                        ->middleware('throttle:20,1,think-tank-procurement-item-delete')
                                        ->name('items.destroy');
                                    Route::delete('plans/{plan}/items/{item}/documents/{document}', [ProcurementController::class, 'destroyDocument'])
                                        ->whereUuid(['plan', 'item', 'document'])
                                        ->middleware('throttle:20,1,think-tank-procurement-document-delete')
                                        ->name('documents.destroy');
                                    Route::post('executions', [ProcurementExecutionController::class, 'store'])
                                        ->middleware('throttle:10,1,think-tank-procurement-execution-create')
                                        ->name('executions.store');
                                    Route::patch('executions/{execution}', [ProcurementExecutionController::class, 'update'])
                                        ->whereUuid('execution')
                                        ->middleware('throttle:30,1,think-tank-procurement-execution-update')
                                        ->name('executions.update');
                                    Route::delete('executions/{execution}', [ProcurementExecutionController::class, 'destroy'])
                                        ->whereUuid('execution')
                                        ->middleware('throttle:10,1,think-tank-procurement-execution-delete')
                                        ->name('executions.destroy');
                                    Route::put('executions/{execution}/form', [ProcurementExecutionController::class, 'updateForm'])
                                        ->whereUuid('execution')
                                        ->middleware('throttle:20,1,think-tank-procurement-execution-form')
                                        ->name('executions.form.update');
                                    Route::post('executions/{execution}/documents', [ProcurementExecutionController::class, 'storeDocuments'])
                                        ->whereUuid('execution')
                                        ->middleware('throttle:20,1,think-tank-procurement-execution-documents')
                                        ->name('executions.documents.store');
                                    Route::delete('executions/{execution}/documents/{document}', [ProcurementExecutionController::class, 'destroyDocument'])
                                        ->whereUuid(['execution', 'document'])
                                        ->middleware('throttle:20,1,think-tank-procurement-execution-documents-delete')
                                        ->name('executions.documents.destroy');
                                    Route::delete('executions/{execution}/cover', [ProcurementExecutionController::class, 'destroyCover'])
                                        ->whereUuid('execution')
                                        ->middleware('throttle:20,1,think-tank-procurement-execution-cover-delete')
                                        ->name('executions.cover.destroy');
                                    Route::post('executions/{execution}/publish', [ProcurementExecutionController::class, 'publish'])
                                        ->whereUuid('execution')
                                        ->middleware('throttle:10,1,think-tank-procurement-execution-publish')
                                        ->name('executions.publish');
                                    Route::post('executions/{execution}/recall', [ProcurementExecutionController::class, 'recall'])
                                        ->whereUuid('execution')
                                        ->middleware('throttle:10,1,think-tank-procurement-execution-recall')
                                        ->name('executions.recall');
                                    Route::post('executions/{execution}/republish', [ProcurementExecutionController::class, 'republish'])
                                        ->whereUuid('execution')
                                        ->middleware('throttle:10,1,think-tank-procurement-execution-republish')
                                        ->name('executions.republish');
                                    Route::post('vendor-categories', [VendorDirectoryController::class, 'storeCategory'])
                                        ->middleware('throttle:20,1,think-tank-vendor-category-create')
                                        ->name('vendor-categories.store');
                                    Route::post('vendors', [VendorDirectoryController::class, 'storeVendor'])
                                        ->middleware('throttle:10,1,think-tank-vendor-create')
                                        ->name('vendors.store');
                                    Route::patch('vendors/{vendor}', [VendorDirectoryController::class, 'updateVendor'])
                                        ->whereUuid('vendor')
                                        ->middleware('throttle:20,1,think-tank-vendor-update')
                                        ->name('vendors.update');
                                    Route::post('vendors/{vendor}/invitation', [VendorDirectoryController::class, 'resendInvitation'])
                                        ->whereUuid('vendor')
                                        ->middleware('throttle:5,1,think-tank-vendor-invitation')
                                        ->name('vendors.invitation');
                                });
                        });

                    Route::prefix('finance')
                        ->name('finance.')
                        ->middleware('think.tank.area:finance')
                        ->group(function (): void {
                            Route::middleware('permission:think_tank.finance.view|think_tank.finance.manage')
                                ->group(function (): void {
                                    Route::get('overview', [FinanceController::class, 'overview'])->name('overview');
                                    Route::get('funds', [FinanceController::class, 'funds'])->name('funds.index');
                                    Route::get('budget-lines', [FinanceController::class, 'budgetLines'])->name('budget-lines.index');
                                    Route::get('execution', [FinanceController::class, 'execution'])->name('execution.index');
                                    Route::get('reports', [FinanceController::class, 'reports'])->name('reports.index');
                                });

                            Route::middleware('permission:think_tank.finance.manage')
                                ->group(function (): void {
                                    Route::post('funding-requests', [FinanceController::class, 'fundingRequest'])
                                        ->middleware('throttle:10,1,think-tank-finance-funding-request')
                                        ->name('funding-requests.store');
                                    Route::post('transfers/{disbursement}/confirm', [FinanceController::class, 'confirmTransfer'])
                                        ->whereUuid('disbursement')
                                        ->middleware('throttle:20,1,think-tank-finance-receipt-confirm')
                                        ->name('transfers.confirm');
                                    Route::post('budget-lines', [FinanceController::class, 'storeBudgetLine'])
                                        ->middleware('throttle:20,1,think-tank-finance-budget-line-create')
                                        ->name('budget-lines.store');
                                    Route::patch('budget-lines/{line}', [FinanceController::class, 'updateBudgetLine'])
                                        ->whereUuid('line')
                                        ->middleware('throttle:30,1,think-tank-finance-budget-line-update')
                                        ->name('budget-lines.update');
                                });
                        });

                    Route::prefix('monitoring')
                        ->name('monitoring.')
                        ->middleware('think.tank.area:me')
                        ->group(function (): void {
                            Route::get('overview', MonitoringOverviewController::class)
                                ->middleware('permission:think_tank.me.view|think_tank.me.submit|think_tank.me.reports.view|think_tank.me.reports.manage|think_tank.me.reports.submit|think_tank.me.notifications.view')
                                ->name('overview');

                            Route::middleware('permission:think_tank.me.view|think_tank.me.submit')->group(function (): void {
                                Route::get('assignments', [MonitoringAssignmentsController::class, 'index'])
                                    ->name('assignments.index');
                                Route::get('assignments/{assignment}', [MonitoringAssignmentsController::class, 'apiShow'])
                                    ->whereUuid('assignment')
                                    ->name('assignments.show');
                                Route::get('assignments/{assignment}/attachments/{attachment}', [MonitoringAssignmentsController::class, 'attachment'])
                                    ->whereUuid(['assignment', 'attachment'])
                                    ->name('assignments.attachments.show');
                                Route::get('results', MonitoringResultsController::class)
                                    ->name('results.index');
                            });

                            Route::middleware('permission:think_tank.me.submit')->group(function (): void {
                                Route::post('assignments/{assignment}/draft', [MonitoringAssignmentsController::class, 'draft'])
                                    ->whereUuid('assignment')
                                    ->middleware('throttle:30,1,think-tank-me-draft')
                                    ->name('assignments.draft');
                                Route::post('assignments/{assignment}/submit', [MonitoringAssignmentsController::class, 'apiSubmit'])
                                    ->whereUuid('assignment')
                                    ->middleware('throttle:10,1,think-tank-me-submit')
                                    ->name('assignments.submit');
                            });

                            Route::middleware('permission:think_tank.me.reports.view|think_tank.me.reports.manage|think_tank.me.reports.submit')->group(function (): void {
                                Route::get('reports', [MonitoringReportsController::class, 'apiIndex'])
                                    ->name('reports.index');
                                Route::get('reports/{report}', [MonitoringReportsController::class, 'apiShow'])
                                    ->whereUuid('report')
                                    ->name('reports.show');
                                Route::get('reports/{report}/documents/{document}', [MonitoringReportsController::class, 'document'])
                                    ->whereUuid(['report', 'document'])
                                    ->name('reports.documents.show');
                                Route::get('reports/{report}/achievements/{achievement}/evidence/{evidence}', [MonitoringReportsController::class, 'achievementEvidence'])
                                    ->whereUuid(['report', 'achievement', 'evidence'])
                                    ->name('reports.achievement-evidence.show');
                            });

                            Route::middleware('permission:think_tank.me.reports.manage')->group(function (): void {
                                Route::post('reports', [MonitoringReportsController::class, 'apiStore'])
                                    ->middleware('throttle:10,1,think-tank-me-report-create')
                                    ->name('reports.store');
                                Route::put('reports/{report}', [MonitoringReportsController::class, 'apiUpdate'])
                                    ->whereUuid('report')
                                    ->middleware('throttle:30,1,think-tank-me-report-update')
                                    ->name('reports.update');
                                Route::delete('reports/{report}/documents/{document}', [MonitoringReportsController::class, 'apiDestroyDocument'])
                                    ->whereUuid(['report', 'document'])
                                    ->name('reports.documents.destroy');

                                Route::post('reports/{report}/indicator-results/{result}/achievements', [MonitoringReportAchievementsController::class, 'storeAchievement'])
                                    ->whereUuid(['report', 'result'])
                                    ->name('reports.achievements.store');
                                Route::patch('reports/{report}/indicator-results/{result}/achievements/{achievement}', [MonitoringReportAchievementsController::class, 'updateAchievement'])
                                    ->whereUuid(['report', 'result', 'achievement'])
                                    ->name('reports.achievements.update');
                                Route::delete('reports/{report}/indicator-results/{result}/achievements/{achievement}', [MonitoringReportAchievementsController::class, 'destroyAchievement'])
                                    ->whereUuid(['report', 'result', 'achievement'])
                                    ->name('reports.achievements.destroy');
                                Route::post('reports/{report}/achievements/{achievement}/breakdowns', [MonitoringReportAchievementsController::class, 'apiStoreBreakdown'])
                                    ->whereUuid(['report', 'achievement'])
                                    ->name('reports.achievements.breakdowns.store');
                                Route::delete('reports/{report}/achievements/{achievement}/breakdowns/{breakdown}', [MonitoringReportAchievementsController::class, 'apiDestroyBreakdown'])
                                    ->whereUuid(['report', 'achievement', 'breakdown'])
                                    ->name('reports.achievements.breakdowns.destroy');
                                Route::post('reports/{report}/achievements/{achievement}/evidence', [MonitoringReportAchievementsController::class, 'apiStoreEvidence'])
                                    ->whereUuid(['report', 'achievement'])
                                    ->name('reports.achievements.evidence.store');
                                Route::delete('reports/{report}/achievements/{achievement}/evidence/{evidence}', [MonitoringReportAchievementsController::class, 'apiUnlinkEvidence'])
                                    ->whereUuid(['report', 'achievement', 'evidence'])
                                    ->name('reports.achievements.evidence.destroy');
                            });

                            Route::post('reports/{report}/submit', [MonitoringReportsController::class, 'apiSubmit'])
                                ->whereUuid('report')
                                ->middleware(['permission:think_tank.me.reports.submit', 'throttle:10,1,think-tank-me-report-submit'])
                                ->name('reports.submit');

                            Route::middleware('permission:think_tank.me.notifications.view')->group(function (): void {
                                Route::get('notifications', [MonitoringNotificationsController::class, 'index'])
                                    ->name('notifications.index');
                                Route::post('notifications/read-all', [MonitoringNotificationsController::class, 'readAll'])
                                    ->name('notifications.read-all');
                                Route::post('notifications/{notification}/read', [MonitoringNotificationsController::class, 'read'])
                                    ->whereUuid('notification')
                                    ->name('notifications.read');
                            });
                        });

                    Route::middleware('think.tank.api.users.manage')->group(function (): void {
                        Route::get('users', [UserController::class, 'index'])->name('users.index');
                        Route::post('users', [UserController::class, 'store'])
                            ->middleware('throttle:20,1,think-tank-users-create')
                            ->name('users.store');
                        Route::get('users/{user}', [UserController::class, 'show'])
                            ->whereUuid('user')
                            ->name('users.show');
                        Route::patch('users/{user}', [UserController::class, 'update'])
                            ->whereUuid('user')
                            ->middleware('throttle:30,1,think-tank-users-update')
                            ->name('users.update');
                        Route::post('users/{user}/invitation', [UserController::class, 'invitation'])
                            ->whereUuid('user')
                            ->middleware('throttle:5,1,think-tank-users-invitation')
                            ->name('users.invitation');
                    });
                });
            });
        });
    });
