<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Activity;
use App\Models\ApprovedWorkPlan;
use App\Models\BudgetCommitment;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationSubmission;
use App\Models\Indicator;
use App\Models\Procurement;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementInvoice;
use App\Models\ProcurementPurchaseOrder;
use App\Models\Program;
use App\Models\ProgramFunding;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\Resource;
use App\Models\ResourceCategory;
use App\Models\Sector;
use App\Models\SubActivity;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

trait ScopesAssignedPortfolios
{
    private array $assignedPortfolioIdsCache = [];

    protected function userHasAssignedPortfolioScope(?User $user = null): bool
    {
        $user ??= Auth::user();

        return $user
            && $user->role
            && in_array($user->role->name, ['Portfolio Manager', 'Portfolio Coordinator'], true);
    }

    protected function assignedPortfolioIds(?User $user = null): array
    {
        $user ??= Auth::user();
        if (! $this->userHasAssignedPortfolioScope($user)) {
            return [];
        }

        $cacheKey = (string) $user->id . '|' . Str::lower((string) $user->email);
        if (array_key_exists($cacheKey, $this->assignedPortfolioIdsCache)) {
            return $this->assignedPortfolioIdsCache[$cacheKey];
        }

        $ids = Sector::query()
            ->where(function ($query) use ($user) {
                $query->where('portfolio_manager_user_id', $user->id);

                if (filled($user->email)) {
                    $query->orWhereRaw('LOWER(portfolio_manager_email) = ?', [Str::lower($user->email)]);
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return $this->assignedPortfolioIdsCache[$cacheKey] = $ids;
    }

    protected function assignedPortfolioNodeIds(?User $user = null): array
    {
        $portfolioIds = $this->assignedPortfolioIds($user);
        if (empty($portfolioIds)) {
            return [];
        }

        return Sector::query()
            ->whereIn('id', $portfolioIds)
            ->whereNotNull('governance_node_id')
            ->pluck('governance_node_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function assignedProgramIds(?User $user = null): array
    {
        $portfolioIds = $this->assignedPortfolioIds($user);
        if (empty($portfolioIds)) {
            return [];
        }

        return Program::query()
            ->whereIn('sector_id', $portfolioIds)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    protected function assignedProjectIds(?User $user = null): array
    {
        $portfolioIds = $this->assignedPortfolioIds($user);
        if (empty($portfolioIds)) {
            return [];
        }

        return Project::query()
            ->whereHas('program', fn ($query) => $query->whereIn('sector_id', $portfolioIds))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    protected function assignedActivityIds(?User $user = null): array
    {
        $portfolioIds = $this->assignedPortfolioIds($user);
        if (empty($portfolioIds)) {
            return [];
        }

        return Activity::query()
            ->whereHas('project.program', fn ($query) => $query->whereIn('sector_id', $portfolioIds))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    protected function assignedSubActivityIds(?User $user = null): array
    {
        $portfolioIds = $this->assignedPortfolioIds($user);
        if (empty($portfolioIds)) {
            return [];
        }

        return SubActivity::query()
            ->whereHas('activity.project.program', fn ($query) => $query->whereIn('sector_id', $portfolioIds))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    protected function applyAssignedPortfolioScopeToSectors($query, ?User $user = null): void
    {
        $portfolioIds = $this->assignedPortfolioIds($user);

        if (empty($portfolioIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn('id', $portfolioIds);
    }

    protected function applyAssignedPortfolioScopeToPrograms($query, ?User $user = null): void
    {
        $portfolioIds = $this->assignedPortfolioIds($user);

        if (empty($portfolioIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn('sector_id', $portfolioIds);
    }

    protected function applyAssignedPortfolioScopeToProjects($query, ?User $user = null): void
    {
        $portfolioIds = $this->assignedPortfolioIds($user);

        if (empty($portfolioIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas('program', fn ($programQuery) => $programQuery->whereIn('sector_id', $portfolioIds));
    }

    protected function applyAssignedPortfolioScopeToProgramFundings($query, ?User $user = null): void
    {
        $programIds = $this->assignedProgramIds($user);

        if (empty($programIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn('program_id', $programIds);
    }

    protected function applyAssignedPortfolioScopeToActivities($query, ?User $user = null): void
    {
        $portfolioIds = $this->assignedPortfolioIds($user);

        if (empty($portfolioIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas('project.program', fn ($programQuery) => $programQuery->whereIn('sector_id', $portfolioIds));
    }

    protected function applyAssignedPortfolioScopeToSubActivities($query, ?User $user = null): void
    {
        $portfolioIds = $this->assignedPortfolioIds($user);

        if (empty($portfolioIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereHas('activity.project.program', fn ($programQuery) => $programQuery->whereIn('sector_id', $portfolioIds));
    }

    protected function applyAssignedPortfolioScopeToAllocatables($query, ?User $user = null): void
    {
        $projectIds = $this->assignedProjectIds($user);
        $activityIds = $this->assignedActivityIds($user);
        $subActivityIds = $this->assignedSubActivityIds($user);

        if (empty($projectIds) && empty($activityIds) && empty($subActivityIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function ($scope) use ($projectIds, $activityIds, $subActivityIds) {
            if (! empty($projectIds)) {
                $scope->orWhere(function ($q) use ($projectIds) {
                    $q->where('allocation_level', 'project')
                        ->whereIn('allocation_id', $projectIds);
                });
            }

            if (! empty($activityIds)) {
                $scope->orWhere(function ($q) use ($activityIds) {
                    $q->where('allocation_level', 'activity')
                        ->whereIn('allocation_id', $activityIds);
                });
            }

            if (! empty($subActivityIds)) {
                $scope->orWhere(function ($q) use ($subActivityIds) {
                    $q->where('allocation_level', 'sub_activity')
                        ->whereIn('allocation_id', $subActivityIds);
                });
            }
        });
    }

    protected function applyAssignedPortfolioScopeToCommitments($query, ?User $user = null): void
    {
        $this->applyAssignedPortfolioScopeToAllocatables($query, $user);
    }

    protected function applyAssignedPortfolioScopeToPurchaseRequests($query, ?User $user = null): void
    {
        $this->applyAssignedPortfolioScopeToAllocatables($query, $user);
    }

    protected function applyAssignedPortfolioScopeToPurchaseOrders($query, ?User $user = null): void
    {
        $projectIds = $this->assignedProjectIds($user);
        $activityIds = $this->assignedActivityIds($user);
        $subActivityIds = $this->assignedSubActivityIds($user);

        if (empty($projectIds) && empty($activityIds) && empty($subActivityIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function ($scope) use ($user, $subActivityIds) {
            $scope->whereIn('sub_activity_id', $subActivityIds)
                ->orWhereHas('purchaseRequest', function ($purchaseRequestQuery) use ($user) {
                    $this->applyAssignedPortfolioScopeToPurchaseRequests($purchaseRequestQuery, $user);
                })
                ->orWhereHas('budgetCommitment', function ($commitmentQuery) use ($user) {
                    $this->applyAssignedPortfolioScopeToCommitments($commitmentQuery, $user);
                });
        });
    }

    protected function applyAssignedPortfolioScopeToInvoices($query, ?User $user = null): void
    {
        $projectIds = $this->assignedProjectIds($user);
        $activityIds = $this->assignedActivityIds($user);
        $subActivityIds = $this->assignedSubActivityIds($user);

        if (empty($projectIds) && empty($activityIds) && empty($subActivityIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function ($scope) use ($user, $subActivityIds) {
            $scope->whereIn('sub_activity_id', $subActivityIds)
                ->orWhereHas('purchaseOrder', function ($purchaseOrderQuery) use ($user) {
                    $this->applyAssignedPortfolioScopeToPurchaseOrders($purchaseOrderQuery, $user);
                });
        });
    }

    protected function applyAssignedPortfolioScopeToDisbursements($query, ?User $user = null): void
    {
        $projectIds = $this->assignedProjectIds($user);
        $activityIds = $this->assignedActivityIds($user);
        $subActivityIds = $this->assignedSubActivityIds($user);

        if (empty($projectIds) && empty($activityIds) && empty($subActivityIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function ($scope) use ($user, $subActivityIds) {
            $scope->whereIn('sub_activity_id', $subActivityIds)
                ->orWhereHas('purchaseOrder', function ($purchaseOrderQuery) use ($user) {
                    $this->applyAssignedPortfolioScopeToPurchaseOrders($purchaseOrderQuery, $user);
                });
        });
    }

    protected function applyAssignedPortfolioScopeToResourceNodes($query, ?User $user = null): void
    {
        $nodeIds = $this->assignedPortfolioNodeIds($user);

        if (empty($nodeIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn('governance_node_id', $nodeIds)
            ->whereNotNull('governance_node_id');
    }

    protected function applyAssignedPortfolioScopeToProcurements($query, ?User $user = null): void
    {
        $nodeIds = $this->assignedPortfolioNodeIds($user);

        if (empty($nodeIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn('governance_node_id', $nodeIds)
            ->whereNotNull('governance_node_id');
    }

    protected function applyAssignedPortfolioScopeToEvaluations($query, ?User $user = null): void
    {
        $portfolioIds = $this->assignedPortfolioIds($user);

        if (empty($portfolioIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn('portfolio_id', $portfolioIds)
            ->whereNotNull('portfolio_id');
    }

    protected function applyAssignedPortfolioScopeToEvaluationAssignments($query, ?User $user = null): void
    {
        $query->whereHas('procurement', function ($procurementQuery) use ($user) {
            $this->applyAssignedPortfolioScopeToProcurements($procurementQuery, $user);
        });
    }

    protected function applyAssignedPortfolioScopeToEvaluationSubmissions($query, ?User $user = null): void
    {
        $query->whereHas('procurement', function ($procurementQuery) use ($user) {
            $this->applyAssignedPortfolioScopeToProcurements($procurementQuery, $user);
        });
    }

    protected function applyAssignedPortfolioScopeToApprovedWorkPlans($query, ?User $user = null): void
    {
        $nodeIds = $this->assignedPortfolioNodeIds($user);

        if (empty($nodeIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function ($scope) use ($user, $nodeIds) {
            $scope->whereIn('governance_node_id', $nodeIds)
                ->orWhereHas('budgetCommitment', function ($commitmentQuery) use ($user) {
                    $this->applyAssignedPortfolioScopeToCommitments($commitmentQuery, $user);
                })
                ->orWhereHas('programFunding', function ($fundingQuery) use ($user) {
                    $this->applyAssignedPortfolioScopeToProgramFundings($fundingQuery, $user);
                });
        });
    }

    protected function applyAssignedPortfolioScopeToIndicators($query, ?User $user = null): void
    {
        $portfolioIds = $this->assignedPortfolioIds($user);
        $programIds = $this->assignedProgramIds($user);
        $projectIds = $this->assignedProjectIds($user);
        $activityIds = $this->assignedActivityIds($user);
        $subActivityIds = $this->assignedSubActivityIds($user);

        if (
            empty($portfolioIds)
            && empty($programIds)
            && empty($projectIds)
            && empty($activityIds)
            && empty($subActivityIds)
        ) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function ($scope) use ($portfolioIds, $programIds, $projectIds, $activityIds, $subActivityIds) {
            if (! empty($portfolioIds)) {
                $scope->orWhere(function ($q) use ($portfolioIds) {
                    $q->where('indicatorable_type', Sector::class)
                        ->whereIn('indicatorable_id', $portfolioIds);
                });
            }

            if (! empty($programIds)) {
                $scope->orWhere(function ($q) use ($programIds) {
                    $q->where('indicatorable_type', Program::class)
                        ->whereIn('indicatorable_id', $programIds);
                });
            }

            if (! empty($projectIds)) {
                $scope->orWhere(function ($q) use ($projectIds) {
                    $q->where('indicatorable_type', Project::class)
                        ->whereIn('indicatorable_id', $projectIds);
                });
            }

            if (! empty($activityIds)) {
                $scope->orWhere(function ($q) use ($activityIds) {
                    $q->where('indicatorable_type', Activity::class)
                        ->whereIn('indicatorable_id', $activityIds);
                });
            }

            if (! empty($subActivityIds)) {
                $scope->orWhere(function ($q) use ($subActivityIds) {
                    $q->where('indicatorable_type', SubActivity::class)
                        ->whereIn('indicatorable_id', $subActivityIds);
                });
            }
        });
    }

    protected function applyAssignedPortfolioScopeToPortfolioOwnedRecords(
        $query,
        ?User $user = null,
        string $column = 'portfolio_id'
    ): void {
        $portfolioIds = $this->assignedPortfolioIds($user);

        if (empty($portfolioIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn($column, $portfolioIds);
    }

    protected function portfolioOwnedRecordIsInAssignedPortfolio(
        object $record,
        ?User $user = null,
        string $column = 'portfolio_id'
    ): bool {
        $portfolioId = $record->{$column} ?? null;

        if (! $portfolioId) {
            return false;
        }

        return in_array((string) $portfolioId, $this->assignedPortfolioIds($user), true);
    }

    protected function sectorIsAssignedToUser(Sector|string|null $sector, ?User $user = null): bool
    {
        $sectorId = $sector instanceof Sector ? $sector->id : $sector;

        return $sectorId && in_array((string) $sectorId, $this->assignedPortfolioIds($user), true);
    }

    protected function programIsInAssignedPortfolio(Program $program, ?User $user = null): bool
    {
        return $this->sectorIsAssignedToUser($program->sector_id, $user);
    }

    protected function projectIsInAssignedPortfolio(Project $project, ?User $user = null): bool
    {
        $project->loadMissing('program:id,sector_id');

        return $project->program && $this->programIsInAssignedPortfolio($project->program, $user);
    }

    protected function activityIsInAssignedPortfolio(Activity $activity, ?User $user = null): bool
    {
        $activity->loadMissing('project.program:id,sector_id');

        return $activity->project && $this->projectIsInAssignedPortfolio($activity->project, $user);
    }

    protected function subActivityIsInAssignedPortfolio(SubActivity $subActivity, ?User $user = null): bool
    {
        $subActivity->loadMissing('activity.project.program:id,sector_id');

        return $subActivity->activity && $this->activityIsInAssignedPortfolio($subActivity->activity, $user);
    }

    protected function allocationIsInAssignedPortfolio(?string $level, ?string $id, ?User $user = null): bool
    {
        return match ($level) {
            'project' => $id && in_array((string) $id, $this->assignedProjectIds($user), true),
            'activity' => $id && in_array((string) $id, $this->assignedActivityIds($user), true),
            'sub_activity' => $id && in_array((string) $id, $this->assignedSubActivityIds($user), true),
            default => false,
        };
    }

    protected function fundingIsInAssignedPortfolio(ProgramFunding $funding, ?User $user = null): bool
    {
        return $funding->program_id && in_array((string) $funding->program_id, $this->assignedProgramIds($user), true);
    }

    protected function commitmentIsInAssignedPortfolio(BudgetCommitment $commitment, ?User $user = null): bool
    {
        return $this->allocationIsInAssignedPortfolio($commitment->allocation_level, $commitment->allocation_id, $user);
    }

    protected function purchaseRequestIsInAssignedPortfolio(PurchaseRequest $purchaseRequest, ?User $user = null): bool
    {
        return $this->allocationIsInAssignedPortfolio($purchaseRequest->allocation_level, $purchaseRequest->allocation_id, $user);
    }

    protected function purchaseOrderIsInAssignedPortfolio(ProcurementPurchaseOrder $purchaseOrder, ?User $user = null): bool
    {
        if ($purchaseOrder->sub_activity_id && in_array((string) $purchaseOrder->sub_activity_id, $this->assignedSubActivityIds($user), true)) {
            return true;
        }

        $purchaseOrder->loadMissing('purchaseRequest', 'budgetCommitment');

        return ($purchaseOrder->purchaseRequest && $this->purchaseRequestIsInAssignedPortfolio($purchaseOrder->purchaseRequest, $user))
            || ($purchaseOrder->budgetCommitment && $this->commitmentIsInAssignedPortfolio($purchaseOrder->budgetCommitment, $user));
    }

    protected function invoiceIsInAssignedPortfolio(ProcurementInvoice $invoice, ?User $user = null): bool
    {
        if ($invoice->sub_activity_id && in_array((string) $invoice->sub_activity_id, $this->assignedSubActivityIds($user), true)) {
            return true;
        }

        $invoice->loadMissing('purchaseOrder.purchaseRequest', 'purchaseOrder.budgetCommitment');

        return $invoice->purchaseOrder && $this->purchaseOrderIsInAssignedPortfolio($invoice->purchaseOrder, $user);
    }

    protected function disbursementIsInAssignedPortfolio(ProcurementDisbursement $disbursement, ?User $user = null): bool
    {
        if ($disbursement->sub_activity_id && in_array((string) $disbursement->sub_activity_id, $this->assignedSubActivityIds($user), true)) {
            return true;
        }

        $disbursement->loadMissing('purchaseOrder.purchaseRequest', 'purchaseOrder.budgetCommitment');

        return $disbursement->purchaseOrder && $this->purchaseOrderIsInAssignedPortfolio($disbursement->purchaseOrder, $user);
    }

    protected function resourceCategoryIsInAssignedPortfolioNode(ResourceCategory $category, ?User $user = null): bool
    {
        return $category->governance_node_id
            && in_array((string) $category->governance_node_id, $this->assignedPortfolioNodeIds($user), true);
    }

    protected function resourceIsInAssignedPortfolioNode(Resource $resource, ?User $user = null): bool
    {
        return $resource->governance_node_id
            && in_array((string) $resource->governance_node_id, $this->assignedPortfolioNodeIds($user), true);
    }

    protected function procurementIsInAssignedPortfolio(Procurement $procurement, ?User $user = null): bool
    {
        return $procurement->governance_node_id
            && in_array((string) $procurement->governance_node_id, $this->assignedPortfolioNodeIds($user), true);
    }

    protected function evaluationIsInAssignedPortfolio(Evaluation $evaluation, ?User $user = null): bool
    {
        return $evaluation->portfolio_id
            && in_array((string) $evaluation->portfolio_id, $this->assignedPortfolioIds($user), true);
    }

    protected function evaluationAssignmentIsInAssignedPortfolio(EvaluationAssignment $assignment, ?User $user = null): bool
    {
        $assignment->loadMissing('procurement');

        return $assignment->procurement && $this->procurementIsInAssignedPortfolio($assignment->procurement, $user);
    }

    protected function evaluationSubmissionIsInAssignedPortfolio(EvaluationSubmission $submission, ?User $user = null): bool
    {
        $submission->loadMissing('procurement');

        return $submission->procurement && $this->procurementIsInAssignedPortfolio($submission->procurement, $user);
    }

    protected function approvedWorkPlanIsInAssignedPortfolio(ApprovedWorkPlan $workPlan, ?User $user = null): bool
    {
        if ($workPlan->governance_node_id && in_array((string) $workPlan->governance_node_id, $this->assignedPortfolioNodeIds($user), true)) {
            return true;
        }

        $workPlan->loadMissing('budgetCommitment', 'programFunding');

        return ($workPlan->budgetCommitment && $this->commitmentIsInAssignedPortfolio($workPlan->budgetCommitment, $user))
            || ($workPlan->programFunding && $this->fundingIsInAssignedPortfolio($workPlan->programFunding, $user));
    }

    protected function indicatorIsInAssignedPortfolio(Indicator $indicator, ?User $user = null): bool
    {
        return match ($indicator->indicatorable_type) {
            Sector::class => $indicator->indicatorable_id
                && in_array((string) $indicator->indicatorable_id, $this->assignedPortfolioIds($user), true),
            Program::class => $indicator->indicatorable_id
                && in_array((string) $indicator->indicatorable_id, $this->assignedProgramIds($user), true),
            Project::class => $indicator->indicatorable_id
                && in_array((string) $indicator->indicatorable_id, $this->assignedProjectIds($user), true),
            Activity::class => $indicator->indicatorable_id
                && in_array((string) $indicator->indicatorable_id, $this->assignedActivityIds($user), true),
            SubActivity::class => $indicator->indicatorable_id
                && in_array((string) $indicator->indicatorable_id, $this->assignedSubActivityIds($user), true),
            default => false,
        };
    }
}
