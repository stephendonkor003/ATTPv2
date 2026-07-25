<?php

use App\Models\ConsortiumThinkTank;
use App\Models\Indicator;
use App\Models\IndicatorResult;
use App\Models\MeDataCollection;
use App\Models\MeDataEntryForm;
use App\Models\MeDataEntryFormField;
use App\Models\MeDataEntryFormSection;
use App\Models\MeDataSubmission;
use App\Models\MeDataSubmissionAnswer;
use App\Models\MeReportingPeriod;
use App\Models\Project;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use App\Services\ThinkTankMeAssignmentService;
use Database\Seeders\ConsortiumOperationsPermissionsSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithExceptionHandling;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Standalone smoke scripts do not enter PHPUnit's normal CLI bootstrap. Initialize
// its configuration so Laravel's TestResponse assertions can report useful failures.
(new PHPUnit\TextUI\Configuration\Builder)->build(['phpunit']);

class MeDataEntryWorkflowSmoke
{
    use InteractsWithAuthentication;
    use InteractsWithExceptionHandling;
    use InteractsWithSession;
    use MakesHttpRequests;

    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function run(): void
    {
        $this->assertSchemaReady();
        $this->seedAccessControl();
        Storage::fake('local');

        DB::beginTransaction();

        try {
            $context = $this->prepareContext();
            $this->assertExpandedTypeCataloguePersists($context);
            $this->assertTemplateIndicatorIsRequired($context);
            $this->assertLegacySectionPayloadCompatibility($context);
            $this->assertEmptySectionsAreRejected($context);
            $this->assertMissingSectionGuidanceIsRejected($context);
            $workflow = $this->createWorkflowThroughAdmin($context);

            $this->assertAdminWorkspace($context['admin'], $workflow);
            $this->assertDashboardPerformanceUpdates($context, $workflow);
            $this->assertAssignedThinkTankWorkflow($context, $workflow);
            $this->assertParticipantSubmissionsRegister($context, $workflow);
            $this->assertSubmittedSectionStructureIsLocked($context, $workflow);
            $this->assertEveryAnswerTypeWorks($context);
            $this->assertThinkTankIsolation($context, $workflow);
            $this->assertDraftAndClosedCollectionRules($context, $workflow);

            echo "ME_DATA_ENTRY_WORKFLOW_OK\n";
        } finally {
            DB::rollBack();
            Storage::disk('local')->deleteDirectory('me-data');
            $this->app['auth']->forgetGuards();
        }
    }

    private function seedAccessControl(): void
    {
        foreach ([
            PermissionSeeder::class,
            ConsortiumOperationsPermissionsSeeder::class,
            RolePermissionSeeder::class,
        ] as $seeder) {
            Artisan::call('db:seed', [
                '--class' => $seeder,
                '--force' => true,
            ]);
        }
    }

    private function prepareContext(): array
    {
        $adminRole = Role::query()->where('name', 'System Admin')->first();
        $thinkTankRole = Role::query()->where('name', 'Think Tank User')->first();
        $this->assertTrue((bool) $adminRole, 'System Admin role is missing after permission seeding.');
        $this->assertTrue((bool) $thinkTankRole, 'Think Tank User role is missing after permission seeding.');

        $admin = User::query()
            ->with('role')
            ->where('role_id', $adminRole->id)
            ->orderBy('created_at')
            ->first();
        $this->assertTrue((bool) $admin, 'An existing System Admin user is required for this smoke test.');

        $members = ConsortiumThinkTank::query()
            ->with(['portalUser.role', 'consortium'])
            ->where('status', 'active')
            ->whereNotNull('portal_user_id')
            ->whereHas('portalUser')
            ->orderBy('created_at')
            ->get()
            ->unique(fn (ConsortiumThinkTank $member): string => (string) $member->portal_user_id)
            ->values()
            ->take(2);
        $this->assertSame(2, $members->count(), 'Two active think tanks with distinct existing portal users are required.');

        $firstMember = $members->get(0);
        $secondMember = $members->get(1);
        $firstUser = $firstMember->portalUser;
        $secondUser = $secondMember->portalUser;

        $admin->forceFill([
            'role_id' => $adminRole->id,
            'is_disabled' => false,
            'disabled_at' => null,
            'disabled_until' => null,
            'is_blacklisted' => false,
            'blacklisted_at' => null,
        ])->save();

        foreach ([$firstUser, $secondUser] as $user) {
            $user->forceFill([
                'user_type' => 'think_tank',
                'role_id' => $thinkTankRole->id,
                'is_disabled' => false,
                'disabled_at' => null,
                'disabled_until' => null,
                'is_blacklisted' => false,
                'blacklisted_at' => null,
                'otp_verified_at' => now(),
            ])->save();
            $user->unsetRelation('role');
        }

        $projectComponent = Project::query()
            ->with('program.sector')
            ->whereHas('program.sector')
            ->orderBy('name')
            ->first();
        $this->assertTrue((bool) $projectComponent, 'An existing project component is required for this smoke test.');
        $portfolio = $projectComponent->program->sector;

        $indicator = Indicator::query()->create([
            'indicatorable_type' => Sector::class,
            'indicatorable_id' => $portfolio->id,
            'project_component_id' => $projectComponent->id,
            'name' => 'Smoke numeric performance indicator '.Str::upper(Str::random(6)),
            'baseline_type' => 'year',
            'baseline_value' => 0,
            'definitions' => 'Temporary numeric indicator used to verify M&E form result mapping.',
            'primary_source' => 'Think tank data collection smoke test',
            'responsible_user_id' => $admin->id,
            'responsible_party' => json_encode([$admin->id], JSON_THROW_ON_ERROR),
            'created_by' => $admin->id,
        ]);

        return compact(
            'admin',
            'portfolio',
            'projectComponent',
            'indicator',
            'firstMember',
            'secondMember',
            'firstUser',
            'secondUser'
        );
    }

    private function createWorkflowThroughAdmin(array $context): array
    {
        $token = Str::upper(Str::random(8));
        $formCode = 'SMOKE-FORM-'.$token;
        $formTitle = 'Smoke Think Tank Performance Form '.$token;
        $periodCode = 'SMOKE-PERIOD-'.$token;
        $periodLabel = 'Smoke reporting period '.$token;

        $this->asAdmin($context['admin'])
            ->postWithCsrf(route('budget.me.data-entry.forms.store'), [
                'portfolio_id' => $context['portfolio']->id,
                'indicator_id' => $context['indicator']->id,
                'code' => $formCode,
                'title' => $formTitle,
                'description' => 'Transactional form created by the M&E data-entry smoke test.',
                'instructions' => 'Provide the required performance value and an optional implementation note.',
                'responsible_user_id' => $context['admin']->id,
                'sections' => [
                    [
                        'section_key' => 'performance_results',
                        'name' => 'Performance results',
                        'description' => 'Report the measurable result achieved during this period.',
                        'background_color' => '#F0FDF4',
                        'sort_order' => 10,
                    ],
                    [
                        'section_key' => 'supporting_context',
                        'name' => 'Supporting context',
                        'description' => 'Add narrative context and documentary evidence.',
                        'background_color' => '#FFF7ED',
                        'sort_order' => 20,
                    ],
                ],
                'fields' => [
                    [
                        'field_key' => 'reported_value',
                        'section_key' => 'performance_results',
                        'label' => 'Reported performance value',
                        'field_type' => MeDataEntryFormField::TYPE_NUMBER,
                        'is_required' => 1,
                        'help_text' => 'Enter the value achieved during this reporting period.',
                        'unit_label' => 'Number',
                        'indicator_id' => $context['indicator']->id,
                    ],
                    [
                        'field_key' => 'implementation_note',
                        'section_key' => 'supporting_context',
                        'label' => 'Implementation note',
                        'field_type' => MeDataEntryFormField::TYPE_TEXTAREA,
                        'is_required' => 0,
                        'help_text' => 'Optional context for the reported value.',
                    ],
                    [
                        'field_key' => 'supporting_evidence',
                        'section_key' => 'supporting_context',
                        'label' => 'Supporting evidence',
                        'field_type' => MeDataEntryFormField::TYPE_FILE,
                        'is_required' => 0,
                        'help_text' => 'Upload a PDF or Word document as supporting evidence.',
                        'validation' => [
                            'allowed_extensions' => ['pdf', 'docx'],
                            'max_file_size_mb' => 2,
                            'multiple' => false,
                        ],
                    ],
                ],
            ])
            ->assertRedirect(route('budget.me.rebuild.data-entry', ['tab' => 'forms']));

        $form = MeDataEntryForm::query()->where('title', $formTitle)->first();
        $this->assertTrue((bool) $form, 'Admin form creation route did not persist the form.');
        $generatedFormCode = (string) $form->code;
        $this->assertTrue(
            preg_match('/^MEF-'.now()->format('Y').'-[A-Z0-9]{8}$/', $generatedFormCode) === 1,
            'The generated template code is not readable or does not match the MEF-year-token contract.'
        );
        $this->assertTrue($generatedFormCode !== $formCode, 'The client was able to control the generated template code.');
        $this->assertSame(
            0,
            MeDataEntryForm::query()->where('code', $formCode)->count(),
            'The client-supplied template code was persisted.'
        );
        $this->assertSame(MeDataEntryForm::STATUS_DRAFT, $form->status, 'New form was not saved as a draft.');
        $this->assertSame($context['indicator']->id, $form->indicator_id, 'The form-level indicator link was not persisted.');
        $this->assertSame($context['indicator']->id, $form->indicator?->id, 'The form indicator relationship does not resolve to the linked indicator.');
        $this->assertSame(3, $form->fields()->count(), 'The form did not persist all configured fields.');
        $this->assertSame(2, $form->sections()->count(), 'The form did not persist both configured sections.');

        $requiredField = $form->fields()->where('field_key', 'reported_value')->first();
        $optionalField = $form->fields()->where('field_key', 'implementation_note')->first();
        $uploadField = $form->fields()->where('field_key', 'supporting_evidence')->first();
        $performanceSection = $form->sections()->where('section_key', 'performance_results')->first();
        $supportingSection = $form->sections()->where('section_key', 'supporting_context')->first();
        $this->assertSame('#F0FDF4', $performanceSection?->background_color, 'Performance section colour was not preserved.');
        $this->assertSame('#FFF7ED', $supportingSection?->background_color, 'Supporting section colour was not preserved.');
        $this->assertTrue((bool) $requiredField && (bool) $requiredField->is_required, 'Required mapped number field is missing.');
        $this->assertSame($context['indicator']->id, $requiredField->indicator_id, 'Required field was not mapped to the indicator.');
        $this->assertTrue((bool) $optionalField && ! $optionalField->is_required, 'Optional text field is missing.');
        $this->assertSame(MeDataEntryFormField::TYPE_FILE, $uploadField?->field_type, 'Upload field is missing.');
        $this->assertSame(['pdf', 'docx'], data_get($uploadField?->validation, 'allowed_extensions'), 'Upload extensions were not preserved.');
        $this->assertSame(2, data_get($uploadField?->validation, 'max_file_size_mb'), 'Upload size limit was not preserved.');
        $this->assertSame($performanceSection?->id, $requiredField?->section_id, 'Performance question was not linked to its section.');
        $this->assertSame($supportingSection?->id, $optionalField?->section_id, 'Narrative question was not linked to its section.');
        $this->assertSame($supportingSection?->id, $uploadField?->section_id, 'Upload question was not linked to its section.');

        $form->load(['sections.fields']);
        $updateSections = $form->sections->values()->map(fn (MeDataEntryFormSection $section, int $index): array => [
            'id' => $section->id,
            'section_key' => $section->section_key,
            'name' => $section->name,
            'description' => $section->description,
            'background_color' => $section->background_color,
            'sort_order' => ($index + 1) * 10,
        ])->all();
        $updateFields = $form->sections->values()->flatMap(
            fn (MeDataEntryFormSection $section) => $section->fields->values()->map(
                fn (MeDataEntryFormField $field, int $index): array => [
                    'id' => $field->id,
                    'field_key' => $field->field_key,
                    'section_key' => $section->section_key,
                    'label' => $field->label,
                    'field_type' => $field->field_type,
                    'is_required' => (bool) $field->is_required,
                    'help_text' => $field->help_text,
                    'options' => implode(PHP_EOL, $field->options ?? []),
                    'unit_label' => $field->unit_label,
                    'indicator_id' => $field->indicator_id,
                    'sort_order' => ($index + 1) * 10,
                    'validation' => $field->validation ?? [],
                ]
            )
        )->values()->all();
        $this->asAdmin($context['admin'])
            ->putWithCsrf(route('budget.me.data-entry.forms.update', $form), [
                'portfolio_id' => $form->portfolio_id,
                'indicator_id' => $form->indicator_id,
                'code' => 'CLIENT-UPDATE-MUST-BE-IGNORED',
                'title' => $form->title,
                'description' => $form->description,
                'instructions' => $form->instructions,
                'responsible_user_id' => $form->responsible_user_id,
                'sections' => $updateSections,
                'fields' => $updateFields,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget.me.rebuild.data-entry', ['tab' => 'forms']));
        $this->assertSame(
            $generatedFormCode,
            (string) $form->fresh()->code,
            'Posting a different code during update changed the immutable template code.'
        );

        $form->forceFill(['code' => 'MODEL-UPDATE-MUST-BE-IGNORED'])->save();
        $this->assertSame(
            $generatedFormCode,
            (string) $form->fresh()->code,
            'The model allowed the immutable template code to be changed.'
        );

        $uniquenessProbe = new MeDataEntryForm;
        $uniquenessProbe->forceFill([
            'portfolio_id' => $context['portfolio']->id,
            'indicator_id' => $context['indicator']->id,
            'code' => $generatedFormCode,
            'title' => 'Template code uniqueness probe '.$token,
            'responsible_user_id' => $context['admin']->id,
            'status' => MeDataEntryForm::STATUS_DRAFT,
            'created_by' => $context['admin']->id,
            'updated_by' => $context['admin']->id,
        ])->save();
        $this->assertTrue(
            preg_match('/^MEF-'.now()->format('Y').'-[A-Z0-9]{8}$/', (string) $uniquenessProbe->code) === 1,
            'A subsequent generated template code does not match the readable format.'
        );
        $this->assertTrue(
            (string) $uniquenessProbe->code !== $generatedFormCode,
            'Two templates received the same generated code.'
        );
        $this->assertSame(
            1,
            MeDataEntryForm::query()->where('code', $generatedFormCode)->count(),
            'The generated template code is not unique in storage.'
        );
        $uniquenessProbe->delete();

        $this->asAdmin($context['admin'])
            ->postWithCsrf(route('budget.me.data-entry.forms.publish', $form))
            ->assertRedirect(route('budget.me.rebuild.data-entry', ['tab' => 'forms']));
        $this->assertSame(
            MeDataEntryForm::STATUS_PUBLISHED,
            $form->fresh()->status,
            'Admin publish route did not publish the form.'
        );

        $periodStart = now()->startOfMonth()->toDateString();
        $periodEnd = now()->endOfMonth()->toDateString();
        $this->asAdmin($context['admin'])
            ->postWithCsrf(route('budget.me.data-entry.periods.store'), [
                'portfolio_id' => $context['portfolio']->id,
                'code' => $periodCode,
                'label' => $periodLabel,
                'period_type' => MeReportingPeriod::TYPE_CUSTOM,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'status' => MeReportingPeriod::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('budget.me.rebuild.data-entry', ['tab' => 'periods']));

        $period = MeReportingPeriod::query()->where('code', $periodCode)->first();
        $this->assertTrue((bool) $period, 'Admin reporting-period route did not persist the period.');
        $this->assertSame(MeReportingPeriod::STATUS_ACTIVE, $period->status, 'Reporting period is not active.');

        $opensAt = now()->subMinute()->format('Y-m-d H:i:s');
        $dueAt = now()->addDay()->format('Y-m-d H:i:s');
        $closesAt = now()->addDays(2)->format('Y-m-d H:i:s');
        $this->asAdmin($context['admin'])
            ->postWithCsrf(route('budget.me.data-entry.collections.store'), [
                'form_id' => $form->id,
                'reporting_period_id' => $period->id,
                'instructions' => 'Submit this reporting-period update before the deadline.',
                'opens_at' => $opensAt,
                'due_at' => $dueAt,
                'closes_at' => $closesAt,
                'status' => MeDataCollection::STATUS_OPEN,
                'member_ids' => [$context['firstMember']->id],
            ])
            ->assertRedirect(route('budget.me.rebuild.data-entry', ['tab' => 'collections']));

        $collection = MeDataCollection::query()
            ->where('form_id', $form->id)
            ->where('reporting_period_id', $period->id)
            ->first();
        $this->assertTrue((bool) $collection, 'Admin collection route did not persist the collection.');
        $this->assertSame(MeDataCollection::STATUS_OPEN, $collection->status, 'Collection was not opened.');
        $this->assertSame(1, $collection->assignments()->count(), 'Collection must have exactly one assigned think tank.');

        $assignment = $collection->assignments()->first();
        $this->assertSame(
            $context['firstMember']->id,
            $assignment->think_tank_member_id,
            'Collection was assigned to the wrong think tank.'
        );

        return compact(
            'form',
            'requiredField',
            'optionalField',
            'uploadField',
            'performanceSection',
            'supportingSection',
            'period',
            'collection',
            'assignment',
            'formTitle',
            'periodLabel'
        );
    }

    private function assertExpandedTypeCataloguePersists(array $context): void
    {
        $this->assertSame(23, count(MeDataEntryFormField::ALLOWED_TYPES), 'The answer-type catalogue is incomplete.');

        $form = MeDataEntryForm::query()->create([
            'portfolio_id' => $context['portfolio']->id,
            'indicator_id' => $context['indicator']->id,
            'code' => 'SMOKE-TYPES-'.Str::upper(Str::random(8)),
            'title' => 'Expanded answer type constraint smoke form',
            'responsible_user_id' => $context['admin']->id,
            'status' => MeDataEntryForm::STATUS_DRAFT,
            'created_by' => $context['admin']->id,
            'updated_by' => $context['admin']->id,
        ]);

        foreach (MeDataEntryFormField::ALLOWED_TYPES as $index => $type) {
            $form->fields()->create([
                'field_key' => 'type_'.$type,
                'label' => Str::headline($type),
                'field_type' => $type,
                'options' => in_array($type, MeDataEntryFormField::CHOICE_TYPES, true)
                    ? [['value' => 'one', 'label' => 'One'], ['value' => 'two', 'label' => 'Two']]
                    : null,
                'validation' => [],
                'is_required' => false,
                'sort_order' => ($index + 1) * 10,
            ]);
        }

        $this->assertSame(
            MeDataEntryFormField::ALLOWED_TYPES,
            $form->fields()->orderBy('sort_order')->pluck('field_type')->all(),
            'One or more expanded answer types could not be persisted.'
        );
    }

    private function assertTemplateIndicatorIsRequired(array $context): void
    {
        $code = 'SMOKE-MISSING-INDICATOR-'.Str::upper(Str::random(8));
        $title = 'Template without an indicator '.$code;

        $this->asAdmin($context['admin'])
            ->postWithCsrf(route('budget.me.data-entry.forms.store'), [
                'portfolio_id' => $context['portfolio']->id,
                'code' => $code,
                'title' => $title,
                'responsible_user_id' => $context['admin']->id,
                'sections' => [[
                    'section_key' => 'results',
                    'name' => 'Results',
                    'description' => 'Provide the result measured during this period.',
                    'background_color' => '#EFF6FF',
                ]],
                'fields' => [[
                    'section_key' => 'results',
                    'label' => 'Reported result',
                    'field_type' => MeDataEntryFormField::TYPE_NUMBER,
                    'is_required' => 1,
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('indicator_id');

        $this->assertSame(
            0,
            MeDataEntryForm::query()->where('title', $title)->count(),
            'A form without its required form-level indicator was persisted.'
        );
        $this->flushSession();
    }

    private function assertLegacySectionPayloadCompatibility(array $context): void
    {
        $token = Str::upper(Str::random(8));
        $code = 'SMOKE-LEGACY-SECTIONS-'.$token;
        $title = 'Legacy flat section payload '.$token;

        $this->asAdmin($context['admin'])
            ->postWithCsrf(route('budget.me.data-entry.forms.store'), [
                'portfolio_id' => $context['portfolio']->id,
                'indicator_id' => $context['indicator']->id,
                'code' => $code,
                'title' => $title,
                'responsible_user_id' => $context['admin']->id,
                'fields' => [
                    [
                        'section' => 'Legacy results',
                        'label' => 'Legacy result question',
                        'field_type' => MeDataEntryFormField::TYPE_TEXT,
                        'is_required' => 1,
                    ],
                    [
                        'section' => 'Legacy evidence',
                        'label' => 'Legacy evidence question',
                        'field_type' => MeDataEntryFormField::TYPE_TEXTAREA,
                        'is_required' => 0,
                    ],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('budget.me.rebuild.data-entry', ['tab' => 'forms']));

        $form = MeDataEntryForm::query()->where('title', $title)->first();
        $this->assertTrue((bool) $form, 'Legacy flat form payload was not accepted.');
        $this->assertSame(2, $form->sections()->count(), 'Legacy section names were not normalized into section records.');
        $this->assertSame(0, $form->fields()->whereNull('section_id')->count(), 'Legacy questions were left without section links.');

        foreach ($form->sections as $section) {
            $this->assertTrue(
                in_array($section->background_color, MeDataEntryFormSection::SOFT_BACKGROUND_COLORS, true),
                'A synthesized legacy section did not receive a safe background colour.'
            );
            $this->assertTrue(
                filled($section->description),
                'A synthesized legacy section did not receive respondent guidance.'
            );
        }
    }

    private function assertEmptySectionsAreRejected(array $context): void
    {
        $code = 'SMOKE-EMPTY-SECTION-'.Str::upper(Str::random(8));
        $title = 'Invalid empty-section smoke form '.$code;

        $this->asAdmin($context['admin'])
            ->postWithCsrf(route('budget.me.data-entry.forms.store'), [
                'portfolio_id' => $context['portfolio']->id,
                'indicator_id' => $context['indicator']->id,
                'code' => $code,
                'title' => $title,
                'responsible_user_id' => $context['admin']->id,
                'sections' => [
                    [
                        'section_key' => 'answered_section',
                        'name' => 'Answered section',
                        'description' => 'Complete the question in this section.',
                        'background_color' => '#EFF6FF',
                    ],
                    [
                        'section_key' => 'empty_section',
                        'name' => 'Empty section',
                        'description' => 'This deliberately empty section must be rejected.',
                        'background_color' => '#F0FDF4',
                    ],
                ],
                'fields' => [[
                    'section_key' => 'answered_section',
                    'label' => 'Only question',
                    'field_type' => MeDataEntryFormField::TYPE_TEXT,
                    'is_required' => 1,
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('sections.1.name');

        $this->assertSame(
            0,
            MeDataEntryForm::query()->where('title', $title)->count(),
            'A form with an empty section was persisted.'
        );
        $this->flushSession();
    }

    private function assertMissingSectionGuidanceIsRejected(array $context): void
    {
        $code = 'SMOKE-MISSING-GUIDANCE-'.Str::upper(Str::random(8));
        $title = 'Invalid missing-guidance smoke form '.$code;

        $this->asAdmin($context['admin'])
            ->postWithCsrf(route('budget.me.data-entry.forms.store'), [
                'portfolio_id' => $context['portfolio']->id,
                'indicator_id' => $context['indicator']->id,
                'code' => $code,
                'title' => $title,
                'responsible_user_id' => $context['admin']->id,
                'sections' => [[
                    'section_key' => 'unexplained_section',
                    'name' => 'Unexplained section',
                    'description' => '',
                    'background_color' => '#EFF6FF',
                ]],
                'fields' => [[
                    'section_key' => 'unexplained_section',
                    'label' => 'A question that needs guidance',
                    'field_type' => MeDataEntryFormField::TYPE_TEXT,
                    'is_required' => 1,
                ]],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('sections.0.description');

        $this->assertSame(
            0,
            MeDataEntryForm::query()->where('title', $title)->count(),
            'A form with missing section guidance was persisted.'
        );
        $this->flushSession();
    }

    private function assertAdminWorkspace(User $admin, array $workflow): void
    {
        $this->asAdmin($admin)
            ->get(route('budget.me.rebuild.data-entry'))
            ->assertOk()
            ->assertSee('Data Entry and Performance Tracking')
            ->assertSee('Active Collections')
            ->assertSee('Form Templates')
            ->assertSee('Reporting Periods')
            ->assertSee('Submissions')
            ->assertSee($workflow['formTitle'])
            ->assertSee($workflow['periodLabel']);

        $createFormResponse = $this->asAdmin($admin)
            ->get(route('budget.me.rebuild.data-entry', ['tab' => 'forms', 'create' => 'form']));
        $createFormResponse
            ->assertOk()
            ->assertSee('Form sections and questions')
            ->assertSee('General information')
            ->assertSee('Add another section')
            ->assertSee('Background colour')
            ->assertSee('Instructions / explanation')
            ->assertSee('Add question')
            ->assertSee('Each section needs at least one question')
            ->assertSee('Answer type')
            ->assertSee('Short text')
            ->assertSee('Date and time')
            ->assertSee('Dropdown (single choice)')
            ->assertSee('File upload')
            ->assertSee('Image upload')
            ->assertSee('Maximum size (MB)')
            ->assertSee('Template code')
            ->assertSee('placeholder="Assigned automatically when saved"', false)
            ->assertSee('A unique code will be generated when you save this template.')
            ->assertSee('Linked performance indicator')
            ->assertSee('name="indicator_id"', false)
            ->assertSee('data-template-indicator', false)
            ->assertSee('data-form-portfolio', false)
            ->assertSee('data-portfolio="'.$workflow['form']->portfolio_id.'"', false)
            ->assertSee('filterTemplateIndicators', false)
            ->assertSee('id="data-entry-section-template"', false)
            ->assertSee('id="data-entry-field-template"', false)
            ->assertSee('<textarea', false)
            ->assertSee('name="sections[0][description]"', false)
            ->assertSee('name="fields[0][section_key]" value="general_information"', false);

        $createFormHtml = (string) $createFormResponse->getContent();
        preg_match('/<input(?=[^>]*id="form-code")[^>]*>/s', $createFormHtml, $codeInputMatch);
        $codeInputHtml = (string) ($codeInputMatch[0] ?? '');
        $this->assertTrue($codeInputHtml !== '', 'The automatic Template code display is missing from the create form.');
        $this->assertTrue(
            str_contains($codeInputHtml, 'readonly')
                && str_contains($codeInputHtml, 'aria-readonly="true"'),
            'The automatic Template code display is not readonly and accessibility-labelled.'
        );
        $this->assertTrue(
            ! preg_match('/\sname\s*=/', $codeInputHtml),
            'The readonly Template code display still submits a client-controlled code value.'
        );
    }

    private function assertDashboardPerformanceUpdates(array $context, array $workflow): void
    {
        $assignmentUrl = route('think-tank.me-data.show', $workflow['assignment']);

        $this->asThinkTank($context['firstUser'])
            ->get(route('think-tank.dashboard'))
            ->assertOk()
            ->assertSee('Indicator performance updates')
            ->assertSee($workflow['formTitle'])
            ->assertSee($workflow['periodLabel'])
            ->assertSee('Start update')
            ->assertSee($assignmentUrl, false);

        $this->asThinkTank($context['secondUser'])
            ->get(route('think-tank.dashboard'))
            ->assertOk()
            ->assertDontSee($workflow['formTitle'])
            ->assertDontSee($workflow['periodLabel'])
            ->assertDontSee($assignmentUrl, false);
    }

    private function assertAssignedThinkTankWorkflow(array $context, array $workflow): void
    {
        $assignment = $workflow['assignment'];
        $requiredField = $workflow['requiredField'];
        $optionalField = $workflow['optionalField'];
        $uploadField = $workflow['uploadField'];
        $overview = $this->app->make(ThinkTankMeAssignmentService::class)->overview(
            $context['firstMember'],
            [],
            true
        );
        $indicatorCard = $overview['groups']
            ->flatMap(fn ($cards) => $cards)
            ->first(fn (array $card): bool => (string) $card['assignment']->id === (string) $assignment->id);
        $this->assertTrue(is_array($indicatorCard), 'Assigned indicator card is missing from the think-tank overview service.');
        foreach (['indicator', 'indicator_id', 'indicator_code', 'indicator_name', 'indicator_unit'] as $cardKey) {
            $this->assertTrue(array_key_exists($cardKey, $indicatorCard), "Think-tank indicator card is missing [{$cardKey}].");
        }
        $this->assertSame($context['indicator']->id, $indicatorCard['indicator_id'], 'Indicator card ID does not match the form-level link.');
        $this->assertSame($context['indicator']->indicator_code, $indicatorCard['indicator_code'], 'Indicator card code is incorrect.');
        $this->assertSame($context['indicator']->name, $indicatorCard['indicator_name'], 'Indicator card name is incorrect.');

        $this->asThinkTank($context['firstUser'])
            ->get(route('think-tank.me-data.index'))
            ->assertOk()
            ->assertSee('Assigned indicators')
            ->assertSee('Indicator register')
            ->assertSee('data-me-indicator-workspace', false)
            ->assertSee('class="me-indicator-row', false)
            ->assertSee($context['indicator']->indicator_code)
            ->assertSee($context['indicator']->name)
            ->assertSee('Form: '.$workflow['formTitle'])
            ->assertSee(route('think-tank.me-data.show', $assignment), false)
            ->assertSee($workflow['formTitle'])
            ->assertSee($workflow['periodLabel'])
            ->assertSee('Requires action');

        $this->asThinkTank($context['firstUser'])
            ->get(route('think-tank.me-data.show', $assignment))
            ->assertOk()
            ->assertSee($workflow['formTitle'])
            ->assertSee($workflow['performanceSection']->name)
            ->assertSee($workflow['performanceSection']->description)
            ->assertSee($workflow['supportingSection']->name)
            ->assertSee($workflow['supportingSection']->description)
            ->assertSee($context['indicator']->indicator_code)
            ->assertSee($context['indicator']->name)
            ->assertSee($requiredField->label)
            ->assertSee($optionalField->label)
            ->assertSee($uploadField->label)
            ->assertSee('Section 1')
            ->assertSee('1 question')
            ->assertSee('Section 2')
            ->assertSee('2 questions')
            ->assertSee('--section-bg: #F0FDF4', false)
            ->assertSee('--section-bg: #FFF7ED', false)
            ->assertSee('Save draft')
            ->assertSee('Submit data')
            ->assertSee('data-me-reporting-form', false)
            ->assertSee('data-me-submit-trigger', false)
            ->assertSee('data-me-submit-action', false)
            ->assertSee('<dialog', false)
            ->assertSee('data-me-confirm-dialog', false)
            ->assertSee('aria-labelledby="me-submit-confirm-title"', false)
            ->assertSee('aria-describedby="me-submit-confirm-description"', false)
            ->assertSee('Confirm indicator submission')
            ->assertSee('data-me-confirm-submit', false)
            ->assertSee('data-me-cancel-submit', false)
            ->assertSee('data-me-close-confirm', false)
            ->assertSee('form.reportValidity()', false)
            ->assertDontSee('onclick="return confirm(', false);

        $this->asThinkTank($context['firstUser'])
            ->postWithCsrf(route('think-tank.me-data.save-draft', $assignment), [
                'answers' => [
                    (string) $uploadField->id => UploadedFile::fake()->create(
                        'unsafe-evidence.exe',
                        10,
                        'application/x-msdownload'
                    ),
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('answers.'.(string) $uploadField->id);

        $this->assertSame(
            0,
            MeDataSubmission::query()->where('assignment_id', $assignment->id)->count(),
            'An invalid private upload created a draft submission.'
        );

        $this->asThinkTank($context['firstUser'])
            ->postWithCsrf(route('think-tank.me-data.save-draft', $assignment), [
                'answers' => [
                    (string) $optionalField->id => 'Partial context saved before the required value is available.',
                    (string) $uploadField->id => UploadedFile::fake()->create(
                        'supporting-evidence.pdf',
                        256,
                        'application/pdf'
                    ),
                ],
                'notes' => 'Draft-only smoke update.',
            ])
            ->assertRedirect(route('think-tank.me-data.show', $assignment));

        $submission = MeDataSubmission::query()->where('assignment_id', $assignment->id)->first();
        $this->assertTrue((bool) $submission, 'Draft save did not create a submission record.');
        $this->assertSame(MeDataSubmission::STATUS_DRAFT, $submission->status, 'Partial save did not remain a draft.');
        $this->assertSame(3, $submission->answers()->count(), 'Draft did not persist one answer row per form field.');
        $uploadAnswer = MeDataSubmissionAnswer::query()
            ->where('submission_id', $submission->id)
            ->where('field_id', $uploadField->id)
            ->first();
        $this->assertTrue((bool) $uploadAnswer, 'Private upload answer metadata is missing.');
        $uploadedFiles = data_get($uploadAnswer->value, 'value');
        $this->assertSame(1, is_array($uploadedFiles) ? count($uploadedFiles) : 0, 'Private upload metadata is malformed.');
        $firstUploadedFile = $uploadedFiles[0];
        $this->assertSame('local', $firstUploadedFile['disk'] ?? null, 'Upload was not stored on the private local disk.');
        $this->assertSame('supporting-evidence.pdf', $firstUploadedFile['original_name'] ?? null, 'Original upload name was not preserved safely.');
        $firstUploadPath = (string) ($firstUploadedFile['path'] ?? '');
        $this->assertTrue(
            Str::startsWith($firstUploadPath, "me-data/submissions/{$submission->id}/{$uploadField->id}/"),
            'Upload path is outside its submission and field scope.'
        );
        $this->assertTrue(Storage::disk('local')->exists($firstUploadPath), 'Private upload was not written to storage.');
        $this->assertSame(
            0,
            IndicatorResult::query()->where('data_submission_id', $submission->id)->count(),
            'Saving a draft must not create an indicator result.'
        );

        $this->asThinkTank($context['firstUser'])
            ->postWithCsrf(route('think-tank.me-data.submit', $assignment), [
                'answers' => [
                    (string) $optionalField->id => 'Required value is deliberately missing.',
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('answers.'.(string) $requiredField->id);

        $submission->refresh();
        $this->assertSame(MeDataSubmission::STATUS_DRAFT, $submission->status, 'Invalid final submission changed the draft status.');
        $this->assertSame(
            $firstUploadPath,
            (string) data_get($uploadAnswer->fresh()->value, 'value.0.path'),
            'A failed submission discarded the existing draft attachment.'
        );
        $this->assertTrue(Storage::disk('local')->exists($firstUploadPath), 'A failed submission deleted the draft attachment.');
        $this->assertSame(
            0,
            IndicatorResult::query()->where('data_submission_id', $submission->id)->count(),
            'Invalid final submission created an indicator result.'
        );

        $this->asThinkTank($context['firstUser'])
            ->postWithCsrf(route('think-tank.me-data.save-draft', $assignment), [
                'answers' => [
                    (string) $uploadField->id => UploadedFile::fake()->create(
                        'replacement-evidence.pdf',
                        384,
                        'application/pdf'
                    ),
                ],
                'notes' => 'Attachment replacement smoke update.',
            ])
            ->assertRedirect(route('think-tank.me-data.show', $assignment));

        $uploadAnswer->refresh();
        $replacementPath = (string) data_get($uploadAnswer->value, 'value.0.path');
        $this->assertTrue($replacementPath !== '' && $replacementPath !== $firstUploadPath, 'Replacement upload did not receive a new private path.');
        $this->assertTrue(Storage::disk('local')->exists($replacementPath), 'Replacement upload is missing from private storage.');
        $this->assertTrue(! Storage::disk('local')->exists($firstUploadPath), 'Replaced attachment was not removed after the successful draft save.');

        $reportedValue = 42.5;
        $this->asThinkTank($context['firstUser'])
            ->postWithCsrf(route('think-tank.me-data.submit', $assignment), [
                'answers' => [
                    (string) $requiredField->id => $reportedValue,
                    (string) $optionalField->id => 'Valid final implementation context.',
                ],
                'notes' => 'Final smoke submission.',
            ])
            ->assertRedirect(route('think-tank.me-data.show', $assignment));

        $submission->refresh();
        $this->assertSame(MeDataSubmission::STATUS_SUBMITTED, $submission->status, 'Valid response was not submitted.');
        $this->assertSame($context['firstUser']->id, $submission->submitted_by, 'Submission user provenance is incorrect.');
        $this->assertTrue((bool) $submission->submitted_at, 'Submission timestamp is missing.');
        $this->assertTrue(is_array($submission->schema_snapshot), 'Submission schema snapshot is missing.');
        $snapshotSections = collect(data_get($submission->schema_snapshot, 'sections', []))->keyBy('section_key');
        $this->assertSame(
            $workflow['performanceSection']->description,
            data_get($snapshotSections->get('performance_results'), 'description'),
            'Submission snapshot did not preserve the performance-section guidance.'
        );
        $this->assertSame(
            $workflow['supportingSection']->description,
            data_get($snapshotSections->get('supporting_context'), 'description'),
            'Submission snapshot did not preserve the supporting-section guidance.'
        );
        $snapshotIndicator = data_get($submission->schema_snapshot, 'form.indicator');
        $this->assertTrue(is_array($snapshotIndicator), 'Submission snapshot is missing the linked form indicator identity.');
        $this->assertSame($context['indicator']->id, data_get($snapshotIndicator, 'id'), 'Snapshot indicator ID is incorrect.');
        $this->assertSame($context['indicator']->indicator_code, data_get($snapshotIndicator, 'code'), 'Snapshot indicator code is incorrect.');
        $this->assertSame($context['indicator']->name, data_get($snapshotIndicator, 'name'), 'Snapshot indicator name is incorrect.');
        $this->assertSame($context['indicator']->definitions, data_get($snapshotIndicator, 'definition'), 'Snapshot indicator definition is incorrect.');
        $this->assertSame(
            $replacementPath,
            (string) data_get($uploadAnswer->fresh()->value, 'value.0.path'),
            'Final submission did not preserve the existing draft attachment.'
        );
        $this->assertTrue(Storage::disk('local')->exists($replacementPath), 'Final submission deleted the existing attachment.');

        $results = IndicatorResult::query()->where('data_submission_id', $submission->id)->get();
        $this->assertSame(1, $results->count(), 'Valid submission must create exactly one mapped indicator result.');
        $result = $results->first();
        $this->assertSame($context['indicator']->id, $result->indicator_id, 'Result indicator provenance is incorrect.');
        $this->assertSame($workflow['period']->id, $result->reporting_period_id, 'Result period provenance is incorrect.');
        $this->assertSame($context['firstMember']->id, $result->think_tank_member_id, 'Result think-tank provenance is incorrect.');
        $this->assertSame($submission->id, $result->data_submission_id, 'Result submission provenance is incorrect.');
        $this->assertSame($requiredField->field_key, $result->source_field_key, 'Result field provenance is incorrect.');
        $this->assertSame($reportedValue, (float) $result->actual_value, 'Result actual value is incorrect.');

        $mappedAnswer = MeDataSubmissionAnswer::query()
            ->where('submission_id', $submission->id)
            ->where('field_id', $requiredField->id)
            ->first();
        $this->assertTrue((bool) $mappedAnswer, 'Mapped numeric answer is missing.');
        $this->assertSame($result->id, $mappedAnswer->indicator_result_id, 'Answer is not linked to its indicator result.');
        $this->assertSame($reportedValue, (float) data_get($mappedAnswer->value, 'value'), 'Answer JSON value is incorrect.');

        $optionalAnswer = MeDataSubmissionAnswer::query()
            ->where('submission_id', $submission->id)
            ->where('field_id', $optionalField->id)
            ->first();
        $this->assertTrue((bool) $optionalAnswer, 'Optional text answer is missing.');
        $this->assertSame(null, $optionalAnswer->indicator_result_id, 'Unmapped text answer created an indicator result link.');

        $downloadParams = [
            'assignment' => $assignment->id,
            'answer' => $uploadAnswer->id,
            'fileIndex' => 0,
        ];
        $downloadUrl = URL::temporarySignedRoute(
            'think-tank.me-data.download',
            now()->addMinutes(5),
            $downloadParams
        );

        $downloadResponse = $this->asThinkTank($context['firstUser'])->get($downloadUrl);
        $downloadResponse
            ->assertOk()
            ->assertDownload('replacement-evidence.pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $cacheControl = (string) $downloadResponse->headers->get('Cache-Control');
        $this->assertTrue(
            str_contains($cacheControl, 'private') && str_contains($cacheControl, 'no-store'),
            'Private evidence download returned a cacheable response.'
        );

        $this->asThinkTank($context['firstUser'])
            ->get(route('think-tank.me-data.download', $downloadParams))
            ->assertForbidden();

        $this->asThinkTank($context['secondUser'])
            ->get($downloadUrl)
            ->assertForbidden();

        $adminDownloadUrl = URL::temporarySignedRoute(
            'think-tank.me-data.download',
            now()->addMinutes(5),
            [...$downloadParams, 'think_tank_member_id' => $context['firstMember']->id]
        );
        $this->asAdmin($context['admin'])
            ->get($adminDownloadUrl)
            ->assertOk()
            ->assertDownload('replacement-evidence.pdf');

        $this->asThinkTank($context['firstUser'])
            ->postWithCsrf(route('think-tank.me-data.submit', $assignment), [
                'answers' => [
                    (string) $requiredField->id => 99,
                    (string) $optionalField->id => 'Duplicate submission attempt.',
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('submission');

        $this->assertSame(
            1,
            IndicatorResult::query()->where('data_submission_id', $submission->id)->count(),
            'Repeated submission created a duplicate indicator result.'
        );
        $this->assertSame(
            $reportedValue,
            (float) IndicatorResult::query()->whereKey($result->id)->value('actual_value'),
            'Repeated submission changed the locked indicator result.'
        );

    }

    private function assertThinkTankIsolation(array $context, array $workflow): void
    {
        $this->asThinkTank($context['secondUser'])
            ->get(route('think-tank.me-data.show', $workflow['assignment']))
            ->assertForbidden();

        $this->asThinkTank($context['secondUser'])
            ->get(route('think-tank.me-data.index'))
            ->assertOk()
            ->assertDontSee($workflow['formTitle'])
            ->assertSee('No indicator has been assigned');
    }

    private function assertSubmittedSectionStructureIsLocked(array $context, array $workflow): void
    {
        $form = $workflow['form']->fresh(['sections.fields']);
        $this->assertTrue((bool) $form, 'Submitted form could not be reloaded for its structure-lock check.');

        $this->asAdmin($context['admin'])
            ->get(route('budget.me.rebuild.data-entry', ['tab' => 'forms', 'edit_form' => $form->id]))
            ->assertOk()
            ->assertSee('Structure locked')
            ->assertSee($form->code)
            ->assertSee('This system-generated code cannot be changed.')
            ->assertSee($context['indicator']->name)
            ->assertSee('name="indicator_id" value="'.$form->indicator_id.'"', false)
            ->assertSee('data-template-indicator', false)
            ->assertSee('data-locked="true"', false)
            ->assertSee($workflow['performanceSection']->name)
            ->assertSee($workflow['supportingSection']->name);

        $sections = $form->sections->values()->map(function (MeDataEntryFormSection $section, int $index): array {
            return [
                'id' => $section->id,
                'section_key' => $section->section_key,
                'name' => $section->name,
                'description' => $section->description,
                'background_color' => $section->background_color,
                'sort_order' => ($index + 1) * 10,
            ];
        })->all();
        $fields = $form->sections->values()->flatMap(
            fn (MeDataEntryFormSection $section) => $section->fields->values()->map(
                fn (MeDataEntryFormField $field, int $index): array => [
                    'id' => $field->id,
                    'field_key' => $field->field_key,
                    'section_key' => $section->section_key,
                    'label' => $field->label,
                    'field_type' => $field->field_type,
                    'is_required' => (bool) $field->is_required,
                    'help_text' => $field->help_text,
                    'options' => implode(PHP_EOL, $field->options ?? []),
                    'unit_label' => $field->unit_label,
                    'indicator_id' => $field->indicator_id,
                    'sort_order' => ($index + 1) * 10,
                    'validation' => $field->validation ?? [],
                ]
            )
        )->values()->all();

        $replacementIndicator = Indicator::query()->create([
            'indicatorable_type' => Sector::class,
            'indicatorable_id' => $context['portfolio']->id,
            'project_component_id' => $context['projectComponent']->id,
            'name' => 'Locked relationship replacement indicator '.Str::upper(Str::random(6)),
            'baseline_type' => 'year',
            'baseline_value' => 0,
            'definitions' => 'This indicator must not replace a linked indicator after submission.',
            'primary_source' => 'M&E structure lock smoke test',
            'responsible_user_id' => $context['admin']->id,
            'responsible_party' => json_encode([$context['admin']->id], JSON_THROW_ON_ERROR),
            'created_by' => $context['admin']->id,
        ]);

        $this->asAdmin($context['admin'])
            ->putWithCsrf(route('budget.me.data-entry.forms.update', $form), [
                'portfolio_id' => $form->portfolio_id,
                'indicator_id' => $replacementIndicator->id,
                'code' => $form->code,
                'title' => $form->title,
                'description' => $form->description,
                'instructions' => $form->instructions,
                'responsible_user_id' => $form->responsible_user_id,
                'sections' => $sections,
                'fields' => $fields,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('fields');

        $this->assertSame(
            $context['indicator']->id,
            $form->fresh()->indicator_id,
            'A submitted form allowed its linked indicator to be replaced.'
        );
        $this->flushSession();

        $sections[0]['background_color'] = '#FDF2F8';

        $this->asAdmin($context['admin'])
            ->putWithCsrf(route('budget.me.data-entry.forms.update', $form), [
                'portfolio_id' => $form->portfolio_id,
                'indicator_id' => $form->indicator_id,
                'code' => $form->code,
                'title' => $form->title,
                'description' => $form->description,
                'instructions' => $form->instructions,
                'responsible_user_id' => $form->responsible_user_id,
                'sections' => $sections,
                'fields' => $fields,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('fields');

        $this->assertSame(
            '#F0FDF4',
            $workflow['performanceSection']->fresh()->background_color,
            'A submitted form allowed its section colour to change.'
        );
        $this->flushSession();
    }

    private function assertParticipantSubmissionsRegister(array $context, array $workflow): void
    {
        $submission = MeDataSubmission::query()
            ->where('assignment_id', $workflow['assignment']->id)
            ->first();
        $this->assertTrue((bool) $submission, 'The participant submission required by the admin register smoke is missing.');

        $registerRoute = route('budget.me.rebuild.data-entry');
        $this->asAdmin($context['admin'])
            ->get($registerRoute.'?'.http_build_query(['tab' => 'submissions']))
            ->assertOk()
            ->assertSee('Participant submissions')
            ->assertSee('>Portfolio</th>', false)
            ->assertSee('>Indicator</th>', false)
            ->assertSee('>Participant / think tank</th>', false)
            ->assertSee('>Template / period</th>', false)
            ->assertSee('>Submitted / status</th>', false)
            ->assertSee('>Review</th>', false)
            ->assertSee('<small>Portfolio</small>', false)
            ->assertSee('<small>Indicator</small>', false)
            ->assertSee('Search submissions')
            ->assertSee('name="q"', false)
            ->assertSee('placeholder="Participant, indicator, template or period"', false)
            ->assertSee('for="data-entry-portfolio-filter">Portfolio</label>', false)
            ->assertSee('for="data-entry-status-filter">Status</label>', false)
            ->assertSee($context['portfolio']->name)
            ->assertSee($context['indicator']->indicator_code)
            ->assertSee($context['indicator']->name)
            ->assertSee($context['firstMember']->name)
            ->assertSee($workflow['formTitle'])
            ->assertSee($workflow['periodLabel']);

        $searchTerms = [
            'portfolio' => $context['portfolio']->name,
            'indicator name' => $context['indicator']->name,
            'indicator code' => $context['indicator']->indicator_code,
            'think tank' => $context['firstMember']->name,
            'template title' => $workflow['formTitle'],
            'template code' => $workflow['form']->code,
            'reporting period label' => $workflow['periodLabel'],
            'reporting period code' => $workflow['period']->code,
            'submission status' => Str::upper($submission->status),
        ];

        foreach ($searchTerms as $source => $term) {
            $this->assertTrue(trim((string) $term) !== '', "The {$source} search fixture is empty.");
            $this->asAdmin($context['admin'])
                ->get($registerRoute.'?'.http_build_query([
                    'tab' => 'submissions',
                    'q' => $term,
                ]))
                ->assertOk()
                ->assertSee($workflow['formTitle'])
                ->assertSee($context['indicator']->name)
                ->assertSee('Filters applied')
                ->assertDontSee('No submissions match these filters');
        }

        $composedQuery = [
            'tab' => 'submissions',
            'q' => $context['indicator']->indicator_code,
            'portfolio_id' => $context['portfolio']->id,
            'status' => MeDataSubmission::STATUS_SUBMITTED,
        ];
        $composedResponse = $this->asAdmin($context['admin'])
            ->get($registerRoute.'?'.http_build_query($composedQuery));
        $composedResponse
            ->assertOk()
            ->assertSee($workflow['formTitle'])
            ->assertSee($context['indicator']->name)
            ->assertSee('Filters applied')
            ->assertDontSee('No submissions match these filters');

        $this->asAdmin($context['admin'])
            ->get($registerRoute.'?'.http_build_query([
                'tab' => 'submissions',
                'q' => 'NO-SUBMISSION-MATCH-'.Str::upper(Str::random(16)),
            ]))
            ->assertOk()
            ->assertSee('No submissions match these filters')
            ->assertSee('Try a different participant, indicator, template, period, portfolio or status.')
            ->assertSee('aria-label="Clear filters"', false)
            ->assertDontSee($workflow['formTitle']);
    }

    private function assertEveryAnswerTypeWorks(array $context): void
    {
        $token = Str::upper(Str::random(8));
        $form = MeDataEntryForm::query()->create([
            'portfolio_id' => $context['portfolio']->id,
            'indicator_id' => $context['indicator']->id,
            'code' => 'SMOKE-ALL-TYPES-'.$token,
            'title' => 'All answer types portal smoke '.$token,
            'description' => 'Temporary form that validates every supported answer control.',
            'responsible_user_id' => $context['admin']->id,
            'status' => MeDataEntryForm::STATUS_PUBLISHED,
            'created_by' => $context['admin']->id,
            'updated_by' => $context['admin']->id,
        ]);

        $choiceOptions = ['Alpha', 'Beta', 'Gamma'];
        $definitions = [
            MeDataEntryFormField::TYPE_INTEGER => ['value' => 6, 'validation' => ['min' => 0, 'max' => 20, 'step' => 2]],
            MeDataEntryFormField::TYPE_NUMBER => ['value' => 2.5, 'validation' => ['min' => 0, 'max' => 10, 'step' => 0.5]],
            MeDataEntryFormField::TYPE_PERCENTAGE => ['value' => 75, 'validation' => ['min' => 0, 'max' => 100]],
            MeDataEntryFormField::TYPE_CURRENCY => ['value' => 123.45, 'validation' => ['min' => 0, 'step' => 0.01]],
            MeDataEntryFormField::TYPE_TEXT => ['value' => 'A concise result', 'validation' => ['min_length' => 3, 'max_length' => 100]],
            MeDataEntryFormField::TYPE_TEXTAREA => ['value' => 'A longer implementation narrative for the reporting period.', 'validation' => ['min_length' => 10, 'max_length' => 500]],
            MeDataEntryFormField::TYPE_EMAIL => ['value' => 'monitoring@example.org', 'validation' => ['max_length' => 255]],
            MeDataEntryFormField::TYPE_PHONE => ['value' => '+254 700 123456', 'validation' => ['max_length' => 30]],
            MeDataEntryFormField::TYPE_URL => ['value' => 'https://example.org/evidence', 'validation' => ['max_length' => 2048]],
            MeDataEntryFormField::TYPE_DATE => ['value' => now()->toDateString()],
            MeDataEntryFormField::TYPE_TIME => ['value' => '14:30'],
            MeDataEntryFormField::TYPE_DATETIME => ['value' => now()->format('Y-m-d\TH:i')],
            MeDataEntryFormField::TYPE_MONTH => ['value' => now()->format('Y-m')],
            MeDataEntryFormField::TYPE_YEAR => ['value' => (int) now()->format('Y')],
            MeDataEntryFormField::TYPE_SELECT => ['value' => 'Alpha', 'options' => $choiceOptions],
            MeDataEntryFormField::TYPE_RADIO => ['value' => 'Beta', 'options' => $choiceOptions],
            MeDataEntryFormField::TYPE_MULTISELECT => ['value' => ['Alpha', 'Gamma'], 'options' => $choiceOptions],
            MeDataEntryFormField::TYPE_CHECKBOX => ['value' => ['Beta'], 'options' => $choiceOptions],
            MeDataEntryFormField::TYPE_YES_NO => ['value' => 'yes'],
            MeDataEntryFormField::TYPE_RATING => ['value' => 3, 'validation' => ['min' => 1, 'max' => 5, 'step' => 2]],
            MeDataEntryFormField::TYPE_SCALE => ['value' => 7, 'validation' => ['min' => 1, 'max' => 10, 'step' => 1]],
            MeDataEntryFormField::TYPE_FILE => [
                'value' => fn () => UploadedFile::fake()->create('all-types-evidence.pdf', 128, 'application/pdf'),
                'validation' => ['allowed_extensions' => ['pdf'], 'max_file_size_mb' => 2, 'multiple' => false],
            ],
            MeDataEntryFormField::TYPE_IMAGE => [
                'value' => fn () => UploadedFile::fake()->image('all-types-image.png', 32, 32),
                'validation' => ['allowed_extensions' => ['png'], 'max_file_size_mb' => 2, 'multiple' => false],
            ],
        ];

        $fields = collect();
        $sortIndex = 0;
        foreach ($definitions as $type => $definition) {
            $sortIndex++;
            $fields->put($type, $form->fields()->create([
                'section' => Str::headline(explode('_', $type)[0]).' answers',
                'field_key' => 'answer_'.$type,
                'label' => 'Smoke '.Str::headline($type),
                'field_type' => $type,
                'options' => $definition['options'] ?? null,
                'validation' => $definition['validation'] ?? [],
                'is_required' => true,
                'sort_order' => $sortIndex * 10,
            ]));
        }

        $period = MeReportingPeriod::query()->create([
            'portfolio_id' => $context['portfolio']->id,
            'code' => 'SMOKE-ALL-TYPES-PERIOD-'.$token,
            'label' => 'All answer types period '.$token,
            'period_type' => MeReportingPeriod::TYPE_CUSTOM,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'status' => MeReportingPeriod::STATUS_ACTIVE,
            'created_by' => $context['admin']->id,
            'updated_by' => $context['admin']->id,
        ]);
        $collection = MeDataCollection::query()->create([
            'form_id' => $form->id,
            'reporting_period_id' => $period->id,
            'opens_at' => now()->subMinute(),
            'due_at' => now()->addDay(),
            'closes_at' => now()->addDays(2),
            'status' => MeDataCollection::STATUS_OPEN,
            'created_by' => $context['admin']->id,
            'updated_by' => $context['admin']->id,
        ]);
        $assignment = $collection->assignments()->create([
            'think_tank_member_id' => $context['firstMember']->id,
            'assigned_by' => $context['admin']->id,
            'assigned_at' => now(),
        ]);

        $rendered = $this->asThinkTank($context['firstUser'])
            ->get(route('think-tank.me-data.show', $assignment));
        $rendered
            ->assertOk()
            ->assertSee('What to do in this section')
            ->assertSee('Complete the questions in this section using the most accurate information available. Review your answers before continuing to the next section.')
            ->assertSee('type="email"', false)
            ->assertSee('type="tel"', false)
            ->assertSee('type="url"', false)
            ->assertSee('type="date"', false)
            ->assertSee('type="time"', false)
            ->assertSee('type="datetime-local"', false)
            ->assertSee('type="month"', false)
            ->assertSee('type="range"', false)
            ->assertSee('type="file"', false)
            ->assertSee('enctype="multipart/form-data"', false);

        $answers = [];
        foreach ($definitions as $type => $definition) {
            $value = $definition['value'];
            $answers[(string) $fields->get($type)->id] = $value instanceof Closure ? $value() : $value;
        }

        $this->asThinkTank($context['firstUser'])
            ->postWithCsrf(route('think-tank.me-data.submit', $assignment), [
                'answers' => $answers,
                'notes' => 'All 23 answer types submitted in one request.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('think-tank.me-data.show', $assignment));

        $submission = MeDataSubmission::query()->where('assignment_id', $assignment->id)->first();
        $this->assertSame(MeDataSubmission::STATUS_SUBMITTED, $submission?->status, 'All-types response was not submitted.');
        $this->assertSame(23, $submission?->answers()->count(), 'Not every answer type persisted a response.');
        $this->assertSame(
            0,
            IndicatorResult::query()->where('data_submission_id', $submission?->id)->count(),
            'Unmapped expanded answer types created indicator results.'
        );

        $multiSelectAnswer = $submission->answers()
            ->where('field_id', $fields->get(MeDataEntryFormField::TYPE_MULTISELECT)->id)
            ->first();
        $checkboxAnswer = $submission->answers()
            ->where('field_id', $fields->get(MeDataEntryFormField::TYPE_CHECKBOX)->id)
            ->first();
        $this->assertSame(
            ['Alpha', 'Gamma'],
            data_get($multiSelectAnswer?->value, 'value'),
            'The multi-select response was not persisted as an array.'
        );
        $this->assertSame(
            ['Beta'],
            data_get($checkboxAnswer?->value, 'value'),
            'The checkbox response was not persisted as an array.'
        );

        foreach ([MeDataEntryFormField::TYPE_FILE, MeDataEntryFormField::TYPE_IMAGE] as $uploadType) {
            $answer = $submission->answers()->where('field_id', $fields->get($uploadType)->id)->first();
            $path = (string) data_get($answer?->value, 'value.0.path');
            $this->assertTrue($path !== '' && Storage::disk('local')->exists($path), "The {$uploadType} answer was not stored privately.");
        }

        $this->asThinkTank($context['firstUser'])
            ->get(route('think-tank.me-data.show', $assignment))
            ->assertOk()
            ->assertSee('Alpha, Gamma')
            ->assertSee('Beta')
            ->assertSee('all-types-evidence.pdf')
            ->assertSee('all-types-image.png')
            ->assertSee('All 23 answer types submitted in one request.');
    }

    private function assertDraftAndClosedCollectionRules(array $context, array $workflow): void
    {
        $token = Str::upper(Str::random(8));
        $hiddenTitle = 'Hidden Draft Collection Form '.$token;

        $form = MeDataEntryForm::query()->create([
            'portfolio_id' => $context['portfolio']->id,
            'indicator_id' => $context['indicator']->id,
            'code' => 'SMOKE-HIDDEN-'.$token,
            'title' => $hiddenTitle,
            'description' => 'This form must remain hidden while its collection is a draft.',
            'responsible_user_id' => $context['admin']->id,
            'status' => MeDataEntryForm::STATUS_PUBLISHED,
            'created_by' => $context['admin']->id,
            'updated_by' => $context['admin']->id,
        ]);
        $field = $form->fields()->create([
            'section' => 'Closed collection check',
            'field_key' => 'closed_collection_note',
            'label' => 'Closed collection note',
            'field_type' => MeDataEntryFormField::TYPE_TEXT,
            'is_required' => false,
            'sort_order' => 10,
        ]);
        $period = MeReportingPeriod::query()->create([
            'portfolio_id' => $context['portfolio']->id,
            'code' => 'SMOKE-HIDDEN-PERIOD-'.$token,
            'label' => 'Hidden draft period '.$token,
            'period_type' => MeReportingPeriod::TYPE_CUSTOM,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'status' => MeReportingPeriod::STATUS_ACTIVE,
            'created_by' => $context['admin']->id,
            'updated_by' => $context['admin']->id,
        ]);
        $collection = MeDataCollection::query()->create([
            'form_id' => $form->id,
            'reporting_period_id' => $period->id,
            'opens_at' => now()->subMinute(),
            'due_at' => now()->addHour(),
            'closes_at' => now()->addHours(2),
            'status' => MeDataCollection::STATUS_DRAFT,
            'created_by' => $context['admin']->id,
            'updated_by' => $context['admin']->id,
        ]);
        $assignment = $collection->assignments()->create([
            'think_tank_member_id' => $context['firstMember']->id,
            'assigned_by' => $context['admin']->id,
            'assigned_at' => now(),
        ]);

        $this->asThinkTank($context['firstUser'])
            ->get(route('think-tank.me-data.index'))
            ->assertOk()
            ->assertDontSee($hiddenTitle);

        $collection->update([
            'status' => MeDataCollection::STATUS_CLOSED,
            'updated_by' => $context['admin']->id,
        ]);

        $this->asThinkTank($context['firstUser'])
            ->get(route('think-tank.me-data.show', $assignment))
            ->assertOk()
            ->assertSee($hiddenTitle)
            ->assertSee('Closed');

        $this->asThinkTank($context['firstUser'])
            ->postWithCsrf(route('think-tank.me-data.save-draft', $assignment), [
                'answers' => [(string) $field->id => 'This change must be blocked.'],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('collection');

        $this->asThinkTank($context['firstUser'])
            ->postWithCsrf(route('think-tank.me-data.submit', $assignment), [
                'answers' => [(string) $field->id => 'This final submission must also be blocked.'],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('collection');

        $this->assertSame(
            0,
            MeDataSubmission::query()->where('assignment_id', $assignment->id)->count(),
            'Closed collection accepted a draft or final submission.'
        );
    }

    private function assertSchemaReady(): void
    {
        foreach ([
            'me_data_entry_forms',
            'me_data_entry_form_sections',
            'me_data_entry_form_fields',
            'me_reporting_periods',
            'me_data_collections',
            'me_data_collection_assignments',
            'me_data_submissions',
            'me_data_submission_answers',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Required M&E workflow table [{$table}] is missing.");
        }

        $this->assertTrue(
            Schema::hasColumn('me_data_entry_form_fields', 'section_id'),
            'M&E form fields are missing their section relationship.'
        );
        $this->assertTrue(
            Schema::hasColumn('me_data_entry_forms', 'indicator_id'),
            'M&E form templates are missing their form-level indicator relationship.'
        );
    }

    private function asAdmin(User $user): self
    {
        return $this->asAuthenticatedUser($user);
    }

    private function asThinkTank(User $user): self
    {
        return $this->asAuthenticatedUser($user);
    }

    private function asAuthenticatedUser(User $user): self
    {
        $this->actingAs($user)->withSession([
            'otp_verified' => true,
            'otp_verified_user_id' => (string) $user->id,
            'otp_verified_at' => now()->toIso8601String(),
        ]);

        return $this;
    }

    private function postWithCsrf(string $uri, array $data = [])
    {
        $token = Str::random(40);

        return $this->withSession(['_token' => $token])
            ->post($uri, ['_token' => $token, ...$data]);
    }

    private function putWithCsrf(string $uri, array $data = [])
    {
        $token = Str::random(40);

        return $this->withSession(['_token' => $token])
            ->put($uri, ['_token' => $token, ...$data]);
    }

    private function assertTrue(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }

    private function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($message.' Expected '.var_export($expected, true).', got '.var_export($actual, true).'.');
        }
    }
}

(new MeDataEntryWorkflowSmoke($app))->run();
