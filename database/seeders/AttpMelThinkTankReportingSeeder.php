<?php

namespace Database\Seeders;

use App\Models\AuMemberState;
use App\Models\ConsortiumThinkTank;
use App\Models\Indicator;
use App\Models\MeDataCollection;
use App\Models\MeDataCollectionAssignment;
use App\Models\MeDataEntryForm;
use App\Models\MeDataEntryFormField;
use App\Models\MeDataEntryFormSection;
use App\Models\MeDisaggregationDimension;
use App\Models\MeDisaggregationOption;
use App\Models\MeIndicatorDisaggregationRequirement;
use App\Models\MeReportingPeriod;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AttpMelThinkTankReportingSeeder extends Seeder
{
    private const PROJECT_CLOSE_DATE = '2028-08-30';

    /** @var array<string, string> */
    private array $indicatorIds = [];

    /** @var array<string, MeDisaggregationDimension> */
    private array $dimensions = [];

    /** @var array<string, array<int, string>> */
    private array $options = [];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->loadRequiredIndicators();
            $this->seedDimensions();
            $this->seedIndicatorRequirements();
            $forms = $this->seedForms();
            [$periods, $collections, $assignments] = $this->seedPlannedReportingCycles($forms);

            $this->command?->info(sprintf(
                'ATTP M&E reporting seeded: %d forms, %d planned periods, %d collections, %d Think Tank assignments.',
                count($forms),
                $periods,
                $collections,
                $assignments
            ));
        });
    }

    private function loadRequiredIndicators(): void
    {
        $codes = ['INTC2.3', 'INTC2.4', 'INTC2.5', 'INTC2.7', 'INTC2.8', 'INTC2.9', 'INTC2.10'];
        $this->indicatorIds = Indicator::query()
            ->whereIn('indicator_code', $codes)
            ->pluck('id', 'indicator_code')
            ->all();

        $missing = array_values(array_diff($codes, array_keys($this->indicatorIds)));
        if ($missing !== []) {
            throw new RuntimeException('Install the ATTP World Bank results framework before the reporting instruments. Missing: '.implode(', ', $missing));
        }
    }

    private function seedDimensions(): void
    {
        $definitions = [
            'country' => ['Country', 'classification', 'African Union Member State where the result occurred.', 10],
            'priority_theme' => ['ATTP priority theme', 'classification', 'Official continental priority theme addressed by the result.', 20],
            'gender' => ['Gender', 'beneficiary', 'Gender disaggregation for participants, beneficiaries, fellows, or staff.', 30],
            'researcher_gender' => ['Lead researcher gender', 'classification', 'Gender of the research project or output lead.', 40],
            'stakeholder_category' => ['Stakeholder category', 'beneficiary', 'Policy stakeholder participating in or benefiting from the result.', 50],
            'citizen_participation' => ['Citizen participation', 'beneficiary', 'Whether citizens or civil-society organizations participated.', 60],
            'institution_type' => ['Institution type', 'classification', 'Institution represented by the participant or beneficiary.', 70],
            'fellowship_status' => ['Fellowship status', 'classification', 'Current status of a government-official fellowship.', 80],
            'capacity_plan_status' => ['Capacity-plan status', 'classification', 'Implementation status of an annual institutional capacity plan.', 90],
        ];

        foreach ($definitions as $code => [$name, $group, $description, $sortOrder]) {
            $this->dimensions[$code] = MeDisaggregationDimension::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'dimension_group' => $group,
                    'description' => $description,
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                ]
            );
        }

        $countryRows = AuMemberState::query()->active()->ordered()->get(['name', 'code', 'code_alpha2']);
        if ($countryRows->isEmpty()) {
            throw new RuntimeException('No active African Union Member States were found. Run AuMemberStateSeeder first.');
        }

        $dimensionOptions = [
            'country' => $countryRows->mapWithKeys(fn (AuMemberState $state): array => [
                strtolower((string) ($state->code_alpha2 ?: $state->code ?: Str::slug($state->name, '_'))) => $state->name,
            ])->all(),
            'priority_theme' => [
                'economic_transformation_governance' => 'Economic Transformation and Governance',
                'climate_change' => 'Climate Change',
                'regional_trade' => 'Regional Trade',
                'food_security' => 'Food Security',
                'human_capital' => 'Human Capital',
                'digitalization' => 'Digitalization',
            ],
            'gender' => [
                'female' => 'Female',
                'male' => 'Male',
                'non_binary' => 'Non-binary / another identity',
                'not_disclosed' => 'Prefer not to disclose',
            ],
            'researcher_gender' => [
                'female' => 'Female',
                'male' => 'Male',
                'non_binary' => 'Non-binary / another identity',
                'not_disclosed' => 'Prefer not to disclose',
            ],
            'stakeholder_category' => [
                'government' => 'Government',
                'parliament' => 'Parliament',
                'regional_organization' => 'Regional organization',
                'think_tank' => 'Think Tank',
                'academia' => 'Academia',
                'civil_society' => 'Civil society organization',
                'private_sector' => 'Private sector / industry',
                'development_partner' => 'Development partner',
                'media' => 'Media',
                'citizen_community' => 'Citizen / community group',
                'other' => 'Other',
            ],
            'citizen_participation' => ['yes' => 'Yes', 'no' => 'No'],
            'institution_type' => [
                'national_government' => 'National government',
                'subnational_government' => 'Subnational government',
                'regional_body' => 'Regional body / REC',
                'parliament' => 'Parliament',
                'civil_society' => 'Civil society organization',
                'private_sector' => 'Private sector / industry',
                'media' => 'Media',
                'academia' => 'University / academia',
                'think_tank' => 'Think Tank',
                'other' => 'Other',
            ],
            'fellowship_status' => [
                'planned' => 'Planned',
                'active' => 'Active',
                'completed' => 'Completed',
                'withdrawn' => 'Withdrawn / discontinued',
            ],
            'capacity_plan_status' => [
                'implemented_in_full' => 'Implemented in full',
                'partially_implemented' => 'Partially implemented',
                'not_started' => 'Not started',
                'not_applicable' => 'Not applicable',
            ],
        ];

        foreach ($dimensionOptions as $dimensionCode => $options) {
            $this->options[$dimensionCode] = [];
            $sortOrder = 10;
            foreach ($options as $code => $name) {
                MeDisaggregationOption::query()->updateOrCreate(
                    ['dimension_id' => $this->dimensions[$dimensionCode]->id, 'code' => $code],
                    [
                        'parent_id' => null,
                        'name' => $name,
                        'metadata' => ['world_bank_project_id' => 'P179804'],
                        'sort_order' => $sortOrder,
                        'is_active' => true,
                    ]
                );
                $this->options[$dimensionCode][] = $name;
                $sortOrder += 10;
            }
        }
    }

    private function seedIndicatorRequirements(): void
    {
        $requirements = [
            'INTC2.3' => ['country' => true, 'priority_theme' => true, 'stakeholder_category' => true, 'citizen_participation' => false, 'gender' => false],
            'INTC2.4' => ['country' => true, 'gender' => false],
            'INTC2.5' => ['country' => true, 'priority_theme' => true, 'researcher_gender' => true],
            'INTC2.7' => ['country' => true, 'gender' => true, 'fellowship_status' => true],
            'INTC2.8' => ['country' => true, 'gender' => true],
            'INTC2.9' => ['country' => true, 'capacity_plan_status' => true],
            'INTC2.10' => ['country' => true, 'gender' => false, 'stakeholder_category' => true, 'institution_type' => true],
        ];

        foreach ($requirements as $indicatorCode => $dimensions) {
            $sortOrder = 10;
            foreach ($dimensions as $dimensionCode => $required) {
                MeIndicatorDisaggregationRequirement::query()->updateOrCreate(
                    [
                        'indicator_id' => $this->indicatorIds[$indicatorCode],
                        'dimension_id' => $this->dimensions[$dimensionCode]->id,
                    ],
                    [
                        'is_required' => $required,
                        'collect_numeric_value' => in_array($dimensionCode, ['gender', 'stakeholder_category'], true),
                        'sort_order' => $sortOrder,
                    ]
                );
                $sortOrder += 10;
            }
        }
    }

    /** @return array<int, MeDataEntryForm> */
    private function seedForms(): array
    {
        $component = Project::query()->where('project_id', 'PROG00001-02')->first();
        if (! $component) {
            throw new RuntimeException('ATTP component PROG00001-02 was not found.');
        }

        $forms = [];
        foreach ($this->formBlueprints() as $blueprint) {
            $indicatorId = $this->indicatorIds[$blueprint['indicator_code']];
            $form = MeDataEntryForm::query()->where('code', $blueprint['code'])->first();
            if (! $form) {
                $form = MeDataEntryForm::query()->create([
                    'project_component_id' => $component->id,
                    'indicator_id' => $indicatorId,
                    'title' => $blueprint['title'],
                    'description' => $blueprint['description'],
                    'instructions' => $blueprint['instructions'],
                    'version' => 1,
                    'status' => MeDataEntryForm::STATUS_PUBLISHED,
                ]);
                DB::table('me_data_entry_forms')->where('id', $form->id)->update(['code' => $blueprint['code']]);
                $form->code = $blueprint['code'];
            } else {
                $form->update([
                    'project_component_id' => $component->id,
                    'indicator_id' => $indicatorId,
                    'title' => $blueprint['title'],
                    'description' => $blueprint['description'],
                    'instructions' => $blueprint['instructions'],
                ]);
            }

            $this->linkFormIndicator($form->id, $indicatorId);
            $sections = [];
            foreach ($blueprint['sections'] as $index => $section) {
                $sections[$section['key']] = MeDataEntryFormSection::query()->updateOrCreate(
                    ['form_id' => $form->id, 'section_key' => $section['key']],
                    [
                        'name' => $section['name'],
                        'description' => $section['description'],
                        'background_color' => $section['color'],
                        'sort_order' => ($index + 1) * 10,
                    ]
                );
            }

            foreach ($blueprint['fields'] as $index => $field) {
                MeDataEntryFormField::query()->updateOrCreate(
                    ['form_id' => $form->id, 'field_key' => $field['key']],
                    [
                        'section_id' => $sections[$field['section']]->id,
                        'indicator_id' => ($field['result'] ?? false) ? $indicatorId : null,
                        'section' => $sections[$field['section']]->name,
                        'label' => $field['label'],
                        'help_text' => $field['help'] ?? null,
                        'field_type' => $field['type'],
                        'options' => $field['options'] ?? null,
                        'validation' => $field['validation'] ?? null,
                        'unit_label' => $field['unit'] ?? null,
                        'is_required' => $field['required'] ?? false,
                        'sort_order' => ($index + 1) * 10,
                    ]
                );
            }

            $forms[] = $form->fresh();
        }

        return $forms;
    }

    private function linkFormIndicator(string $formId, string $indicatorId): void
    {
        $exists = DB::table('me_data_entry_form_indicators')
            ->where('form_id', $formId)
            ->where('indicator_id', $indicatorId)
            ->exists();
        if ($exists) {
            DB::table('me_data_entry_form_indicators')
                ->where('form_id', $formId)
                ->where('indicator_id', $indicatorId)
                ->update(['is_primary' => true, 'sort_order' => 10, 'updated_at' => now()]);

            return;
        }

        DB::table('me_data_entry_form_indicators')->insert([
            'id' => (string) Str::uuid(),
            'form_id' => $formId,
            'indicator_id' => $indicatorId,
            'is_primary' => true,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<int, MeDataEntryForm> $forms
     * @return array{int, int, int}
     */
    private function seedPlannedReportingCycles(array $forms): array
    {
        $today = CarbonImmutable::today();
        $projectClose = CarbonImmutable::parse(self::PROJECT_CLOSE_DATE);
        if ($today->greaterThan($projectClose)) {
            return [0, 0, 0];
        }

        $cursor = $today->month <= 6
            ? CarbonImmutable::create($today->year, 1, 1)
            : CarbonImmutable::create($today->year, 7, 1);
        $members = ConsortiumThinkTank::query()->where('status', 'active')->orderBy('name')->get();
        $periodCount = 0;
        $collectionCount = 0;
        $assignmentCount = 0;

        while (true) {
            $half = $cursor->month === 1 ? 'H1' : 'H2';
            $periodEnd = $cursor->month === 1
                ? CarbonImmutable::create($cursor->year, 6, 30)->endOfDay()
                : $cursor->endOfYear();
            if ($periodEnd->greaterThan($projectClose)) {
                break;
            }

            $opensAt = $periodEnd->addDay()->startOfDay();
            $deadline = $opensAt->addDays(30)->endOfDay();
            $reviewDeadline = $deadline->addDays(30)->endOfDay();
            $period = MeReportingPeriod::query()->firstOrNew([
                'code' => "ATTP-MEL-{$cursor->year}-{$half}",
            ]);
            if (! $period->exists || (
                $period->status === MeReportingPeriod::STATUS_DRAFT
                && $period->lifecycle_status === MeReportingPeriod::LIFECYCLE_PLANNED
            )) {
                $period->fill([
                    'label' => "ATTP {$cursor->year} {$half}",
                    'period_type' => MeReportingPeriod::TYPE_SEMI_ANNUAL,
                    'period_start' => $cursor->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'status' => MeReportingPeriod::STATUS_DRAFT,
                    'reporting_year' => $cursor->year,
                    'submission_opens_at' => $opensAt,
                    'submission_deadline' => $deadline,
                    'review_deadline' => $reviewDeadline,
                    'lifecycle_status' => MeReportingPeriod::LIFECYCLE_PLANNED,
                    'instructions' => 'Planned ATTP semi-annual M&E cycle. The Secretariat must review dates and open the period before Think Tanks can submit.',
                ])->save();
            }
            $periodCount++;

            foreach ($forms as $form) {
                $collection = MeDataCollection::query()->firstOrNew([
                    'form_id' => $form->id,
                    'reporting_period_id' => $period->id,
                ]);
                if (! $collection->exists || $collection->status === MeDataCollection::STATUS_DRAFT) {
                    $collection->fill([
                        'instructions' => 'Report only results achieved within this period. Provide record-level details and attach complete means of verification. Draft or duplicate results must not be reported.',
                        'opens_at' => $opensAt,
                        'due_at' => $deadline,
                        'closes_at' => $reviewDeadline,
                        'status' => MeDataCollection::STATUS_DRAFT,
                    ])->save();
                }
                $collectionCount++;

                foreach ($members as $member) {
                    MeDataCollectionAssignment::query()->firstOrCreate(
                        ['collection_id' => $collection->id, 'think_tank_member_id' => $member->id],
                        ['assigned_at' => now()]
                    );
                    $assignmentCount++;
                }
            }

            $cursor = $cursor->addMonths(6);
        }

        return [$periodCount, $collectionCount, $assignmentCount];
    }

    /** @return array<int, array<string, mixed>> */
    private function formBlueprints(): array
    {
        $sections = [
            ['key' => 'result', 'name' => 'A. Result summary and register', 'description' => 'Enter the reportable result and enough record-level detail to prevent double counting.', 'color' => '#EFF6FF'],
            ['key' => 'classification', 'name' => 'B. Classification and disaggregation', 'description' => 'Classify the result using the approved ATTP categories.', 'color' => '#F0FDFA'],
            ['key' => 'evidence', 'name' => 'C. Results narrative and means of verification', 'description' => 'Explain the result and attach auditable supporting evidence.', 'color' => '#FFFBEB'],
        ];
        $countValidation = ['min' => 0, 'step' => 1];
        $evidence = fn (string $type): array => [
            'allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'jpg', 'jpeg', 'png', 'zip'],
            'max_file_size_mb' => 20,
            'multiple' => true,
            'max_files' => 10,
            'evidence_type' => $type,
        ];
        $field = fn (string $section, string $key, string $label, string $type, bool $required = false, array $extra = []): array => array_merge([
            'section' => $section,
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'required' => $required,
        ], $extra);
        $themes = $this->options['priority_theme'];
        $countries = $this->options['country'];
        $genders = $this->options['gender'];
        $stakeholders = $this->options['stakeholder_category'];

        return [
            [
                'code' => 'ATTP-TT-INTC2-3',
                'indicator_code' => 'INTC2.3',
                'title' => 'Policy engagement and dissemination events',
                'description' => 'Semi-annual register for eligible policy engagements and dissemination events with national policy makers and other key stakeholders.',
                'instructions' => 'Count each distinct event once. Include workshops, dissemination events, and evidenced participation in relevant government bodies. Do not include internal meetings or unsupported activities.',
                'sections' => $sections,
                'fields' => [
                    $field('result', 'engagement_event_count', 'Number of eligible engagement or dissemination events', 'integer', true, ['result' => true, 'unit' => 'events', 'validation' => $countValidation]),
                    $field('result', 'event_register', 'Event register (title, date, organizer, location and unique reference for each event)', 'textarea', true, ['help' => 'One line per event. The reported count must agree with this register.', 'validation' => ['max_length' => 15000]]),
                    $field('result', 'event_start_date', 'Earliest event date in this submission', 'date', true),
                    $field('result', 'event_end_date', 'Latest event date in this submission', 'date', true),
                    $field('classification', 'event_countries', 'Countries covered by the events', 'multiselect', true, ['options' => $countries]),
                    $field('classification', 'priority_themes', 'ATTP priority themes addressed', 'multiselect', true, ['options' => $themes]),
                    $field('classification', 'stakeholder_categories', 'Stakeholder categories engaged', 'multiselect', true, ['options' => $stakeholders]),
                    $field('classification', 'citizen_participation', 'Did any event include citizens or civil-society organizations?', 'yes_no', true, ['options' => ['yes' => 'Yes', 'no' => 'No']]),
                    $field('classification', 'total_participants', 'Total participants across reported events', 'integer', true, ['unit' => 'people', 'validation' => $countValidation]),
                    $field('classification', 'female_participants', 'Female participants', 'integer', false, ['unit' => 'people', 'validation' => $countValidation]),
                    $field('classification', 'male_participants', 'Male participants', 'integer', false, ['unit' => 'people', 'validation' => $countValidation]),
                    $field('evidence', 'policy_outcome_narrative', 'Policy dialogue, commitments or follow-up resulting from the engagements', 'textarea', true, ['validation' => ['max_length' => 10000]]),
                    $field('evidence', 'event_evidence_files', 'Event reports, agendas, attendance lists, invitations, minutes or photographs', 'file', true, ['validation' => $evidence('attendance_list')]),
                    $field('evidence', 'event_evidence_url', 'Public event or dissemination URL', 'url', false),
                ],
            ],
            [
                'code' => 'ATTP-TT-INTC2-4',
                'indicator_code' => 'INTC2.4',
                'title' => 'Think Tank participation in government bodies',
                'description' => 'Evidence register for research staff serving on government task forces, advisory panels, or working groups.',
                'instructions' => 'The official indicator counts distinct Think Tanks, not staff positions. Report the Think Tank result as 1 when at least one eligible appointment is active and evidenced; otherwise report 0.',
                'sections' => $sections,
                'fields' => [
                    $field('result', 'qualifying_think_tank_count', 'Qualifying Think Tank count for this submission', 'integer', true, ['result' => true, 'unit' => 'Think Tank', 'validation' => ['min' => 0, 'max' => 1, 'step' => 1]]),
                    $field('result', 'appointed_staff_register', 'Appointed staff register (name, title, government body, role and appointment reference)', 'textarea', true, ['validation' => ['max_length' => 15000]]),
                    $field('result', 'appointment_start_date', 'Earliest appointment start date', 'date', true),
                    $field('result', 'appointment_end_date', 'Latest appointment end date, if applicable', 'date', false),
                    $field('classification', 'government_body_countries', 'Countries of the government bodies', 'multiselect', true, ['options' => $countries]),
                    $field('classification', 'government_body_types', 'Types of government body', 'multiselect', true, ['options' => ['Task force', 'Advisory panel', 'Technical working group', 'Committee', 'Other formal government body']]),
                    $field('classification', 'appointed_staff_gender', 'Gender of appointed research staff', 'multiselect', false, ['options' => $genders]),
                    $field('classification', 'government_institutions', 'Government institutions represented', 'textarea', true),
                    $field('evidence', 'appointment_contribution', 'Research staff contribution and policy relevance', 'textarea', true, ['validation' => ['max_length' => 10000]]),
                    $field('evidence', 'appointment_evidence_files', 'Appointment letters, terms of reference, membership lists or minutes', 'file', true, ['validation' => $evidence('official_letter')]),
                    $field('evidence', 'appointment_evidence_url', 'Official government-body URL', 'url', false),
                ],
            ],
            [
                'code' => 'ATTP-TT-INTC2-5',
                'indicator_code' => 'INTC2.5',
                'title' => 'Peer-reviewed policy research outputs',
                'description' => 'Semi-annual register of completed peer-reviewed outputs on continental priority issues.',
                'instructions' => 'Count an output once when it is complete, addresses an approved priority, and has documentary peer-review evidence. Use a stable title, DOI, ISBN, URL, or internal reference to prevent duplicates.',
                'sections' => $sections,
                'fields' => [
                    $field('result', 'research_output_count', 'Number of eligible peer-reviewed research outputs', 'integer', true, ['result' => true, 'unit' => 'outputs', 'validation' => $countValidation]),
                    $field('result', 'research_output_register', 'Output register (title, output type, author(s), publication date and unique identifier)', 'textarea', true, ['validation' => ['max_length' => 18000]]),
                    $field('result', 'latest_publication_date', 'Latest publication or completion date', 'date', true),
                    $field('classification', 'research_output_types', 'Research output types', 'multiselect', true, ['options' => ['Policy brief', 'Working paper', 'Just-in-time analysis', 'Literature review', 'Journal article', 'Research report', 'Other approved output']]),
                    $field('classification', 'priority_themes', 'ATTP priority themes addressed', 'multiselect', true, ['options' => $themes]),
                    $field('classification', 'research_countries', 'Countries covered by the research', 'multiselect', true, ['options' => $countries]),
                    $field('classification', 'cross_border_research', 'Does the research address a cross-border issue?', 'yes_no', true, ['options' => ['yes' => 'Yes', 'no' => 'No']]),
                    $field('classification', 'lead_researcher_names', 'Lead researcher name(s)', 'textarea', true),
                    $field('classification', 'lead_researcher_gender', 'Lead researcher gender', 'select', true, ['options' => $this->options['researcher_gender']]),
                    $field('classification', 'coauthor_names', 'Co-author name(s)', 'textarea', false),
                    $field('evidence', 'policy_recommendations', 'Principal policy findings and recommendations', 'textarea', true, ['validation' => ['max_length' => 12000]]),
                    $field('evidence', 'publication_url', 'Publication or repository URL', 'url', false),
                    $field('evidence', 'research_evidence_files', 'Final outputs, peer-review records and approval evidence', 'file', true, ['validation' => $evidence('publication')]),
                ],
            ],
            [
                'code' => 'ATTP-TT-INTC2-7',
                'indicator_code' => 'INTC2.7',
                'title' => 'Government-official fellowship register',
                'description' => 'Register of government officials seconded to Think Tanks and receiving project-supported fellowships.',
                'instructions' => 'Count each official once after the fellowship begins and the secondment or fellowship agreement is verified. Do not recount the same fellow in later periods.',
                'sections' => $sections,
                'fields' => [
                    $field('result', 'government_fellow_count', 'Number of eligible government officials receiving fellowships', 'integer', true, ['result' => true, 'unit' => 'fellows', 'validation' => $countValidation]),
                    $field('result', 'fellow_register', 'Fellow register (name, government organization, fellowship title and unique reference)', 'textarea', true, ['validation' => ['max_length' => 15000]]),
                    $field('result', 'fellowship_start_date', 'Earliest fellowship start date', 'date', true),
                    $field('result', 'fellowship_end_date', 'Latest fellowship end date', 'date', false),
                    $field('classification', 'fellow_countries', 'Countries of the government officials', 'multiselect', true, ['options' => $countries]),
                    $field('classification', 'fellow_gender', 'Gender of fellows', 'multiselect', true, ['options' => $genders]),
                    $field('classification', 'fellowship_status', 'Fellowship status', 'multiselect', true, ['options' => $this->options['fellowship_status']]),
                    $field('classification', 'government_organizations', 'Government organizations represented', 'textarea', true),
                    $field('evidence', 'fellowship_achievements', 'Fellowship work, outputs and skills transferred', 'textarea', true, ['validation' => ['max_length' => 10000]]),
                    $field('evidence', 'fellowship_evidence_files', 'Secondment letters, agreements, activity logs or completion reports', 'file', true, ['validation' => $evidence('official_letter')]),
                ],
            ],
            [
                'code' => 'ATTP-TT-INTC2-8',
                'indicator_code' => 'INTC2.8',
                'title' => 'Female professional staff mentoring and peer learning',
                'description' => 'Weighted-percentage return for eligible female professional staff receiving mentoring or peer-learning support.',
                'instructions' => 'The denominator is all eligible female professional staff employed during the reporting period. The numerator is the subset receiving qualifying mentoring or peer-learning support. The reported percentage must equal numerator ÷ denominator × 100.',
                'sections' => $sections,
                'fields' => [
                    $field('result', 'female_staff_support_percentage', 'Percentage of eligible female professional staff receiving support', 'percentage', true, ['result' => true, 'unit' => '%', 'validation' => ['min' => 0, 'max' => 100, 'step' => 0.01, 'rollup_numerator_field_key' => 'female_staff_supported', 'rollup_denominator_field_key' => 'eligible_female_professional_staff']]),
                    $field('result', 'female_staff_supported', 'Numerator: eligible female professional staff who received support', 'integer', true, ['unit' => 'staff', 'validation' => $countValidation]),
                    $field('result', 'eligible_female_professional_staff', 'Denominator: all eligible female professional staff', 'integer', true, ['unit' => 'staff', 'validation' => ['min' => 1, 'step' => 1]]),
                    $field('result', 'supported_staff_register', 'Supported staff register (name or coded ID, support type, dates and completion status)', 'textarea', true, ['validation' => ['max_length' => 15000]]),
                    $field('classification', 'staff_countries', 'Countries of participating staff', 'multiselect', true, ['options' => $countries]),
                    $field('classification', 'staff_gender', 'Gender classification used for this indicator', 'select', true, ['options' => ['Female']]),
                    $field('classification', 'support_types', 'Types of support provided', 'multiselect', true, ['options' => ['Mentoring', 'Peer learning', 'Both mentoring and peer learning']]),
                    $field('classification', 'support_start_date', 'Support start date', 'date', true),
                    $field('classification', 'support_end_date', 'Support end date', 'date', false),
                    $field('evidence', 'support_outcomes', 'Skills, professional development or institutional outcomes', 'textarea', true, ['validation' => ['max_length' => 10000]]),
                    $field('evidence', 'mentoring_evidence_files', 'Staff register, mentoring plans, attendance records, logs or completion records', 'file', true, ['validation' => $evidence('attendance_list')]),
                ],
            ],
            [
                'code' => 'ATTP-TT-INTC2-9',
                'indicator_code' => 'INTC2.9',
                'title' => 'Annual institutional capacity-plan implementation',
                'description' => 'Annual institutional capacity-plan implementation and completion return.',
                'instructions' => 'The official result is 1 only when every required action in the approved annual capacity plan is completed and evidenced; otherwise enter 0 and report the remaining actions.',
                'sections' => $sections,
                'fields' => [
                    $field('result', 'fully_implemented_plan_count', 'Annual capacity plans implemented in full', 'integer', true, ['result' => true, 'unit' => 'plans', 'validation' => ['min' => 0, 'max' => 1, 'step' => 1]]),
                    $field('result', 'capacity_plan_year', 'Capacity-plan year', 'year', true, ['validation' => ['min' => 2024, 'max' => 2028]]),
                    $field('result', 'planned_action_count', 'Total actions in the approved plan', 'integer', true, ['unit' => 'actions', 'validation' => ['min' => 1, 'step' => 1]]),
                    $field('result', 'completed_action_count', 'Actions completed and evidenced', 'integer', true, ['unit' => 'actions', 'validation' => $countValidation]),
                    $field('result', 'capacity_action_register', 'Capacity-action register (action, responsible person, due date and completion status)', 'textarea', true, ['validation' => ['max_length' => 18000]]),
                    $field('classification', 'capacity_plan_country', 'Think Tank country', 'select', true, ['options' => $countries]),
                    $field('classification', 'capacity_plan_status', 'Capacity-plan implementation status', 'select', true, ['options' => $this->options['capacity_plan_status']]),
                    $field('classification', 'capacity_areas', 'Institutional capacity areas addressed', 'multiselect', true, ['options' => ['Governance', 'Research quality', 'Financial management', 'Procurement', 'Human resources', 'M&E and learning', 'Communications and uptake', 'Digital systems', 'Resource mobilization', 'Other']]),
                    $field('evidence', 'implementation_variance', 'Incomplete actions, variance explanation and corrective action', 'textarea', false, ['validation' => ['max_length' => 10000]]),
                    $field('evidence', 'capacity_plan_evidence_files', 'Approved plan, action tracker and completion evidence', 'file', true, ['validation' => $evidence('workplan')]),
                ],
            ],
            [
                'code' => 'ATTP-TT-INTC2-10',
                'indicator_code' => 'INTC2.10',
                'title' => 'Evidence-based policymaking training completion',
                'description' => 'Participant-level completion return for project-supported evidence-based policymaking training.',
                'instructions' => 'Count distinct stakeholders who satisfy the documented completion rule. Retain participant-level identifiers to prevent a person being counted twice for the same training.',
                'sections' => $sections,
                'fields' => [
                    $field('result', 'training_completer_count', 'Number of stakeholders who completed eligible training', 'integer', true, ['result' => true, 'unit' => 'people', 'validation' => $countValidation]),
                    $field('result', 'training_register', 'Training register (training title, dates, organizer and unique reference)', 'textarea', true, ['validation' => ['max_length' => 15000]]),
                    $field('result', 'participant_completion_register', 'Participant completion register (name or coded ID, institution, category and completion result)', 'textarea', true, ['validation' => ['max_length' => 18000]]),
                    $field('result', 'training_start_date', 'Earliest training start date', 'date', true),
                    $field('result', 'training_end_date', 'Latest training end date', 'date', true),
                    $field('classification', 'training_countries', 'Countries represented', 'multiselect', true, ['options' => $countries]),
                    $field('classification', 'stakeholder_categories', 'Stakeholder categories trained', 'multiselect', true, ['options' => $stakeholders]),
                    $field('classification', 'participant_institution_types', 'Participant institution types', 'multiselect', true, ['options' => $this->options['institution_type']]),
                    $field('classification', 'participant_gender', 'Participant gender categories', 'multiselect', false, ['options' => $genders]),
                    $field('classification', 'female_completers', 'Female completers', 'integer', false, ['unit' => 'people', 'validation' => $countValidation]),
                    $field('classification', 'male_completers', 'Male completers', 'integer', false, ['unit' => 'people', 'validation' => $countValidation]),
                    $field('evidence', 'training_learning_outcomes', 'Learning assessment, competencies gained and intended application', 'textarea', true, ['validation' => ['max_length' => 10000]]),
                    $field('evidence', 'training_evidence_files', 'Agenda, attendance register, assessments, certificates or training report', 'file', true, ['validation' => $evidence('certificate')]),
                ],
            ],
        ];
    }
}
