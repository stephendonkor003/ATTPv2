<?php

use App\Models\ConsortiumThinkTank;
use App\Models\Indicator;
use App\Models\IndicatorTarget;
use App\Models\MeDataCollection;
use App\Models\MeDataCollectionAssignment;
use App\Models\MeDataEntryForm;
use App\Models\MeFramework;
use App\Models\MePerformanceReport;
use App\Models\Project;
use App\Models\ReportingFrequency;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use App\Support\IndicatorReportingSchedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithExceptionHandling;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

(new PHPUnit\TextUI\Configuration\Builder)->build(['phpunit']);

class MePerformanceReportingSmoke
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
        foreach ([
            'me_data_entry_form_indicators',
            'me_performance_reports',
            'me_performance_report_indicator_results',
            'me_performance_report_documents',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Required table [{$table}] is missing.");
        }
        $this->assertTrue(
            Schema::hasColumn('me_performance_reports', 'reporting_scope'),
            'The saved reporter-disaggregation scope column is missing.'
        );

        Storage::fake('local');
        DB::beginTransaction();

        try {
            $context = $this->prepareContext();
            $this->authenticate($context['admin']);

            $this->get(route('budget.me.performance-reports.create'))
                ->assertOk()
                ->assertSee('Create a Performance Report')
                ->assertSee('Reporter disaggregation selection')
                ->assertSee('Indicator measurement plan')
                ->assertSee($context['form']->title)
                ->assertSee($context['component']->name);

            $this->postWithCsrf(route('budget.me.performance-reports.store'), [
                'form_id' => $context['form']->id,
                'reporting_quarter' => $context['quarter'],
                'reporting_year' => 2026,
                'reporting_scope' => [
                    'geographic_scope' => 'national',
                    'country' => 'Ghana',
                    'priority_theme' => 'regional_trade',
                    'gender' => 'female',
                    'age_group' => 'youth_below_35',
                    'stakeholder_category' => 'government',
                ],
            ])->assertRedirect();

            $report = MePerformanceReport::query()
                ->with(['indicatorResults', 'responsibleDirectorate'])
                ->where('form_id', $context['form']->id)
                ->where('reporting_year', 2026)
                ->where('reporting_quarter', $context['quarter'])
                ->first();

            $this->assertTrue((bool) $report, 'Creating a report did not persist the report header.');
            $this->assertSame(
                (string) $context['component']->id,
                (string) $report->project_component_id,
                'The report did not snapshot its project component.'
            );
            $this->assertSame(
                (string) $context['component']->governance_node_id,
                (string) $report->responsible_directorate_id,
                'The report did not snapshot the responsible Directorate.'
            );
            $this->assertSame(1, $report->indicatorResults->count(), 'The due indicator was not linked to the report.');
            $this->assertSame(
                'Ghana',
                $report->reporting_scope['country'] ?? null,
                'The reporter-selected disaggregation scope was not saved.'
            );
            $this->assertSame(
                125.0,
                (float) $report->indicatorResults->first()->target_value,
                'The approved indicator target was not copied into the report.'
            );

            $this->get(route('budget.me.rebuild.data-entry', ['tab' => 'reports']))
                ->assertOk()
                ->assertSee('Performance Reports')
                ->assertSee($context['form']->title)
                ->assertSee($context['component']->name);

            $this->get(route('budget.me.performance-reports.edit', $report))
                ->assertOk()
                ->assertSee('Indicator results and progress against target')
                ->assertSee('Indicator reporting dashboard')
                ->assertSee('Indicator reference and result table')
                ->assertSee('Means of Verification')
                ->assertSee('Lessons learned')
                ->assertSee('Mandatory section check')
                ->assertSee('Not started')
                ->assertSee('Submit Report')
                ->assertSee($context['indicator']->name);

            $this->postWithCsrf(route('budget.me.performance-reports.submit', $report))
                ->assertRedirect()
                ->assertSessionHasErrors('report');
            $this->assertSame(
                MePerformanceReport::STATUS_DRAFT,
                $report->fresh()->status,
                'An incomplete report bypassed the mandatory section gate.'
            );

            $reportResult = $report->indicatorResults->first();
            $payload = [
                'indicator_results' => [
                    $reportResult->id => ['actual_value' => 100],
                ],
                'key_achievements' => 'The reporting-period milestone was substantially delivered.',
                'variance_explanation' => 'The remaining variance is caused by a short procurement delay.',
                'means_of_verification_notes' => 'Signed completion record and source workbook attached.',
                'overall_assessment' => 'Performance remains on track with a recoverable variance.',
                'performance_rating' => 'on_track',
                'conclusion' => 'Delivery is credible and supported by the submitted evidence.',
                'challenges_faced' => 'A supplier delivery was delayed.',
                'mitigation_strategies' => 'The delivery schedule was revised and monitored weekly.',
                'lessons_learned' => 'Earlier supplier confirmation reduces schedule exposure.',
                'adaptive_management_actions' => 'Confirm all critical inputs one month earlier.',
                'next_period_priorities' => 'Complete the outstanding milestone and verify quality.',
                'document_names' => ['Signed completion record'],
                'documents' => [
                    UploadedFile::fake()->create('completion-record.pdf', 128, 'application/pdf'),
                ],
            ];

            $this->putWithCsrf(route('budget.me.performance-reports.update', $report), $payload)
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $this->postWithCsrf(route('budget.me.performance-reports.achievements.store', [$report, $reportResult]), [
                'title' => 'ATTP policy evidence product completed',
                'description' => 'A policy-relevant research product was completed and shared with public-sector stakeholders.',
                'achieved_on' => '2026-01-20',
                'geographic_scope' => 'national',
                'country' => 'Ghana',
                'location' => 'Accra',
                'priority_themes' => ['regional_trade'],
            ])->assertRedirect()->assertSessionHasNoErrors();

            $report->refresh()->load(['indicatorResults.indicatorResult', 'documents']);
            $reportResult = $report->indicatorResults->first();
            $this->assertSame(100.0, (float) $reportResult->actual_value, 'The actual result was not saved.');
            $this->assertSame(80.0, (float) $reportResult->progress_percent, 'Target progress was not calculated.');
            $this->assertTrue((bool) $reportResult->indicatorResult, 'The central indicator result was not updated.');
            $this->assertSame(1, $report->documents->count(), 'The MOV attachment was not saved.');

            $this->get(route('budget.me.performance-reports.edit', $report))
                ->assertOk()
                ->assertSee('Report ready for submission')
                ->assertSee('Complete')
                ->assertSee('Submit Report');

            $this->postWithCsrf(route('budget.me.performance-reports.submit', $report))
                ->assertRedirect()
                ->assertSessionHasNoErrors();
            $this->assertSame(
                MePerformanceReport::STATUS_SUBMITTED,
                $report->fresh()->status,
                'The complete report was not submitted.'
            );

            $this->get(route('budget.me.performance-reports.edit', $report))
                ->assertOk()
                ->assertSee('Reject &amp; Return', false)
                ->assertSee('Verify Report')
                ->assertSee('Indicator results and progress against target')
                ->assertSee('Complete');

            $this->postWithCsrf(route('budget.me.performance-reports.review', $report), [
                'review_action' => MePerformanceReport::STATUS_VERIFIED,
                'review_notes' => 'The reported result agrees with the attached source evidence.',
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame(
                MePerformanceReport::STATUS_VERIFIED,
                $report->fresh()->status,
                'The report was not verified.'
            );

            $this->postWithCsrf(route('budget.me.performance-reports.review', $report), [
                'review_action' => MePerformanceReport::STATUS_APPROVED,
                'review_notes' => 'Final approval granted after evidence verification.',
            ])->assertRedirect()->assertSessionHasNoErrors();

            $this->get(route('budget.me.performance-reports.edit', $report))
                ->assertOk()
                ->assertSee('Archive Report')
                ->assertSee('Overall assessment, performance rating and conclusion')
                ->assertSee('Complete');

            $this->postWithCsrf(route('budget.me.performance-reports.archive', $report), [
                'archive_notes' => 'Finalized and retained as the official quarterly performance record.',
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame(
                MePerformanceReport::STATUS_ARCHIVED,
                $report->fresh()->status,
                'The reviewed report was not archived.'
            );

            $collection = MeDataCollection::query()->create([
                'form_id' => $context['form']->id,
                'reporting_period_id' => $report->reporting_period_id,
                'instructions' => 'Prepare the quarterly performance report.',
                'opens_at' => now()->subDay(),
                'due_at' => now()->addDays(7),
                'closes_at' => now()->addDays(14),
                'status' => MeDataCollection::STATUS_OPEN,
                'created_by' => $context['admin']->id,
                'updated_by' => $context['admin']->id,
            ]);
            $assignment = MeDataCollectionAssignment::query()->create([
                'collection_id' => $collection->id,
                'think_tank_member_id' => $context['member']->id,
                'assigned_by' => $context['admin']->id,
                'assigned_at' => now(),
            ]);

            $this->authenticate($context['portalUser']);
            $this->get(route('think-tank.performance-reports.index'))
                ->assertOk()
                ->assertSee('Performance Report Lifecycle')
                ->assertSee($context['form']->title);

            $this->postWithCsrf(route('think-tank.performance-reports.store'), [
                'assignment_id' => $assignment->id,
                'reporting_quarter' => $context['quarter'],
                'reporting_year' => 2026,
            ])->assertRedirect()->assertSessionHasNoErrors();

            $memberReport = MePerformanceReport::query()
                ->with('indicatorResults')
                ->where('think_tank_member_id', $context['member']->id)
                ->where('form_id', $context['form']->id)
                ->first();
            $this->assertTrue((bool) $memberReport, 'The think tank could not create its organization-owned draft.');
            $this->assertSame(
                MePerformanceReport::STATUS_DRAFT,
                $memberReport->status,
                'A new think tank report did not begin in Draft stage.'
            );

            $this->get(route('think-tank.performance-reports.edit', $report))
                ->assertForbidden();
            $this->get(route('think-tank.performance-reports.edit', $memberReport))
                ->assertOk()
                ->assertSee('Save Draft')
                ->assertSee('Submit Report')
                ->assertSee('Mandatory section check')
                ->assertSee('Not started');

            $memberResult = $memberReport->indicatorResults->first();
            $portalPayload = $payload;
            $portalPayload['indicator_results'] = [
                $memberResult->id => ['actual_value' => 110],
            ];
            $portalPayload['documents'] = [
                UploadedFile::fake()->image('partner-evidence.png', 32, 32),
            ];
            $this->putWithCsrf(route('think-tank.performance-reports.update', $memberReport), $portalPayload)
                ->assertRedirect()
                ->assertSessionHasNoErrors();
            $memberReport->refresh()->load('documents.repositoryItem.versions');
            $memberEvidence = $memberReport->documents->first()?->repositoryItem;
            $secretariatEvidence = $report->fresh()->documents()->with('repositoryItem')->first()?->repositoryItem;
            $this->assertTrue((bool) $memberEvidence, 'The think-tank attachment was not synchronized to the Evidence Repository.');
            $this->assertTrue((bool) $secretariatEvidence, 'The Secretariat attachment was not synchronized to the Evidence Repository.');
            $this->assertTrue($context['portalUser']->isThinkTankUser(), 'The security check actor is not a think-tank user.');
            $this->assertSame((string) $context['member']->id, (string) $context['portalUser']->resolvedThinkTankMembership()?->id, 'The security check actor has the wrong membership.');
            $this->assertTrue((string) $memberEvidence->id !== (string) $secretariatEvidence->id, 'The test evidence records unexpectedly resolved to the same repository item.');
            $this->assertTrue(! $secretariatEvidence->reportDocuments()->whereHas('report', fn ($query) => $query->where('think_tank_member_id', $context['member']->id))->exists(), 'Secretariat evidence unexpectedly belongs to the think tank.');
            $this->get(route('budget.me.knowledge-evidence.download', $secretariatEvidence))->assertForbidden();
            $this->get(route('budget.me.knowledge-evidence.download', $memberEvidence))->assertOk();
            $memberEvidenceVersion = $memberEvidence->versions->first();
            $this->assertTrue((bool) $memberEvidenceVersion, 'The initial repository document version was not recorded.');
            $this->get(route('budget.me.knowledge-evidence.versions.download', [$memberEvidence, $memberEvidenceVersion]))->assertOk();
            $this->postWithCsrf(route('budget.me.performance-reports.achievements.store', [$memberReport, $memberResult]), [
                'title' => 'Partner research product completed',
                'description' => 'The assigned think tank completed a policy-relevant research product for the reporting period.',
                'achieved_on' => '2026-01-25',
                'geographic_scope' => 'national',
                'country' => $context['member']->country ?: 'Ghana',
                'priority_themes' => ['regional_trade'],
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->postWithCsrf(route('think-tank.performance-reports.submit', $memberReport))
                ->assertRedirect()
                ->assertSessionHasNoErrors();
            $this->assertSame(
                MePerformanceReport::STATUS_SUBMITTED,
                $memberReport->fresh()->status,
                'The think tank report was not submitted to the Secretariat.'
            );
            $this->postWithCsrf(route('budget.me.performance-reports.review', $memberReport), [
                'review_action' => MePerformanceReport::STATUS_VERIFIED,
                'review_notes' => 'A report author must not be able to approve this report.',
            ])->assertForbidden();

            $context['portalUser']->forceFill([
                'think_tank_access_level' => User::THINK_TANK_ACCESS_PROCUREMENT,
            ])->save();
            $this->authenticate($context['portalUser']);
            $this->get(route('think-tank.performance-reports.index'))->assertForbidden();

            $this->authenticate($context['admin']);
            $this->postWithCsrf(route('budget.me.performance-reports.review', $memberReport), [
                'review_action' => MePerformanceReport::STATUS_VERIFIED,
                'review_notes' => 'Partner evidence verified.',
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->postWithCsrf(route('budget.me.performance-reports.review', $memberReport), [
                'review_action' => MePerformanceReport::STATUS_APPROVED,
                'review_notes' => 'Partner report approved.',
            ])->assertRedirect()->assertSessionHasNoErrors();
            $this->postWithCsrf(route('budget.me.performance-reports.archive', $memberReport), [
                'archive_notes' => 'Partner report retained as a historical record.',
            ])->assertRedirect()->assertSessionHasNoErrors();
            $memberReport->refresh();
            $this->assertSame(
                MePerformanceReport::STATUS_ARCHIVED,
                $memberReport->status,
                'The partner report did not complete the four-stage lifecycle.'
            );
            $this->assertTrue(
                $memberReport->transitions()->count() >= 4,
                'The report lifecycle audit trail is incomplete.'
            );

            $this->get(route('budget.me.rebuild.reporting-dashboard', [
                'reporting_year' => 2026,
                'reporting_period_type' => 'quarter',
                'reporting_period_label' => $context['quarter'],
                'component_id' => $context['component']->id,
                'results_level' => 'pdo',
                'indicator_id' => $context['indicator']->id,
                'thematic_area_id' => $context['portfolio']->id,
            ]))
                ->assertOk()
                ->assertSee('Reporting operations dashboard')
                ->assertSee('Workflow distribution')
                ->assertSee('Submission timeliness')
                ->assertSee('Indicator completeness')
                ->assertSee('Reports by think tank or partner')
                ->assertSee('Management attention queue')
                ->assertSee('Export filtered CSV')
                ->assertSee($context['form']->title)
                ->assertSee('View report');

            $dashboardCsv = $this->get(route('budget.me.rebuild.reporting-dashboard.csv', [
                'reporting_year' => 2026,
                'reporting_period_type' => 'quarter',
                'reporting_period_label' => $context['quarter'],
                'q' => $context['form']->title,
            ]));
            $dashboardCsv
                ->assertOk()
                ->assertStreamed()
                ->assertHeader('content-type', 'text/csv; charset=UTF-8');
            $this->assertTrue(
                str_contains($dashboardCsv->streamedContent(), $context['form']->title),
                'The filtered dashboard CSV omitted the matching report.'
            );

            $this->get(route('budget.me.rebuild.reporting-dashboard', [
                'reporting_year' => 2026,
                'think_tank_id' => $context['member']->id,
                'status' => 'archived',
                'drilldown' => 'stage_archived',
            ]))
                ->assertOk()
                ->assertSee('Archived report records')
                ->assertSee($context['member']->name)
                ->assertSee($context['form']->title)
                ->assertSee('Permission-scoped drill-down');

            $this->get(route('budget.me.rebuild.management-dashboard', [
                'reporting_year' => 2026,
                'portfolio_id' => $context['portfolio']->id,
            ]))
                ->assertOk()
                ->assertSee('Management dashboard')
                ->assertSee('Official performance is approval-controlled')
                ->assertSee('Portfolio readiness')
                ->assertSee('Management action queue')
                ->assertSee('Report lifecycle')
                ->assertSee('Official performance rating')
                ->assertSee('Reporting organization coverage')
                ->assertSee('Reporting window health')
                ->assertSee('Recent official decisions')
                ->assertSee($context['portfolio']->name)
                ->assertSee($context['member']->name)
                ->assertSee($context['form']->title);

            $this->get(route('budget.me.consolidated-reports.index', [
                'reporting_year' => 2026,
                'reporting_period_type' => 'quarter',
                'reporting_period_label' => $context['quarter'],
                'portfolio_id' => $context['portfolio']->id,
                'project_component_id' => $context['component']->id,
            ]))
                ->assertOk()
                ->assertSee('Think Tank M&amp;E Reports', false)
                ->assertSee('Only projects belonging to the selected portfolio are shown.')
                ->assertSee('Reporting coverage')
                ->assertSee('Consolidation quality controls')
                ->assertSee('Approved consolidated indicator performance')
                ->assertSee($context['member']->name)
                ->assertSee($context['component']->name)
                ->assertSee($context['indicator']->name);
            $consolidatedFilters = [
                'reporting_year' => 2026,
                'reporting_period_type' => 'quarter',
                'reporting_period_label' => $context['quarter'],
                'portfolio_id' => $context['portfolio']->id,
                'project_component_id' => $context['component']->id,
            ];
            $consolidatedProjectToken = Str::upper(Str::slug(
                (string) ($context['component']->project_id ?: $context['component']->name)
            ));
            $this->get(route('budget.me.consolidated-reports.excel', $consolidatedFilters))
                ->assertOk()
                ->assertDownload('ATTP-Consolidated-MEL-2026-'.$context['quarter'].'-'.$consolidatedProjectToken.'.xlsx');
            $this->get(route('budget.me.consolidated-reports.pdf', $consolidatedFilters))
                ->assertOk()
                ->assertDownload('ATTP-Consolidated-MEL-2026-'.$context['quarter'].'-'.$consolidatedProjectToken.'.pdf')
                ->assertHeader('content-type', 'application/pdf');

            $engineFilters = [
                'project_year' => 1,
                'reporting_year' => 2026,
                'component_id' => $context['component']->id,
                'indicator_id' => $context['indicator']->id,
            ];
            $this->get(route('budget.me.consolidation-engine.index', [
                ...$engineFilters,
                'level' => 'indicator',
            ]))
                ->assertOk()
                ->assertSee('Consolidations Engine')
                ->assertSee('Official approved-only consolidation is active')
                ->assertSee($context['indicator']->name)
                ->assertSee($context['member']->name);
            $this->get(route('budget.me.consolidation-engine.index', [
                ...$engineFilters,
                'level' => 'project',
            ]))
                ->assertOk()
                ->assertSee('Project-level consolidation')
                ->assertSee($context['component']->name);
            $this->get(route('budget.me.consolidation-engine.excel', [
                ...$engineFilters,
                'level' => 'indicator',
            ]))
                ->assertOk()
                ->assertDownload('ATTP-complete-Consolidation-2026.xlsx')
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $indicatorCsv = $this->get(route('budget.me.consolidation-engine.csv', [
                ...$engineFilters,
                'level' => 'indicator',
            ]));
            $indicatorCsv
                ->assertOk()
                ->assertStreamed()
                ->assertDownload('ATTP-indicator-Consolidation-2026.csv')
                ->assertHeader('content-type', 'text/csv; charset=UTF-8');
            $this->assertTrue(
                str_contains($indicatorCsv->streamedContent(), $context['indicator']->name),
                'The indicator-level CSV omitted the approved indicator consolidation row.'
            );
            $this->get(route('budget.me.consolidation-engine.csv', [
                ...$engineFilters,
                'level' => 'project',
            ]))
                ->assertOk()
                ->assertDownload('ATTP-project-Consolidation-2026.csv');
            $this->get(route('budget.me.consolidation-engine.pdf', [
                ...$engineFilters,
                'level' => 'indicator',
            ]))
                ->assertOk()
                ->assertDownload('ATTP-indicator-Consolidation-2026.pdf')
                ->assertHeader('content-type', 'application/pdf');

            $indicatorReportFilters = [
                'project_year' => 1,
                'reporting_year' => 2026,
                'component_id' => $context['component']->id,
                'indicator_id' => $context['indicator']->id,
            ];
            $individualReportFilters = [
                ...$indicatorReportFilters,
                'mode' => 'individual',
            ];
            $individualReportStem = 'ATTP-Indicator-Report-'
                .Str::slug((string) $context['indicator']->indicator_code).'-2026';

            $this->get(route('budget.me.indicator-reports.index', $individualReportFilters))
                ->assertOk()
                ->assertSee('Indicator Report')
                ->assertSee('Individual indicator report')
                ->assertSee($context['indicator']->name)
                ->assertSee($context['member']->name);
            $this->get(route('budget.me.indicator-reports.excel', $individualReportFilters))
                ->assertOk()
                ->assertDownload($individualReportStem.'.xlsx')
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $individualIndicatorCsv = $this->get(route('budget.me.indicator-reports.csv', $individualReportFilters));
            $individualIndicatorCsv
                ->assertOk()
                ->assertStreamed()
                ->assertDownload($individualReportStem.'.csv')
                ->assertHeader('content-type', 'text/csv; charset=UTF-8');
            $this->assertTrue(
                str_contains($individualIndicatorCsv->streamedContent(), $context['indicator']->name),
                'The individual indicator-report CSV omitted its approved contribution.'
            );
            $this->get(route('budget.me.indicator-reports.pdf', $individualReportFilters))
                ->assertOk()
                ->assertDownload($individualReportStem.'.pdf')
                ->assertHeader('content-type', 'application/pdf');

            $consolidatedIndicatorFilters = [
                ...$indicatorReportFilters,
                'mode' => 'consolidated',
            ];
            $consolidatedReportStem = 'ATTP-Consolidated-Indicator-Report-2026';
            $this->get(route('budget.me.indicator-reports.index', $consolidatedIndicatorFilters))
                ->assertOk()
                ->assertSee('Indicator Report')
                ->assertSee('Consolidated indicator report')
                ->assertSee($context['indicator']->name)
                ->assertSee($context['component']->name);
            $this->get(route('budget.me.indicator-reports.excel', $consolidatedIndicatorFilters))
                ->assertOk()
                ->assertDownload($consolidatedReportStem.'.xlsx')
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $consolidatedIndicatorCsv = $this->get(route('budget.me.indicator-reports.csv', $consolidatedIndicatorFilters));
            $consolidatedIndicatorCsv
                ->assertOk()
                ->assertStreamed()
                ->assertDownload($consolidatedReportStem.'.csv')
                ->assertHeader('content-type', 'text/csv; charset=UTF-8');
            $this->assertTrue(
                str_contains($consolidatedIndicatorCsv->streamedContent(), $context['indicator']->name),
                'The consolidated indicator-report CSV omitted its approved indicator row.'
            );
            $this->get(route('budget.me.indicator-reports.pdf', $consolidatedIndicatorFilters))
                ->assertOk()
                ->assertDownload($consolidatedReportStem.'.pdf')
                ->assertHeader('content-type', 'application/pdf');

            $this->get(route('budget.me.consolidated-reports.index', [
                'reporting_year' => 2026,
                'reporting_period_type' => 'semi_annual',
                'reporting_period_label' => 'Q1',
            ]))
                ->assertOk()
                ->assertSee('H1 (January');

            echo "ME_PERFORMANCE_REPORTING_OK\n";
        } finally {
            DB::rollBack();
            $this->app['auth']->forgetGuards();
        }
    }

    private function prepareContext(): array
    {
        $adminRole = Role::query()->where('name', 'System Admin')->first();
        $this->assertTrue((bool) $adminRole, 'The System Admin role is missing.');
        $admin = User::query()->where('role_id', $adminRole->id)->orderBy('created_at')->first();
        $this->assertTrue((bool) $admin, 'An existing System Admin user is required.');

        $member = ConsortiumThinkTank::query()
            ->with('portalUser')
            ->where('status', 'active')
            ->whereNotNull('portal_user_id')
            ->whereHas('portalUser')
            ->orderBy('created_at')
            ->first();
        $this->assertTrue((bool) $member?->portalUser, 'An active think tank portal user is required.');
        $portalUser = $member->portalUser;
        $portalUser->forceFill([
            'user_type' => 'think_tank',
            'think_tank_member_id' => $member->id,
            'think_tank_access_level' => User::THINK_TANK_ACCESS_ME,
            'is_disabled' => false,
            'disabled_at' => null,
            'disabled_until' => null,
            'is_blacklisted' => false,
            'blacklisted_at' => null,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        $component = Project::query()
            ->with('program.sector')
            ->whereNotNull('governance_node_id')
            ->whereHas('program.sector')
            ->orderBy('name')
            ->first();
        $this->assertTrue((bool) $component, 'A project component with a responsible Directorate is required.');
        $portfolio = $component->program->sector;
        $framework = MeFramework::query()->current()->first();
        $this->assertTrue((bool) $framework, 'An active current M&E framework is required.');

        $frequency = ReportingFrequency::query()
            ->indicatorCadences()
            ->orderBy('sort_order')
            ->first();
        if (! $frequency) {
            $frequency = ReportingFrequency::query()->create([
                'portfolio_id' => $portfolio->id,
                'name' => 'Quarterly smoke '.Str::upper(Str::random(8)),
                'code' => 'QUARTERLY-SMOKE-'.Str::upper(Str::random(8)),
                'interval_unit' => 'quarterly',
                'interval_value' => 1,
                'frequency_in_days' => 90,
                'is_active' => true,
                'created_by' => $admin->id,
            ]);
        }

        $indicator = Indicator::query()->create([
            'indicatorable_type' => Sector::class,
            'indicatorable_id' => $portfolio->id,
            'project_component_id' => $component->id,
            'framework_id' => $framework->id,
            'name' => 'Quarterly reporting smoke indicator '.Str::upper(Str::random(8)),
            'results_level' => 'pdo',
            'value_type' => 'number',
            'aggregation_method' => 'sum',
            'organization_rollup_method' => 'sum',
            'reporting_source' => 'both',
            'is_cumulative' => false,
            'requires_evidence' => true,
            'is_active' => true,
            'baseline_type' => 'year',
            'baseline_value' => 0,
            'frequency_of_reporting_id' => $frequency->id,
            'data_collection_method' => 'Administrative source records',
            'definitions' => 'Temporary indicator for the performance-reporting acceptance test.',
            'responsible_user_id' => $admin->id,
            'responsible_party' => json_encode([$admin->id], JSON_THROW_ON_ERROR),
            'created_by' => $admin->id,
        ]);
        IndicatorTarget::query()->create([
            'indicator_id' => $indicator->id,
            'framework_id' => $framework->id,
            'target_context' => Indicator::SETUP_TARGET_CONTEXT,
            'period_type' => 'year',
            'period_label' => 'Approved target',
            'target_value' => 125,
            'project_year' => 1,
            'target_scope' => 'project',
            'approval_status' => 'approved',
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $form = MeDataEntryForm::query()->create([
            'portfolio_id' => $portfolio->id,
            'project_component_id' => $component->id,
            'indicator_id' => $indicator->id,
            'title' => 'Quarterly Performance Report '.Str::upper(Str::random(8)),
            'responsible_user_id' => $admin->id,
            'status' => MeDataEntryForm::STATUS_PUBLISHED,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $form->indicators()->attach($indicator->id, [
            'id' => (string) Str::uuid(),
            'is_primary' => true,
            'sort_order' => 10,
        ]);

        $indicator->load('frequency');
        $quarter = match ($indicator->frequency?->indicatorCadenceKey()) {
            'semi_annual' => 'Q2',
            'annual' => 'Q4',
            default => 'Q1',
        };
        $this->assertTrue(
            IndicatorReportingSchedule::isDueInQuarter($indicator, $quarter),
            'The selected smoke-test quarter is not due under the approved cadence.'
        );

        return compact('admin', 'member', 'portalUser', 'portfolio', 'component', 'frequency', 'indicator', 'form', 'quarter');
    }

    private function authenticate(User $user): void
    {
        $user->forceFill([
            'is_disabled' => false,
            'disabled_at' => null,
            'disabled_until' => null,
            'is_blacklisted' => false,
            'blacklisted_at' => null,
        ])->save();

        $this->actingAs($user)->withSession([
            'otp_verified' => true,
            'otp_verified_user_id' => (string) $user->id,
            'otp_verified_at' => now()->toIso8601String(),
        ]);
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

(new MePerformanceReportingSmoke($app))->run();
