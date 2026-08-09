<?php

namespace App\Services;

use App\Models\Indicator;
use App\Models\IndicatorTarget;
use App\Models\IndicatorUnit;
use App\Models\MeFramework;
use App\Models\MeIndicatorCalculationRule;
use App\Models\MeIndicatorReferenceSheet;
use App\Models\MePerformanceThreshold;
use App\Models\Program;
use App\Models\Project;
use App\Models\ReportingFrequency;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AttpMelFrameworkInstaller
{
    public const FRAMEWORK_CODE = 'ATTP-RF';

    public const FRAMEWORK_VERSION = 'World Bank PAD5316';

    public const WORLD_BANK_PROJECT_ID = 'P179804';

    public const PROJECT_PAGE_URL = 'https://projects.worldbank.org/en/projects-operations/project-detail/P179804';

    public const PAD_URL = 'https://documents.worldbank.org/curated/en/099101623140041645/pdf/BOSIB0c74a63d907e081120e77d1999b450.pdf';

    public const PROJECT_DEVELOPMENT_OBJECTIVE = 'To establish a sustainable platform to strengthen the capacity for effective policy research and policy making on cross-boundary priorities in Africa.';

    /** @return array{framework: MeFramework, indicators: int, targets: int} */
    public function install(?string $userId = null): array
    {
        return DB::transaction(function () use ($userId): array {
            $program = Program::query()->where('program_id', 'PROG00001')->first();
            if (! $program) {
                throw new RuntimeException('ATTP program PROG00001 was not found. Run the ATTP programme seeders first.');
            }

            $components = Project::query()
                ->where('program_id', $program->id)
                ->whereIn('project_id', ['PROG00001-01', 'PROG00001-02', 'PROG00001-03'])
                ->get()
                ->keyBy('project_id');
            foreach (['PROG00001-01', 'PROG00001-02', 'PROG00001-03'] as $componentCode) {
                if (! $components->has($componentCode)) {
                    throw new RuntimeException("ATTP component {$componentCode} was not found. Run the ATTP programme seeders first.");
                }
            }

            $framework = MeFramework::query()->updateOrCreate(
                ['code' => self::FRAMEWORK_CODE, 'version' => self::FRAMEWORK_VERSION],
                [
                    'title' => 'ATTP Results Framework — World Bank Project P179804',
                    'project_development_objective' => self::PROJECT_DEVELOPMENT_OBJECTIVE,
                    'status' => MeFramework::STATUS_ACTIVE,
                    'effective_from' => '2023-11-02',
                    'effective_to' => '2028-08-30',
                    'is_current' => true,
                    'notes' => 'Official results framework aligned to the World Bank Africa Think Tank Platform Project (P179804), Project Appraisal Document PAD5316. Official project targets and Indicator Reference Sheet metadata are preserved. INTC2.6 is intentionally absent because it is not present in the approved PAD results framework. Sources: '.self::PROJECT_PAGE_URL.' and '.self::PAD_URL,
                    'updated_by' => $userId,
                ]
            );
            MeFramework::query()
                ->whereKeyNot($framework->id)
                ->where('code', self::FRAMEWORK_CODE)
                ->update(['is_current' => false]);

            $units = $this->units($userId);
            $frequencies = $this->frequencies($userId);
            $indicatorCount = 0;
            $targetCount = 0;

            foreach ($this->definitions() as $definition) {
                $component = $definition['component_code']
                    ? $components->get($definition['component_code'])
                    : null;
                $indicator = Indicator::query()->updateOrCreate(
                    ['indicator_code' => $definition['code']],
                    [
                        'indicatorable_type' => Program::class,
                        'indicatorable_id' => $program->id,
                        'project_component_id' => $component?->id,
                        'framework_id' => $framework->id,
                        'name' => $definition['name'],
                        'results_level' => $definition['level'],
                        'result_area' => $definition['result_area'],
                        'unit_id' => $units[$definition['unit']]->id,
                        'baseline_type' => 'year',
                        'baseline_value' => is_numeric($definition['baseline']) ? $definition['baseline'] : null,
                        'annual_target' => is_numeric($definition['targets']['Y1'] ?? null) ? $definition['targets']['Y1'] : null,
                        'life_of_programme_target' => is_numeric($definition['targets']['END'] ?? null) ? $definition['targets']['END'] : null,
                        'value_type' => $definition['value_type'],
                        'target_type' => $definition['target_type'],
                        'reporting_source' => $definition['reporting_source'],
                        'is_cumulative' => $definition['is_cumulative'],
                        'aggregation_method' => $definition['aggregation_method'],
                        'organization_rollup_method' => $definition['rollup_method'],
                        'calculation_key' => $definition['calculation_key'],
                        'requires_evidence' => $definition['requires_evidence'],
                        'is_active' => true,
                        'display_order' => $definition['display_order'],
                        'frequency_of_reporting_id' => $frequencies[$definition['operational_frequency_code']]->id,
                        'primary_source' => $definition['data_sources'],
                        'methodology' => $definition['calculation_method'],
                        'data_collection_method' => $definition['data_collection_method'],
                        'definitions' => $definition['definition'],
                    ]
                );
                $indicatorCount++;

                MeIndicatorReferenceSheet::query()->updateOrCreate(
                    ['indicator_id' => $indicator->id, 'version' => 1],
                    [
                        'framework_id' => $framework->id,
                        'definition' => $definition['definition'],
                        'rationale' => $definition['rationale'],
                        'inclusion_criteria' => $definition['inclusion_criteria'],
                        'exclusion_criteria' => $definition['exclusion_criteria'],
                        'unit_of_measurement' => $definition['unit'],
                        'data_collection_method' => $definition['data_collection_method'],
                        'disaggregation' => $definition['disaggregation'],
                        'data_sources' => $definition['data_sources'],
                        'calculation_method' => $definition['calculation_method'],
                        'collection_frequency' => $definition['collection_frequency'],
                        'reporting_frequency' => $definition['reporting_frequency'],
                        'means_of_verification' => $definition['means_of_verification'],
                        'data_generation_responsibility' => $definition['data_generation_responsibility'],
                        'verification_responsibility' => $definition['verification_responsibility'],
                        'additional_guidance' => $definition['guidance'],
                        'approval_status' => 'approved',
                        'effective_from' => '2023-11-02',
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]
                );

                foreach ($definition['targets'] as $period => $target) {
                    $numeric = is_numeric($target) ? (float) $target : null;
                    IndicatorTarget::query()->updateOrCreate(
                        [
                            'indicator_id' => $indicator->id,
                            'framework_id' => $framework->id,
                            'target_context' => 'official_'.strtolower($period),
                            'target_scope' => 'project',
                            'period_label' => $period,
                            'revision' => 1,
                        ],
                        [
                            'period_type' => 'year',
                            'project_year' => match ($period) {
                                'Y1' => 1, 'Y2' => 2, 'Y3' => 3, default => null,
                            },
                            'reporting_year' => match ($period) {
                                'Y1' => $program->start_year,
                                'Y2' => $program->start_year ? $program->start_year + 1 : null,
                                'Y3' => $program->start_year ? $program->start_year + 2 : null,
                                default => $program->end_year,
                            },
                            'target_value' => $numeric,
                            'target_text' => $numeric === null ? (string) $target : null,
                            'baseline_value' => (string) $definition['baseline'],
                            'unit_id' => $units[$definition['unit']]->id,
                            'approval_status' => 'approved',
                            'notes' => 'Official '.$period.' target from World Bank PAD5316 for project '.self::WORLD_BANK_PROJECT_ID.'.',
                            'created_by' => $userId,
                            'updated_by' => $userId,
                        ]
                    );
                    $targetCount++;
                }

                if ($definition['calculation_key']) {
                    MeIndicatorCalculationRule::query()->updateOrCreate(
                        ['indicator_id' => $indicator->id, 'version' => 1],
                        [
                            'framework_id' => $framework->id,
                            'calculation_key' => $definition['calculation_key'],
                            'source_type' => 'approved_indicator_results',
                            'configuration' => $definition['calculation_configuration'],
                            'deduplication_key' => 'deduplication_key',
                            'is_active' => true,
                            'effective_from' => '2023-11-02',
                            'created_by' => $userId,
                            'updated_by' => $userId,
                        ]
                    );
                }
            }

            foreach ([
                ['achieved', 'Achieved / Exceeded', 100, null, '#15935d', 1],
                ['on_track', 'On Track / Moderate', 75, 99.99, '#0e7490', 2],
                ['needs_attention', 'Needs Attention', 50, 74.99, '#d8941d', 3],
                ['off_track', 'Off Track', null, 49.99, '#c43d38', 4],
            ] as [$code, $label, $minimum, $maximum, $color, $order]) {
                MePerformanceThreshold::query()->updateOrCreate(
                    ['framework_id' => $framework->id, 'code' => $code],
                    compact('label', 'color') + [
                        'minimum_percent' => $minimum,
                        'maximum_percent' => $maximum,
                        'display_order' => $order,
                    ]
                );
            }

            return ['framework' => $framework, 'indicators' => $indicatorCount, 'targets' => $targetCount];
        });
    }

    /** @return array<string, IndicatorUnit> */
    private function units(?string $userId): array
    {
        return collect([
            'Number' => ['symbol' => '#', 'description' => 'A whole-number count'],
            'Percentage' => ['symbol' => '%', 'description' => 'A percentage calculated from a numerator and denominator'],
            'Yes/No' => ['symbol' => null, 'description' => 'Boolean achievement status'],
            'Milestone' => ['symbol' => null, 'description' => 'Qualitative milestone or status'],
        ])->mapWithKeys(function (array $attributes, string $name) use ($userId): array {
            $unit = IndicatorUnit::query()->firstOrCreate(['name' => $name], $attributes + [
                'sort_order' => 10,
                'is_active' => true,
                'created_by' => $userId,
            ]);

            return [$name => $unit];
        })->all();
    }

    /** @return array<string, ReportingFrequency> */
    private function frequencies(?string $userId): array
    {
        $definitions = [
            'SEMI_ANNUAL' => ['Semi-Annual', 'month', 6, 182, 'Twice per calendar year', 30],
            'ANNUAL' => ['Annual', 'annual', 1, 365, 'Once per calendar year', 40],
            'ONCE' => ['Once', 'once', null, null, 'A one-time project milestone', 90],
        ];

        return collect($definitions)->mapWithKeys(function (array $row, string $code) use ($userId): array {
            [$name, $unit, $value, $days, $description, $sortOrder] = $row;
            $frequency = ReportingFrequency::query()->firstOrCreate(
                ['portfolio_id' => null, 'code' => $code],
                [
                    'name' => $name,
                    'interval_unit' => $unit,
                    'interval_value' => $value,
                    'frequency_in_days' => $days,
                    'description' => $description,
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                    'created_by' => $userId,
                ]
            );

            return [$code => $frequency];
        })->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function definitions(): array
    {
        $component1 = 'Establish capacity to operate a sustainable policy making platform';
        $component2 = 'Strengthen the quality, relevance and uptake of policy research on priority issues';
        $component3 = 'Support for platform sustainability';
        $base = [
            'rationale' => 'Tracks progress against the approved World Bank ATTP Results Framework.',
            'inclusion_criteria' => 'Count only completed, eligible, non-duplicate records supported by the means of verification in the approved Indicator Reference Sheet.',
            'exclusion_criteria' => 'Exclude draft, rejected, duplicate, ineligible, incomplete, or unsupported records.',
            'data_collection_method' => 'Review validated administrative records and structured progress reports.',
            'data_sources' => 'ATTP Secretariat records and Think Tank progress reports.',
            'means_of_verification' => 'Approved progress reports and indicator-specific supporting records.',
            'guidance' => 'Only Secretariat-approved results contribute to official project performance. Maintain a record-level register and evidence for audit and duplicate control.',
            'disaggregation' => [],
            'target_type' => 'cumulative',
            'is_cumulative' => true,
            'aggregation_method' => 'sum',
            'rollup_method' => 'sum',
            'requires_evidence' => true,
            'calculation_key' => null,
            'calculation_configuration' => [],
            'operational_frequency_code' => 'ANNUAL',
            'collection_frequency' => 'Annual',
            'reporting_frequency' => 'Annual',
            'data_generation_responsibility' => 'ATTP Secretariat and participating Think Tanks',
            'verification_responsibility' => 'AUC ATTP Secretariat M&E Officer / Manager',
        ];

        $pdo1Baseline = 'The AUC has established the platform steering committee (PSC) and associated governance structures.';
        $pdo1Annual = "PSC has assessed the previous year's performance of think tank activities and approved research priorities for the following year.";
        $pdo1End = 'PSC has prepared and approved an exit strategy outlining a sustainability mechanism.';

        $rows = [
            ['PDO 1', 'Platform on continental priority policy issues established and operational.', 'pdo', null, null, 'Milestone', 'milestone', $pdo1Baseline, ['Y1' => $pdo1Annual, 'Y2' => $pdo1Annual, 'Y3' => $pdo1Annual, 'END' => $pdo1End], 'secretariat', 10],
            ['PDO 2', 'Policy-relevant research products on cross-border priority issues generated by think tanks.', 'pdo', null, null, 'Number', 'number', 0, ['Y1' => 10, 'Y2' => 20, 'Y3' => 30, 'END' => 40], 'system_calculated', 20, 'pdo_policy_research_products', ['source_indicator_codes' => ['INTC2.5']]],
            ['PDO 3', 'Think tank policy engagement activities undertaken.', 'pdo', null, null, 'Number', 'number', 0, ['Y1' => 20, 'Y2' => 40, 'Y3' => 60, 'END' => 80], 'system_calculated', 30, 'pdo_policy_engagements', ['source_indicator_codes' => ['INTC2.3']]],
            ['PDO 3-CE', 'Of which include citizen engagement.', 'pdo', null, null, 'Number', 'number', 0, ['Y1' => 20, 'Y2' => 10, 'Y3' => 15, 'END' => 20], 'system_calculated', 40, 'pdo_citizen_engagements', ['source_indicator_codes' => ['INTC2.3'], 'achievement_filter' => ['citizen_engagement' => true]]],
            ['PDO 4', 'Research projects led by female researchers.', 'pdo', null, null, 'Number', 'number', 0, ['Y1' => 5, 'Y2' => 10, 'Y3' => 15, 'END' => 20], 'system_calculated', 50, 'pdo_female_led_research', ['source_indicator_codes' => ['INTC2.5'], 'achievement_filter' => ['lead_researcher_gender' => 'female']]],
            ['INTC1', 'Platform Steering Committee meetings convened.', 'intermediate_results', 'PROG00001-01', $component1, 'Number', 'number', 0, ['Y1' => 1, 'Y2' => 2, 'Y3' => 3, 'END' => 4], 'secretariat', 110],
            ['INTC2.1', 'Africa online knowledge sharing repository established and updated.', 'intermediate_results', 'PROG00001-02', $component2, 'Yes/No', 'boolean', 'No', ['Y1' => 'Yes', 'Y2' => 'Yes', 'Y3' => 'Yes', 'END' => 'Yes'], 'secretariat', 210],
            ['INTC2.2', 'African and global online think tank database established and updated.', 'intermediate_results', 'PROG00001-02', $component2, 'Yes/No', 'boolean', 'No', ['Y1' => 'Yes', 'Y2' => 'Yes', 'Y3' => 'Yes', 'END' => 'Yes'], 'secretariat', 220],
            ['INTC2.3', 'Policy engagement and dissemination events between think tanks, national policy makers and other key stakeholders.', 'intermediate_results', 'PROG00001-02', $component2, 'Number', 'number', 0, ['Y1' => 5, 'Y2' => 15, 'Y3' => 25, 'END' => 30], 'both', 230],
            ['INTC2.4', 'Think tanks with research staff sitting on government task forces, advisory panels, and working groups.', 'intermediate_results', 'PROG00001-02', $component2, 'Number', 'number', 0, ['Y1' => 2, 'Y2' => 4, 'Y3' => 6, 'END' => 8], 'think_tank', 240],
            ['INTC2.5', 'Peer-reviewed policy research outputs on continental priority issues.', 'intermediate_results', 'PROG00001-02', $component2, 'Number', 'number', 0, ['Y1' => 4, 'Y2' => 12, 'Y3' => 18, 'END' => 24], 'think_tank', 250],
            ['INTC2.7', 'Government officials seconded to think tanks receiving fellowships.', 'intermediate_results', 'PROG00001-02', $component2, 'Number', 'number', 0, ['Y1' => 2, 'Y2' => 8, 'Y3' => 12, 'END' => 16], 'think_tank', 270],
            ['INTC2.8', 'Percentage of female professional staff in think tanks receiving mentoring or peer learning support.', 'intermediate_results', 'PROG00001-02', $component2, 'Percentage', 'percentage', 0, ['Y1' => 15, 'Y2' => 50, 'Y3' => 75, 'END' => 100], 'think_tank', 280],
            ['INTC2.9', 'Annual institutional capacity plans implemented in full.', 'intermediate_results', 'PROG00001-02', $component2, 'Number', 'number', 0, ['Y1' => 8, 'Y2' => 8, 'Y3' => 8, 'END' => 8], 'think_tank', 290],
            ['INTC2.10', 'Completed training by stakeholders on evidence-based policy making training.', 'intermediate_results', 'PROG00001-02', $component2, 'Number', 'number', 0, ['Y1' => 10, 'Y2' => 40, 'Y3' => 60, 'END' => 80], 'both', 300],
            ['INTC2.11', "Perception of national and regional level policy makers, and other key policy stakeholders of think tanks' abilities to inform and influence domestic policy.", 'intermediate_results', 'PROG00001-02', $component2, 'Percentage', 'percentage', 0, ['Y1' => 10, 'Y2' => 40, 'Y3' => 60, 'END' => 80], 'secretariat', 310],
            ['INTC3.1', 'Studies to establish and operate endowment fund completed.', 'intermediate_results', 'PROG00001-03', $component3, 'Yes/No', 'boolean', 'No', ['Y1' => 'Yes', 'Y2' => 'Yes', 'Y3' => 'Yes', 'END' => 'Yes'], 'secretariat', 410],
            ['INTC3.2', 'Platform fund mobilization targets met.', 'intermediate_results', 'PROG00001-03', $component3, 'Yes/No', 'boolean', 'No', ['Y1' => 'Yes', 'Y2' => 'Yes', 'Y3' => 'Yes', 'END' => 'Yes'], 'secretariat', 420],
        ];

        $official = $this->officialMetadata();

        return collect($rows)->map(function (array $row) use ($base, $official): array {
            [$code, $name, $level, $component, $resultArea, $unit, $valueType, $baseline, $targets, $source, $order] = $row;
            $calculationKey = $row[11] ?? null;
            $calculationConfiguration = $row[12] ?? [];
            $definition = array_merge($base, [
                'code' => $code,
                'name' => $name,
                'level' => $level,
                'component_code' => $component,
                'result_area' => $resultArea,
                'unit' => $unit,
                'value_type' => $valueType,
                'baseline' => $baseline,
                'targets' => $targets,
                'reporting_source' => $source,
                'display_order' => $order,
                'definition' => $name,
                'calculation_key' => $calculationKey,
                'calculation_configuration' => $calculationConfiguration,
            ], $official[$code] ?? []);

            if (in_array($source, ['think_tank', 'both'], true)) {
                $definition['operational_frequency_code'] = 'SEMI_ANNUAL';
                $definition['collection_frequency'] = 'Continuous; consolidated in semi-annual Think Tank progress reports';
                $definition['data_generation_responsibility'] = 'Participating Think Tanks; AUC ATTP Secretariat collates and verifies';
            }
            if ($code === 'INTC3.1') {
                $definition['operational_frequency_code'] = 'ONCE';
                $definition['collection_frequency'] = 'Once, upon completion of the commissioned studies';
            }
            if ($valueType === 'boolean') {
                $definition['aggregation_method'] = 'latest';
                $definition['rollup_method'] = 'maximum';
            }
            if ($valueType === 'milestone') {
                $definition['aggregation_method'] = 'non_additive';
                $definition['rollup_method'] = 'non_additive';
                $definition['is_cumulative'] = false;
            }
            if ($valueType === 'percentage') {
                $definition['aggregation_method'] = 'percentage';
                $definition['rollup_method'] = 'weighted_average';
            }

            return $definition;
        })->all();
    }

    /** @return array<string, array<string, mixed>> */
    private function officialMetadata(): array
    {
        $semiAnnualReports = 'Think Tank semi-annual progress reports submitted to the AUC ATTP Secretariat.';
        $annualReview = 'Annual review by the AUC ATTP Secretariat and Platform Steering Committee.';

        return [
            'PDO 1' => [
                'definition' => 'The platform is operational when its governance bodies assess Think Tank performance, approve the following year’s research priorities, and adopt an exit strategy with a sustainability mechanism by project end.',
                'data_sources' => 'Platform Steering Committee convention reports and meeting minutes.',
                'data_collection_method' => 'Review Platform Steering Committee reports, decisions, and approved minutes.',
                'means_of_verification' => 'Signed PSC minutes, annual performance assessment, approved research priorities, and approved exit strategy.',
                'calculation_method' => 'Report the latest achieved qualitative milestone against the official annual milestone.',
                'guidance' => $annualReview,
            ],
            'PDO 2' => [
                'definition' => 'Cumulative number of policy-relevant research products generated by participating Think Tanks on priority cross-border issues and assessed as relevant through the annual Think Tank performance review.',
                'data_sources' => 'Platform Steering Committee report and annual policy community survey, supported by Think Tank progress reports.',
                'data_collection_method' => 'Review research products and the PSC annual assessment of Think Tank activities.',
                'means_of_verification' => 'Published research product, peer-review record, annual TTPSC report, and policy community survey.',
                'calculation_method' => 'Count distinct approved qualifying research products reported under INTC2.5; suppress duplicates.',
                'disaggregation' => ['country', 'priority_theme', 'researcher_gender'],
            ],
            'PDO 3' => [
                'definition' => 'Cumulative number of policy engagement activities undertaken by Think Tanks with government, private sector, industry, civil society, or other key policy stakeholders, including workshops and dissemination events.',
                'data_sources' => $semiAnnualReports,
                'data_collection_method' => 'Review the event and engagement register in each Think Tank semi-annual progress report.',
                'means_of_verification' => 'Event report, agenda, attendance list, invitation, minutes, photographs, and dissemination materials.',
                'calculation_method' => 'Count distinct approved qualifying engagement activities reported under INTC2.3; suppress duplicates.',
                'disaggregation' => ['country', 'priority_theme', 'stakeholder_category', 'citizen_participation', 'gender'],
            ],
            'PDO 3-CE' => [
                'definition' => 'Cumulative number of PDO 3 policy engagement activities that include citizens or civil society organizations in the engagement process.',
                'data_sources' => $semiAnnualReports,
                'data_collection_method' => 'Filter approved INTC2.3 engagement records where citizen or civil-society participation is evidenced.',
                'means_of_verification' => 'Attendance list identifying citizens or CSOs, event report, agenda, and engagement evidence.',
                'calculation_method' => 'Count distinct approved INTC2.3 activities with citizen_engagement marked true and supported by evidence.',
                'disaggregation' => ['country', 'priority_theme', 'citizen_participation', 'stakeholder_category'],
            ],
            'PDO 4' => [
                'definition' => 'Cumulative number of qualifying research projects on continental priorities led by female researchers.',
                'data_sources' => $semiAnnualReports,
                'data_collection_method' => 'Review research output registers and verify the gender of the named lead researcher.',
                'means_of_verification' => 'Approved research proposal or output, publication, author record, and peer-review evidence.',
                'calculation_method' => 'Count distinct approved INTC2.5 research records whose lead researcher gender is female; suppress duplicates.',
                'disaggregation' => ['country', 'priority_theme', 'researcher_gender'],
            ],
            'INTC1' => [
                'definition' => 'Cumulative number of Platform Steering Committee meetings convened during project implementation.',
                'data_sources' => 'Platform Steering Committee meeting minutes.',
                'data_collection_method' => 'Review signed minutes and meeting records.',
                'means_of_verification' => 'Meeting notice, agenda, signed attendance list, and approved minutes.',
                'calculation_method' => 'Count distinct PSC meetings with approved minutes.',
            ],
            'INTC2.1' => [
                'definition' => 'An Africa online knowledge-sharing repository has been established and remains updated with current eligible knowledge products.',
                'data_sources' => 'AUC project progress reports and the online knowledge repository.',
                'data_collection_method' => 'Verify repository availability, access, and current database entries.',
                'means_of_verification' => 'Live repository URL, system records, update log, and AUC progress report.',
                'calculation_method' => 'Yes when the repository is accessible and updated during the reporting year; otherwise No.',
            ],
            'INTC2.2' => [
                'definition' => 'An online database covering African and global Think Tanks has been established and remains updated.',
                'data_sources' => 'AUC project progress reports and the online Think Tank database.',
                'data_collection_method' => 'Verify database availability, access, scope, and current entries.',
                'means_of_verification' => 'Live database URL, system records, update log, and AUC progress report.',
                'calculation_method' => 'Yes when the database is accessible and updated during the reporting year; otherwise No.',
            ],
            'INTC2.3' => [
                'definition' => 'Cumulative number of policy engagement and dissemination events between Think Tanks and national policy makers or other key stakeholders, including workshops, dissemination events, and participation in relevant government bodies or committees.',
                'data_sources' => $semiAnnualReports,
                'data_collection_method' => 'Think Tanks maintain a record-level engagement register; the Secretariat verifies and de-duplicates each semi-annual submission.',
                'means_of_verification' => 'Event report, agenda, attendance list, invitation, minutes, photographs, and dissemination materials.',
                'calculation_method' => 'Sum distinct approved engagement events. Stakeholders include government, civil society, and private sector; one event is counted once.',
                'disaggregation' => ['country', 'priority_theme', 'stakeholder_category', 'citizen_participation', 'gender'],
            ],
            'INTC2.4' => [
                'definition' => 'Cumulative number of participating Think Tanks with research staff directly engaged in government task forces, advisory panels, or working groups at country level.',
                'data_sources' => $semiAnnualReports,
                'data_collection_method' => 'Verify named staff appointments and count each qualifying Think Tank once in the applicable cumulative total.',
                'means_of_verification' => 'Appointment or nomination letter, terms of reference, official membership list, minutes, and progress report.',
                'calculation_method' => 'Count distinct Think Tanks with at least one evidenced, eligible staff appointment; do not count individual staff as separate Think Tanks.',
                'disaggregation' => ['country', 'gender', 'government_body_type'],
            ],
            'INTC2.5' => [
                'definition' => 'Cumulative number of peer-reviewed policy research outputs on the six continental priority themes or other priorities agreed in approved annual work plans, including policy briefs, working papers, just-in-time analyses, and literature reviews.',
                'data_sources' => $semiAnnualReports,
                'data_collection_method' => 'Review the research-output register, published output, and peer-review evidence.',
                'means_of_verification' => 'Publication or final output, peer-review record, publication URL, approved work-plan reference, and author details.',
                'calculation_method' => 'Count distinct completed and peer-reviewed outputs once; suppress duplicate titles or identifiers.',
                'disaggregation' => ['country', 'priority_theme', 'researcher_gender', 'research_output_type'],
            ],
            'INTC2.7' => [
                'definition' => 'Cumulative number of government officials seconded to participating Think Tanks and receiving fellowships through the project-supported fellowship programme.',
                'data_sources' => 'Fellowship programme progress reports and Think Tank semi-annual progress reports.',
                'data_collection_method' => 'Verify each fellow’s government organization, host Think Tank, fellowship period, and status.',
                'means_of_verification' => 'Secondment or fellowship letter, signed agreement, attendance or activity record, and completion report.',
                'calculation_method' => 'Count each distinct eligible government official once after the fellowship begins and required documentation is verified.',
                'disaggregation' => ['country', 'gender', 'fellowship_status'],
            ],
            'INTC2.8' => [
                'definition' => 'Percentage of female professional staff employed by participating Think Tanks who receive mentoring or peer-learning support during the applicable reporting period.',
                'data_sources' => $semiAnnualReports,
                'data_collection_method' => 'Collect the number of female professional staff receiving support and the total number of eligible female professional staff for every Think Tank.',
                'means_of_verification' => 'Staff register, mentoring or peer-learning plan, attendance records, mentor logs, and completion records.',
                'calculation_method' => 'Sum approved numerators divided by the sum of approved denominators, multiplied by 100. Numerator: eligible female professional staff receiving support. Denominator: all eligible female professional staff.',
                'disaggregation' => ['country', 'gender', 'support_type'],
            ],
            'INTC2.9' => [
                'definition' => 'Number of participating Think Tanks whose approved annual institutional capacity plan has been implemented in full.',
                'data_sources' => $semiAnnualReports,
                'data_collection_method' => 'Compare completed capacity actions with each approved annual institutional capacity plan.',
                'means_of_verification' => 'Approved capacity plan, action tracker, completion evidence, and annual implementation report.',
                'calculation_method' => 'Count a Think Tank once per annual plan only when all required actions are completed and evidenced.',
                'disaggregation' => ['country', 'capacity_plan_status'],
            ],
            'INTC2.10' => [
                'definition' => 'Cumulative number of stakeholders who complete project-supported training on evidence-based policy making.',
                'data_sources' => $semiAnnualReports,
                'data_collection_method' => 'Verify participant-level attendance and completion records for each eligible training.',
                'means_of_verification' => 'Training agenda, attendance register, participant profile, completion or assessment record, and training report.',
                'calculation_method' => 'Count each distinct stakeholder who meets the training completion requirement; stakeholders include government, CSO, private sector, and media.',
                'disaggregation' => ['country', 'gender', 'stakeholder_category', 'institution_type'],
            ],
            'INTC2.11' => [
                'definition' => "Percentage of surveyed national and regional policy makers and other key policy stakeholders who positively assess Think Tanks' ability to inform and influence domestic policy.",
                'data_sources' => 'Annual policy community survey administered by the AUC to key policy stakeholders.',
                'data_collection_method' => 'Administer a standardized policy community survey, validate the respondent frame, and analyse complete responses.',
                'means_of_verification' => 'Approved survey instrument, respondent frame, anonymized survey dataset, analysis output, and annual survey report.',
                'calculation_method' => 'Number of valid respondents giving the defined positive response divided by all valid respondents, multiplied by 100.',
                'data_generation_responsibility' => 'AUC ATTP Secretariat through the annual policy community survey',
                'disaggregation' => ['country', 'stakeholder_category', 'institution_type'],
            ],
            'INTC3.1' => [
                'definition' => 'The commissioned studies required to establish and operate the platform endowment fund have been completed.',
                'data_sources' => 'Commissioned endowment-fund studies.',
                'data_collection_method' => 'Review final commissioned studies against approved terms of reference and acceptance records.',
                'means_of_verification' => 'Approved terms of reference, final studies, consultant acceptance, and approval record.',
                'calculation_method' => 'Yes when all required studies are completed and formally accepted; otherwise No.',
            ],
            'INTC3.2' => [
                'definition' => 'The annual resource-mobilization target for the platform fund has been met.',
                'data_sources' => 'AUC platform fund annual progress reports.',
                'data_collection_method' => 'Compare verified resources mobilized with the approved annual fund-mobilization target.',
                'means_of_verification' => 'Approved annual target, contribution agreements, bank or accounting records, and annual fund report.',
                'calculation_method' => 'Yes when verified mobilization equals or exceeds the approved annual target; otherwise No.',
            ],
        ];
    }
}
