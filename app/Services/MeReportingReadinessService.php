<?php

namespace App\Services;

use App\Models\ConsortiumThinkTank;
use App\Models\Indicator;
use App\Models\MeDataCollection;
use App\Models\MeDataEntryForm;
use App\Models\MeFocalUnitContact;
use App\Models\MeMatrixVersion;
use App\Models\MeReportingPeriod;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

class MeReportingReadinessService
{
    /**
     * Return a live, read-only commissioning assessment for the portfolios the
     * current Secretariat user is permitted to manage.
     */
    public function assess(Collection|array $portfolioIds): array
    {
        $portfolioIds = collect($portfolioIds)
            ->map(fn ($id): string => (string) $id)
            ->filter()
            ->unique()
            ->values();

        $activeThinkTankIds = ConsortiumThinkTank::query()
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id): string => (string) $id);
        $activeThinkTankCount = $activeThinkTankIds->count();

        $reportingAccountOrganizations = User::query()
            ->whereIn('think_tank_member_id', $activeThinkTankIds->all())
            ->whereIn('think_tank_access_level', [
                User::THINK_TANK_ACCESS_ADMIN,
                User::THINK_TANK_ACCESS_ME,
            ])
            ->where(function ($query): void {
                $query->whereNull('is_disabled')->orWhere('is_disabled', false);
            })
            ->where(function ($query): void {
                $query->whereNull('is_blacklisted')->orWhere('is_blacklisted', false);
            })
            ->distinct()
            ->pluck('think_tank_member_id')
            ->filter()
            ->count();

        $mappedFocalOrganizations = MeFocalUnitContact::query()
            ->where('is_active', true)
            ->whereIn('think_tank_member_id', $activeThinkTankIds->all())
            ->distinct()
            ->pluck('think_tank_member_id')
            ->filter()
            ->count();
        $linkedFocalOrganizations = MeFocalUnitContact::query()
            ->where('is_active', true)
            ->whereNotNull('user_id')
            ->whereIn('think_tank_member_id', $activeThinkTankIds->all())
            ->distinct()
            ->pluck('think_tank_member_id')
            ->filter()
            ->count();

        $componentIds = Project::query()
            ->whereHas('program', fn ($query) => $query->whereIn('sector_id', $portfolioIds->all()))
            ->pluck('id');

        $activeMatrixCount = MeMatrixVersion::query()
            ->whereIn('portfolio_id', $portfolioIds->all())
            ->where('status', 'active')
            ->count();

        $reportReadyIndicatorCount = Indicator::query()
            ->whereIn('project_component_id', $componentIds)
            ->whereNotNull('indicator_code')
            ->whereNotNull('baseline_value')
            ->whereNotNull('annual_target')
            ->whereNotNull('life_of_programme_target')
            ->whereNotNull('frequency_of_reporting_id')
            ->whereNotNull('unit_id')
            ->whereNotNull('responsible_user_id')
            ->whereNotNull('means_of_verification_id')
            ->count();

        $publishedFormCount = MeDataEntryForm::query()
            ->whereIn('portfolio_id', $portfolioIds->all())
            ->where('status', MeDataEntryForm::STATUS_PUBLISHED)
            ->whereNotNull('project_component_id')
            ->whereNotNull('indicator_id')
            ->whereHas('indicators')
            ->whereHas('sections.fields')
            ->count();

        $activePeriodCount = MeReportingPeriod::query()
            ->whereIn('portfolio_id', $portfolioIds->all())
            ->where('status', MeReportingPeriod::STATUS_ACTIVE)
            ->count();

        $openCollections = MeDataCollection::query()
            ->where('status', MeDataCollection::STATUS_OPEN)
            ->whereHas('form', fn ($query) => $query
                ->whereIn('portfolio_id', $portfolioIds->all())
                ->where('status', MeDataEntryForm::STATUS_PUBLISHED))
            ->whereHas('reportingPeriod', fn ($query) => $query
                ->whereIn('portfolio_id', $portfolioIds->all())
                ->where('status', MeReportingPeriod::STATUS_ACTIVE))
            ->withCount([
                'assignments as active_assignment_count' => fn ($query) => $query
                    ->whereHas('thinkTank', fn ($memberQuery) => $memberQuery->where('status', 'active')),
            ])
            ->get();
        $fullyAssignedCollectionCount = $activeThinkTankCount > 0
            ? $openCollections->where('active_assignment_count', '>=', $activeThinkTankCount)->count()
            : 0;
        $bestAssignmentCoverage = (int) ($openCollections->max('active_assignment_count') ?? 0);

        $gates = [
            [
                'key' => 'access',
                'label' => 'Think-tank reporting access',
                'complete' => $activeThinkTankCount > 0 && $reportingAccountOrganizations >= $activeThinkTankCount,
                'value' => "{$reportingAccountOrganizations} / {$activeThinkTankCount} organizations",
                'detail' => "{$mappedFocalOrganizations} organizations are mapped in the focal register; {$linkedFocalOrganizations} have a formally linked focal account.",
                'route' => 'budget.me.focal-units.index',
                'query' => [],
                'action' => 'Review focal accounts',
            ],
            [
                'key' => 'matrix',
                'label' => 'Controlled M&E Matrix',
                'complete' => $activeMatrixCount > 0,
                'value' => number_format($activeMatrixCount).' active',
                'detail' => 'Upload the approved matrix and activate its controlled version before commissioning reporting.',
                'route' => 'budget.me.matrices.index',
                'query' => [],
                'action' => 'Manage matrix',
            ],
            [
                'key' => 'indicators',
                'label' => 'Report-ready indicators',
                'complete' => $reportReadyIndicatorCount > 0,
                'value' => number_format($reportReadyIndicatorCount).' ready',
                'detail' => 'A ready indicator has a component, code, baseline, targets, cadence, unit, responsible officer and means of verification.',
                'route' => 'budget.me.indicators.index',
                'query' => [],
                'action' => 'Configure indicators',
            ],
            [
                'key' => 'forms',
                'label' => 'Published reporting forms',
                'complete' => $publishedFormCount > 0,
                'value' => number_format($publishedFormCount).' published',
                'detail' => 'The form must contain sections and questions and be linked to the correct project component and indicators.',
                'route' => 'budget.me.rebuild.data-entry',
                'query' => ['tab' => 'forms'],
                'action' => 'Manage forms',
            ],
            [
                'key' => 'periods',
                'label' => 'Active reporting periods',
                'complete' => $activePeriodCount > 0,
                'value' => number_format($activePeriodCount).' active',
                'detail' => 'Create the approved quarterly, semi-annual or annual reporting window and mark it active.',
                'route' => 'budget.me.rebuild.data-entry',
                'query' => ['tab' => 'periods'],
                'action' => 'Manage periods',
            ],
            [
                'key' => 'collections',
                'label' => 'Open collection assigned to all organizations',
                'complete' => $fullyAssignedCollectionCount > 0,
                'value' => $fullyAssignedCollectionCount > 0
                    ? number_format($fullyAssignedCollectionCount).' fully assigned'
                    : "Best coverage {$bestAssignmentCoverage} / {$activeThinkTankCount}",
                'detail' => 'Join a published form to an active period, open it and assign every active think tank.',
                'route' => 'budget.me.rebuild.data-entry',
                'query' => ['tab' => 'collections'],
                'action' => 'Manage collections',
            ],
        ];

        $completed = collect($gates)->where('complete', true)->count();
        $total = count($gates);

        return [
            'ready' => $total > 0 && $completed === $total,
            'completed' => $completed,
            'total' => $total,
            'percentage' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            'active_think_tanks' => $activeThinkTankCount,
            'gates' => $gates,
        ];
    }
}
