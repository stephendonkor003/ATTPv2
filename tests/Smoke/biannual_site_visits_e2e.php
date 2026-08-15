<?php

// This end-to-end script renders several large PDFs in one long-lived PHP process.
ini_set('memory_limit', '512M');

use App\Mail\BiAnnualSiteVisitCreatedMail;
use App\Mail\UserAccountCreated;
use App\Models\BiAnnualSiteVisitProfile;
use App\Models\BiAnnualSiteVisitQuestion;
use App\Models\BiAnnualSiteVisitTemplate;
use App\Models\ConsortiumThinkTank;
use App\Models\Role;
use App\Models\Sector;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\BiAnnualSiteVisitBrandingService;
use App\Services\BiAnnualSiteVisitTemplateService;
use App\Support\BiannualQuestionnaire;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithExceptionHandling;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

(new PHPUnit\TextUI\Configuration\Builder)->build(['phpunit']);

class BiAnnualSiteVisitsSmoke
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
        $this->assertSchema();
        Mail::fake();

        DB::beginTransaction();

        try {
            $admin = $this->admin();
            $thinkTank = ConsortiumThinkTank::query()
                ->where('status', 'active')
                ->whereHas('consortium.programFunding.program.sector')
                ->orderBy('created_at')
                ->first();
            $template = BiAnnualSiteVisitTemplate::query()
                ->published()
                ->withStructure()
                ->orderByDesc('is_default')
                ->first();
            $questionnaireDefinition = require database_path('data/biannual_monitoring_questionnaire.php');
            $expectedQuestionCount = (int) data_get($questionnaireDefinition, 'counts.questions');

            $this->assertTrue((bool) $thinkTank, 'An active Think Tank is required.');
            $this->assertTrue((bool) $template, 'The default Bi-Annual questionnaire was not seeded.');
            $this->assertSame(7, $template->sections->count(), 'Default section count is incorrect.');
            $this->assertSame(
                $expectedQuestionCount,
                $template->questions()->count(),
                'Default question count is incorrect.'
            );
            $customTemplate = $this->assertCustomBuilderRoundTrip($admin);
            $this->asUser($admin);
            $this->postWithCsrf(
                route('biannual-site-visits.templates.publish', $customTemplate)
            )->assertRedirect();
            $customTemplate->refresh();
            $this->assertSame(
                BiAnnualSiteVisitTemplate::STATUS_PUBLISHED,
                $customTemplate->status,
                'The customizable questionnaire could not be published.'
            );

            $publishedUpdatedAt = $customTemplate->updated_at?->toIso8601String();
            $publishedSectionCount = $customTemplate->sections()->count();
            $publishedQuestionCount = $customTemplate->questions()->count();
            $familyCountBeforeEdit = BiAnnualSiteVisitTemplate::query()
                ->where('code', $customTemplate->code)
                ->count();

            $this->postWithCsrf(
                route('biannual-site-visits.templates.editable-draft', $customTemplate)
            )->assertRedirect();

            $editableTemplate = BiAnnualSiteVisitTemplate::query()
                ->where('code', $customTemplate->code)
                ->draft()
                ->orderByDesc('version')
                ->firstOrFail();
            $this->assertSame(
                $customTemplate->version + 1,
                $editableTemplate->version,
                'Editing a published questionnaire did not create the next draft version.'
            );
            $this->assertSame(
                $publishedSectionCount,
                $editableTemplate->sections()->count(),
                'The editable version did not retain every section.'
            );
            $this->assertSame(
                $publishedQuestionCount,
                $editableTemplate->questions()->count(),
                'The editable version did not retain every question.'
            );
            $this->assertSame(
                'derived_template',
                data_get($editableTemplate->settings, 'source.type'),
                'The editable version retained misleading original-source provenance.'
            );
            $this->assertSame(
                (string) $customTemplate->id,
                data_get($editableTemplate->settings, 'source.derived_from_template_id'),
                'The editable version did not record its source template.'
            );

            $customTemplate->refresh();
            $this->assertSame(
                BiAnnualSiteVisitTemplate::STATUS_PUBLISHED,
                $customTemplate->status,
                'Creating an editable version changed the published source status.'
            );
            $this->assertSame(
                $publishedUpdatedAt,
                $customTemplate->updated_at?->toIso8601String(),
                'Creating an editable version mutated the published source.'
            );

            $this->postWithCsrf(
                route('biannual-site-visits.templates.editable-draft', $customTemplate)
            )->assertRedirect(route('biannual-site-visits.templates.edit', $editableTemplate));
            $this->postWithCsrf(
                route('biannual-site-visits.templates.editable-draft', $editableTemplate)
            )->assertRedirect(route('biannual-site-visits.templates.edit', $editableTemplate));
            $this->assertSame(
                $familyCountBeforeEdit + 1,
                BiAnnualSiteVisitTemplate::query()->where('code', $customTemplate->code)->count(),
                'Repeated Edit clicks created duplicate draft versions.'
            );

            $monitoringRole = Role::query()
                ->where('name', 'Monitoring and Evaluation Manager')
                ->firstOrFail();
            $team = collect(range(1, 6))->map(fn (int $position) => User::create([
                'name' => "Bi-Annual Smoke Member {$position}",
                'email' => 'biannual-smoke-'.$position.'-'.Str::lower(Str::random(6)).'@example.test',
                'password' => Hash::make('Password123!'),
                'user_type' => null,
                'role_id' => $monitoringRole->id,
                'must_change_password' => false,
                'is_disabled' => false,
            ]));
            $ordinaryStaff = User::create([
                'name' => 'Bi-Annual Active Staff Without Permissions',
                'email' => 'biannual-active-staff-'.Str::lower(Str::random(6)).'@example.test',
                'password' => Hash::make('Password123!'),
                'user_type' => 'staff',
                'role_id' => null,
                'must_change_password' => false,
                'is_disabled' => false,
                'is_blacklisted' => false,
            ]);
            $disabledStaff = User::create([
                'name' => 'Bi-Annual Disabled Staff',
                'email' => 'biannual-disabled-staff-'.Str::lower(Str::random(6)).'@example.test',
                'password' => Hash::make('Password123!'),
                'user_type' => 'staff',
                'role_id' => null,
                'must_change_password' => false,
                'is_disabled' => true,
                'is_blacklisted' => false,
            ]);
            $leader = $team->first();
            $visitTeam = $team->take(2)->push($ordinaryStaff)->values();
            $this->assertSameScoreRatingRoundTrip(
                $admin,
                $leader,
                $team->pluck('id')->all(),
                $thinkTank,
                $customTemplate
            );

            $this->asUser($admin)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Procurement Site Visits')
                ->assertSee('Bi-Annual Site Visits')
                ->assertSee('Questionnaire Builder');

            $this->asUser($admin)
                ->get(route('biannual-site-visits.index'))
                ->assertOk()
                ->assertSee('Bi-Annual Site Visits')
                ->assertSee('Questionnaire Builder')
                ->assertSee('Default questionnaire ready')
                ->assertSee($template->name)
                ->assertSee(number_format($expectedQuestionCount).' questions')
                ->assertSee(route('biannual-site-visits.templates.preview', $template), false)
                ->assertSee('Questionnaire templates')
                ->assertSee('Edit &amp; update', false)
                ->assertSee('Edit as new version')
                ->assertSee('id="add-team-members-modal"', false)
                ->assertSee('id="manage-team-modal"', false)
                ->assertSee('Add selected members')
                ->assertSee('Save team changes')
                ->assertSee($ordinaryStaff->email)
                ->assertSee('assets/images/attp-logo.jpeg')
                ->assertDontSee('Operations & Oversight');

            $this->asUser($admin)
                ->get(route('biannual-site-visits.create'))
                ->assertOk()
                ->assertSee('Monitoring team')
                ->assertSee('0 members selected')
                ->assertDontSee('At least five are required')
                ->assertSee('id="add-team-member"', false)
                ->assertSee('data-remove-team-member', false)
                ->assertSee('id="team-member-row-template"', false)
                ->assertSee('id="preview-questionnaire"', false)
                ->assertSee('id="download-questionnaire-pdf"', false)
                ->assertSee('id="show-new-staff-form"', false)
                ->assertSee('list="biannual-specialist-role-options"', false)
                ->assertSee('choose an existing specialist role or type a new one', false)
                ->assertSee($ordinaryStaff->email)
                ->assertDontSee($disabledStaff->email)
                ->assertSee('Project Coordinator')
                ->assertSee('World Bank Representative')
                ->assertSee($template->name);

            $portfolioSnapshot = app(BiAnnualSiteVisitBrandingService::class)
                ->portfolioSnapshot($thinkTank);
            $portfolioName = $portfolioSnapshot['name'] ?? null;
            $this->assertTrue(
                filled($portfolioName),
                'The selected Think Tank could not resolve a portfolio name.'
            );

            $portfolioManagerRole = Role::query()
                ->where('name', 'Portfolio Manager')
                ->firstOrFail();
            $portfolioManager = User::create([
                'name' => 'Bi-Annual Scoped Portfolio Manager',
                'email' => 'biannual-portfolio-'.Str::lower(Str::random(6)).'@example.test',
                'password' => Hash::make('Password123!'),
                'user_type' => null,
                'role_id' => $portfolioManagerRole->id,
                'must_change_password' => false,
                'is_disabled' => false,
            ]);
            Sector::query()
                ->whereKey($portfolioSnapshot['id'])
                ->update([
                    'portfolio_manager_user_id' => $portfolioManager->id,
                    'portfolio_manager_email' => $portfolioManager->email,
                ]);
            $portfolioManager->unsetRelations();

            $this->asUser($portfolioManager)
                ->get(route('biannual-site-visits.create'))
                ->assertOk()
                ->assertSee($thinkTank->name)
                ->assertSee($ordinaryStaff->email);
            $this->get(route('biannual-site-visits.templates.preview', [
                'template' => $template,
                'think_tank_member_id' => $thinkTank->id,
            ]))
                ->assertOk()
                ->assertSee($portfolioName);

            $this->asUser($admin);
            $this->get(route('biannual-site-visits.templates.preview', [
                'template' => $template,
                'think_tank_member_id' => $thinkTank->id,
            ]))
                ->assertOk()
                ->assertSee($template->name)
                ->assertSee($portfolioName);

            $this->get(route('biannual-site-visits.templates.preview.pdf', [
                'template' => $template,
                'think_tank_member_id' => $thinkTank->id,
            ]))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');

            $this->asUser($admin)
                ->get(route('biannual-site-visits.templates.index'))
                ->assertOk()
                ->assertSee('Questionnaire Templates')
                ->assertSee($expectedQuestionCount.' questions')
                ->assertSee('id="template-preview-modal"', false)
                ->assertSee('data-template-preview', false)
                ->assertSee('Download PDF')
                ->assertSee('assets/images/attp-logo.jpeg');

            $this->get(route('biannual-site-visits.templates.preview', [
                'template' => $template,
                'embed' => 1,
            ]))
                ->assertOk()
                ->assertSee('reusable ATTP questionnaire template')
                ->assertSee('Not assigned — template library');

            $this->get(route('biannual-site-visits.templates.preview.pdf', $template))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');

            $this->assertTrue(
                str_starts_with(
                    (string) app(BiAnnualSiteVisitBrandingService::class)->logoDataUri(),
                    'data:image/jpeg;base64,'
                ),
                'The Bi-Annual documents are not using the supplied ATTP logo.'
            );

            $payload = [
                'think_tank_member_id' => $thinkTank->id,
                'template_id' => $template->id,
                'cycle_year' => 2098,
                'cycle_half' => 'H1',
                'title' => 'Bi-Annual E2E Monitoring '.Str::upper(Str::random(5)),
                'location' => 'Smoke Test Office',
                'starts_on' => '2098-03-10',
                'ends_on' => '2098-03-12',
                'objectives' => 'Verify the complete configurable monitoring workflow.',
                'group_name' => 'Flexible Smoke Monitoring Team',
                'team_members' => $visitTeam->pluck('id')->all(),
                'team_specialisms' => [
                    'Safeguards & Inclusion Specialist',
                    'Senior Procurement Advisor',
                    'Finance Management Specialist',
                ],
                'group_leader_id' => $leader->id,
            ];

            $invalid = $payload;
            $invalid['team_members'] = [];
            $invalid['team_specialisms'] = [];

            $this->postWithCsrf(route('biannual-site-visits.store'), $invalid)
                ->assertSessionHasErrors(['team_members']);
            $this->assertSame(
                0,
                BiAnnualSiteVisitProfile::query()->where('cycle_year', 2098)->count(),
                'A visit without a monitoring-team member was created.'
            );
            $this->get(route('biannual-site-visits.create'))
                ->assertOk()
                ->assertSee('0 members selected');

            $duplicate = $payload;
            $duplicate['team_members'][2] = $duplicate['team_members'][0];
            $this->postWithCsrf(route('biannual-site-visits.store'), $duplicate)
                ->assertSessionHasErrors(['team_members.2']);

            $this->postWithCsrf(route('biannual-site-visits.store'), $payload)
                ->assertRedirect();

            $visit = BiAnnualSiteVisitProfile::query()
                ->with(['siteVisit.group.members', 'answers'])
                ->where('title', $payload['title'])
                ->first();

            $this->assertTrue((bool) $visit, 'The valid Bi-Annual Site Visit was not created.');
            $this->assertSame(
                $visitTeam->count(),
                $visit->siteVisit->group->members->count(),
                'The flexible visit team was not saved completely.'
            );
            $this->assertSame(
                'Safeguards & Inclusion Specialist',
                data_get($visit->settings, 'team_specialisms.'.$leader->id),
                'A newly entered specialist role was not stored.'
            );
            $this->get(route('biannual-site-visits.create'))
                ->assertOk()
                ->assertSee('Safeguards &amp; Inclusion Specialist', false);
            $this->assertSame('draft', $visit->siteVisit->status, 'New visit did not start as a draft.');
            $this->assertSame(
                $portfolioName,
                data_get($visit->settings, 'portfolio.name'),
                'The visit did not snapshot the portfolio used for its watermark.'
            );
            $ordinaryStaff->unsetRelations();
            $this->assertTrue(
                $ordinaryStaff->can('biannual_site_visits.respond'),
                'Selecting an active staff member did not grant questionnaire response access.'
            );
            $this->assertSame(
                $expectedQuestionCount,
                collect($visit->questionnaire_snapshot['sections'])
                    ->sum(fn (array $section) => collect($section['topics'])
                        ->sum(fn (array $topic) => count($topic['questions']))),
                'The visit snapshot does not contain every imported question.'
            );

            $otherPortfolio = Sector::query()
                ->where('id', '!=', $portfolioSnapshot['id'])
                ->orderBy('name')
                ->firstOrFail();
            $otherPortfolioManager = User::create([
                'name' => 'Bi-Annual Other Portfolio Manager',
                'email' => 'biannual-other-portfolio-'.Str::lower(Str::random(6)).'@example.test',
                'password' => Hash::make('Password123!'),
                'user_type' => null,
                'role_id' => $portfolioManagerRole->id,
                'must_change_password' => false,
                'is_disabled' => false,
            ]);
            $otherPortfolio->update([
                'portfolio_manager_user_id' => $otherPortfolioManager->id,
                'portfolio_manager_email' => $otherPortfolioManager->email,
            ]);
            $programId = $thinkTank->fresh()
                ->consortium
                ?->programFunding
                ?->program_id;
            $this->assertTrue(filled($programId), 'The linked Think Tank programme is missing.');
            DB::table('myb_programs')
                ->where('id', $programId)
                ->update(['sector_id' => $otherPortfolio->id]);

            $this->asUser($portfolioManager)
                ->get(route('biannual-site-visits.show', $visit))
                ->assertOk();
            $this->asUser($otherPortfolioManager)
                ->get(route('biannual-site-visits.show', $visit))
                ->assertForbidden();
            $this->asUser($admin);

            $this->asUser($admin)
                ->postWithCsrf(route('site-visits.assign.group', $visit->siteVisit), [
                    'group_name' => 'Invalid replacement group',
                    'leader_id' => $leader->id,
                    'members' => $visitTeam->pluck('id')->all(),
                ])
                ->assertStatus(422);
            $this->assertSame(
                1,
                DB::table('site_visit_groups')
                    ->where('site_visit_id', $visit->site_visit_id)
                    ->count(),
                'The legacy group endpoint replaced the Bi-Annual monitoring team.'
            );

            $respondPermissionId = DB::table('permissions')
                ->where('name', 'biannual_site_visits.respond')
                ->value('id');
            $restrictedMember = $visitTeam->last();
            $restrictedMember->update(['role_id' => null]);
            $restrictedMember->permissions()->attach($respondPermissionId);
            $restrictedMember->unsetRelations();
            $this->asUser($restrictedMember)
                ->get(route('biannual-site-visits.show', $visit))
                ->assertOk();
            $this->get(route('biannual-site-visits.templates.preview', [
                'template' => $template,
                'think_tank_member_id' => $thinkTank->id,
            ]))
                ->assertForbidden();
            $this->get(route('biannual-site-visits.reports.submitted'))
                ->assertForbidden();
            $this->get(route('biannual-site-visits.pdf', $visit))
                ->assertForbidden();
            $restrictedMember->permissions()->detach($respondPermissionId);
            $restrictedMember->update(['role_id' => $monitoringRole->id]);
            $restrictedMember->unsetRelations();

            $this->asUser($leader)
                ->get(route('biannual-site-visits.show', $visit))
                ->assertOk()
                ->assertSee('Consortium Governance')
                ->assertSee('Overall Assessment')
                ->assertSee('Collaborative draft');

            $this->get(route('biannual-site-visits.pdf', $visit))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');

            $answers = BiAnnualSiteVisitQuestion::query()
                ->where('template_id', $template->id)
                ->orderBy('sort_order')
                ->get()
                ->mapWithKeys(fn (BiAnnualSiteVisitQuestion $question): array => [
                    $question->question_key => [
                        'score' => 3,
                        'strength' => 'Verified strength for '.$question->question_key,
                        'weakness' => '',
                        'evidence_notes' => 'Reviewed during the monitoring visit.',
                        'not_applicable_reason' => '',
                    ],
                ])
                ->all();

            $this->asUser($leader);
            $this->putWithCsrf(route('biannual-site-visits.answers.update', $visit), [
                'answers' => $answers,
            ])->assertRedirect();

            $visit->refresh()->load('answers');
            $this->assertSame(
                $expectedQuestionCount,
                $visit->answers->count(),
                'Not all questionnaire responses were persisted.'
            );
            $this->assertSame(
                0,
                $visit->answers->whereNull('question_id')->count(),
                'One or more saved answers lost their source question link.'
            );
            $this->assertSame(100.0, (float) $visit->completion_percentage, 'Completion did not reach 100%.');
            $this->assertSame(
                100.0,
                (float) $visit->score_percentage,
                'The canonical weighted score was not persisted for reporting.'
            );

            $leader->update(['role_id' => null]);
            $leader->unsetRelations();
            $this->assertTrue(
                $leader->can('biannual_site_visits.submit'),
                'The selected team lead lost submission access when their role changed.'
            );
            $this->asUser($leader)
                ->postWithCsrf(route('biannual-site-visits.submit', $visit))
                ->assertRedirect();
            $this->assertSame('submitted', $visit->siteVisit->fresh()->status, 'Team lead submission failed.');
            $leader->update(['role_id' => $monitoringRole->id]);
            $leader->unsetRelations();

            $this->asUser($admin);
            $this->postWithCsrf(route('biannual-site-visits.review', $visit), [
                'status' => 'approved',
                'remarks' => 'E2E assessment approved.',
            ])->assertRedirect();

            $visit->refresh();
            $this->assertSame('approved', $visit->siteVisit->fresh()->status, 'Review approval failed.');
            $this->assertTrue((bool) $visit->reviewed_at, 'Review timestamp was not recorded.');
            $this->assertTrue($visit->siteVisit->approvals()->where('status', 'approved')->exists(), 'Approval history is missing.');

            $this->get(route('biannual-site-visits.show', $visit))
                ->assertOk()
                ->assertSee('Approved')
                ->assertDontSee('Save draft');

            $this->get(route('biannual-site-visits.pdf', $visit))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');

            $this->get(route('biannual-site-visits.reports.submitted', [
                'portfolio_id' => data_get($visit->settings, 'portfolio.id'),
                'status' => 'approved',
            ]))
                ->assertOk()
                ->assertSee('Submitted Site Visit Reports')
                ->assertSee($visit->reference_number)
                ->assertSee($portfolioName)
                ->assertSee('Approved');

            $this->get(route('biannual-site-visits.reports.submitted', [
                'portfolio_id' => $otherPortfolio->id,
                'status' => 'approved',
            ]))
                ->assertOk()
                ->assertDontSee($visit->reference_number);

            $this->get(route('biannual-site-visits.reports.submitted.pdf', [
                'portfolio_id' => data_get($visit->settings, 'portfolio.id'),
                'status' => 'approved',
            ]))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');

            $legacySiteVisit = SiteVisit::create([
                'assignment_type' => 'group',
                'visit_type' => BiAnnualSiteVisitProfile::VISIT_TYPE,
                'visit_date' => '2099-03-10',
                'status' => 'approved',
                'created_by' => $admin->id,
                'assigned_by' => $admin->id,
            ]);
            $visit->update(['score_percentage' => 80]);
            $legacyVisit = $visit->replicate();
            $legacyVisit->fill([
                'site_visit_id' => $legacySiteVisit->id,
                'reference_number' => 'BASV-2099-H1-'.Str::upper(Str::random(5)),
                'cycle_year' => 2099,
                'score_percentage' => null,
            ]);
            $legacyVisit->save();
            $visit->load('answers');
            $visit->answers->each(function ($answer) use ($legacyVisit): void {
                $legacyAnswer = $answer->replicate();
                $legacyAnswer->profile_id = $legacyVisit->id;
                $legacyAnswer->save();
            });

            $this->get(route('biannual-site-visits.reports.submitted', [
                'q' => $visit->title,
                'portfolio_id' => data_get($visit->settings, 'portfolio.id'),
                'status' => 'approved',
            ]))
                ->assertOk()
                ->assertSee('90.0%');

            foreach ($visitTeam as $recipient) {
                Mail::assertQueued(
                    BiAnnualSiteVisitCreatedMail::class,
                    fn (BiAnnualSiteVisitCreatedMail $mail): bool => (string) $mail->visit->id === (string) $visit->id
                        && (string) $mail->recipient->id === (string) $recipient->id
                        && $mail->hasTo($recipient->email)
                        && $mail->portfolioName === $portfolioName
                        && $mail->queue === null
                );
            }
            $visitMail = Mail::queued(
                BiAnnualSiteVisitCreatedMail::class,
                fn (BiAnnualSiteVisitCreatedMail $mail): bool => (string) $mail->visit->id === (string) $visit->id
            );
            $this->assertSame(
                $visitTeam->count(),
                $visitMail->count(),
                'The visit should queue exactly one notification for every selected team member.'
            );
            $this->assertSame(
                $visitTeam->count(),
                $visitMail->pluck('recipient.id')->map('strval')->unique()->count(),
                'A team recipient received a duplicate assignment notification.'
            );

            $mailHtml = (new BiAnnualSiteVisitCreatedMail(
                $visit,
                $leader,
                true,
                $portfolioName
            ))->render();
            $this->assertTrue(
                str_contains($mailHtml, $portfolioName)
                    && str_contains($mailHtml, $visit->reference_number)
                    && str_contains($mailHtml, 'Team Leader'),
                'The assignment email is missing its portfolio, visit, or leader content.'
            );

            $additionalMembers = $team->slice(2, 2)->values();
            $this->asUser($admin)
                ->get(route('biannual-site-visits.index'))
                ->assertOk()
                ->assertSee($visit->reference_number)
                ->assertDontSee(
                    route('biannual-site-visits.team-members.store', $visit),
                    false
                )
                ->assertDontSee(
                    route('biannual-site-visits.team.update', $visit),
                    false
                )
                ->assertDontSee(route('biannual-site-visits.edit', $visit), false)
                ->assertDontSee(route('biannual-site-visits.deactivate', $visit), false);

            $this->postWithCsrf(
                route('biannual-site-visits.team-members.store', $visit),
                [
                    '_team_visit_id' => $visit->id,
                    'team_members' => $additionalMembers->pluck('id')->all(),
                    'team_specialisms' => [
                        $additionalMembers->get(0)->id => 'M&E Officer',
                        $additionalMembers->get(1)->id => 'Climate Resilience Specialist',
                    ],
                ]
            )
                ->assertStatus(422);

            $this->putWithCsrf(
                route('biannual-site-visits.team.update', $visit),
                [
                    '_team_manage_visit_id' => $visit->id,
                    'group_leader_id' => $leader->id,
                    'team_specialisms' => [
                        $leader->id => 'Changed after approval',
                    ],
                ]
            )->assertStatus(422);
            $this->get(route('biannual-site-visits.edit', $visit))->assertStatus(422);
            $this->patchWithCsrf(route('biannual-site-visits.deactivate', $visit), [
                'deactivation_reason' => 'Finalized records must remain immutable.',
            ])->assertStatus(422);

            $this->assertSame(
                $visitTeam->count(),
                $visit->siteVisit->group->members()->count(),
                'A finalized visit allowed its monitoring team to change.'
            );
            $this->assertTrue((bool) $visit->fresh()->is_active, 'A finalized visit was deactivated.');

            $newStaffEmail = 'biannual-inline-staff-'.Str::lower(Str::random(6)).'@example.test';
            $newStaffReference = 'new:smoke_inline_staff';
            $inlineTitle = 'Bi-Annual Inline Staff '.Str::upper(Str::random(5));
            $this->asUser($admin)
                ->postWithCsrf(route('biannual-site-visits.store'), [
                    'think_tank_member_id' => $thinkTank->id,
                    'template_id' => $template->id,
                    'cycle_year' => 2096,
                    'cycle_half' => 'H1',
                    'title' => $inlineTitle,
                    'location' => 'Inline Staff Test Office',
                    'starts_on' => '2096-03-10',
                    'ends_on' => '2096-03-11',
                    'group_name' => 'Inline Staff Monitoring Team',
                    'team_members' => [$leader->id, $newStaffReference],
                    'team_specialisms' => [
                        'Project Coordinator',
                        'World Bank Representative',
                    ],
                    'group_leader_id' => $newStaffReference,
                    'new_team_members' => [
                        'smoke_inline_staff' => [
                            'name' => 'Bi-Annual Inline Staff Member',
                            'email' => $newStaffEmail,
                        ],
                    ],
                ])
                ->assertRedirect();

            $inlineStaff = User::query()->where('email', $newStaffEmail)->firstOrFail();
            $inlineVisit = BiAnnualSiteVisitProfile::query()
                ->with('siteVisit.group.members')
                ->where('title', $inlineTitle)
                ->firstOrFail();
            $this->assertSame(
                (string) $inlineStaff->id,
                (string) $inlineVisit->siteVisit->group->leader_id,
                'The inline-created staff member was not saved as the selected team lead.'
            );
            $inlineStaff->unsetRelations();
            $this->assertTrue(
                $inlineStaff->must_change_password
                    && $inlineStaff->can('biannual_site_visits.respond')
                    && $inlineStaff->can('biannual_site_visits.submit'),
                'The inline-created staff account is missing its password or assignment permissions.'
            );
            Mail::assertQueued(
                UserAccountCreated::class,
                fn (UserAccountCreated $mail): bool => (string) $mail->user->id === (string) $inlineStaff->id
                    && $mail->hasTo($newStaffEmail)
                    && $mail->queue === null
            );
            Mail::assertQueued(
                BiAnnualSiteVisitCreatedMail::class,
                fn (BiAnnualSiteVisitCreatedMail $mail): bool => (string) $mail->visit->id === (string) $inlineVisit->id
                    && (string) $mail->recipient->id === (string) $inlineStaff->id
                    && $mail->isLeader
            );

            $firstQuestion = BiAnnualSiteVisitQuestion::query()
                ->where('template_id', $inlineVisit->template_id)
                ->orderBy('sort_order')
                ->firstOrFail();
            $this->asUser($admin)
                ->putWithCsrf(route('biannual-site-visits.answers.update', $inlineVisit), [
                    'answers' => [
                        $firstQuestion->question_key => [
                            'score' => 2,
                            'strength' => 'Draft response retained through lifecycle changes.',
                            'weakness' => '',
                            'evidence_notes' => 'Lifecycle smoke evidence.',
                            'not_applicable_reason' => '',
                        ],
                    ],
                ])
                ->assertRedirect();

            $inlineVisit->refresh()->load('siteVisit.group.members');
            $teamBeforeLifecycle = $inlineVisit->siteVisit->group->members
                ->pluck('user_id')
                ->map('strval')
                ->sort()
                ->values()
                ->all();
            $answerCountBeforeLifecycle = $inlineVisit->answers()->count();
            $this->assertSame(1, $answerCountBeforeLifecycle, 'The lifecycle draft response was not saved.');

            $updatedInlineTitle = $inlineTitle.' Updated';
            $updatedInlineGroup = 'Updated Inline Staff Monitoring Team';
            $this->putWithCsrf(route('biannual-site-visits.update', $inlineVisit), [
                'title' => $updatedInlineTitle,
                'location' => 'Updated Lifecycle Test Office',
                'starts_on' => '2096-03-12',
                'ends_on' => '2096-03-14',
                'objectives' => 'Verify reversible schedule lifecycle management.',
                'group_name' => $updatedInlineGroup,
            ])->assertRedirect(route('biannual-site-visits.show', $inlineVisit));

            $inlineVisit->refresh()->load('siteVisit.group');
            $this->assertSame($updatedInlineTitle, $inlineVisit->title, 'The draft schedule title was not updated.');
            $this->assertSame('2096-03-12', $inlineVisit->starts_on->toDateString(), 'The draft schedule start date was not updated.');
            $this->assertSame('2096-03-12', $inlineVisit->siteVisit->visit_date->toDateString(), 'The base site visit date did not follow the edited schedule.');
            $this->assertSame($updatedInlineGroup, $inlineVisit->siteVisit->group->group_name, 'The monitoring-team name was not updated.');
            $this->assertSame(
                $answerCountBeforeLifecycle,
                $inlineVisit->answers()->count(),
                'Editing the schedule removed questionnaire responses.'
            );
            $this->get(route('biannual-site-visits.edit', $inlineVisit))
                ->assertOk()
                ->assertSee($updatedInlineTitle)
                ->assertSee($updatedInlineGroup);

            $deactivationReason = 'Visit rescheduled outside the current monitoring plan.';
            $this->patchWithCsrf(route('biannual-site-visits.deactivate', $inlineVisit), [
                '_deactivate_visit_id' => $inlineVisit->id,
                'deactivation_reason' => $deactivationReason,
            ])->assertRedirect(route('biannual-site-visits.index', ['lifecycle' => 'inactive']));

            $inlineVisit->refresh()->load('siteVisit.group.members');
            $this->assertSame(false, (bool) $inlineVisit->is_active, 'The draft visit was not deactivated.');
            $this->assertSame('draft', $inlineVisit->siteVisit->status, 'Deactivation overwrote the workflow state.');
            $this->assertSame($deactivationReason, $inlineVisit->deactivation_reason, 'The deactivation reason was not stored.');
            $this->assertTrue((bool) $inlineVisit->deactivated_at, 'The deactivation timestamp was not stored.');
            $this->assertSame((string) $admin->id, (string) $inlineVisit->deactivated_by, 'The deactivation actor was not stored.');
            $this->assertSame(
                $answerCountBeforeLifecycle,
                $inlineVisit->answers()->count(),
                'Deactivation removed questionnaire responses.'
            );
            $this->assertSame(
                $teamBeforeLifecycle,
                $inlineVisit->siteVisit->group->members
                    ->pluck('user_id')
                    ->map('strval')
                    ->sort()
                    ->values()
                    ->all(),
                'Deactivation changed the monitoring team.'
            );

            $this->get(route('biannual-site-visits.show', $inlineVisit))
                ->assertOk()
                ->assertSee('This scheduled visit is inactive and read-only.')
                ->assertSee($deactivationReason)
                ->assertDontSee('Save draft');
            $this->get(route('biannual-site-visits.index'))
                ->assertOk()
                ->assertDontSee($inlineVisit->reference_number);
            $this->get(route('biannual-site-visits.index', ['lifecycle' => 'inactive']))
                ->assertOk()
                ->assertSee($inlineVisit->reference_number)
                ->assertSee(route('biannual-site-visits.reactivate', $inlineVisit), false);

            $this->putWithCsrf(route('biannual-site-visits.update', $inlineVisit), [
                'title' => 'Forbidden inactive edit',
                'location' => 'Inactive office',
                'starts_on' => '2096-03-12',
                'ends_on' => '2096-03-14',
                'objectives' => null,
                'group_name' => $updatedInlineGroup,
            ])->assertStatus(422);
            $this->putWithCsrf(route('biannual-site-visits.answers.update', $inlineVisit), [
                'answers' => [
                    $firstQuestion->question_key => ['score' => 3],
                ],
            ])->assertStatus(422);
            $this->postWithCsrf(route('biannual-site-visits.submit', $inlineVisit))
                ->assertStatus(422);
            $this->postWithCsrf(route('biannual-site-visits.team-members.store', $inlineVisit), [
                'team_members' => [$additionalMembers->first()->id],
                'team_specialisms' => [
                    $additionalMembers->first()->id => 'M&E Officer',
                ],
            ])->assertStatus(422);
            $this->putWithCsrf(route('biannual-site-visits.team.update', $inlineVisit), [
                'group_leader_id' => $inlineStaff->id,
                'team_specialisms' => [
                    $inlineStaff->id => 'World Bank Representative',
                ],
            ])->assertStatus(422);

            $inlineVisit->refresh();
            $this->assertSame($updatedInlineTitle, $inlineVisit->title, 'An inactive schedule accepted an update.');
            $this->assertSame(
                $answerCountBeforeLifecycle,
                $inlineVisit->answers()->count(),
                'An inactive questionnaire mutation changed stored responses.'
            );

            $this->patchWithCsrf(route('biannual-site-visits.reactivate', $inlineVisit))
                ->assertRedirect(route('biannual-site-visits.show', $inlineVisit));

            $inlineVisit->refresh()->load('siteVisit.group.members');
            $this->assertSame(true, (bool) $inlineVisit->is_active, 'The draft visit was not reactivated.');
            $this->assertSame('draft', $inlineVisit->siteVisit->status, 'Reactivation overwrote the workflow state.');
            $this->assertTrue((bool) $inlineVisit->reactivated_at, 'The reactivation timestamp was not stored.');
            $this->assertSame((string) $admin->id, (string) $inlineVisit->reactivated_by, 'The reactivation actor was not stored.');
            $this->assertSame(
                $answerCountBeforeLifecycle,
                $inlineVisit->answers()->count(),
                'Reactivation removed questionnaire responses.'
            );
            $this->assertSame(
                $teamBeforeLifecycle,
                $inlineVisit->siteVisit->group->members
                    ->pluck('user_id')
                    ->map('strval')
                    ->sort()
                    ->values()
                    ->all(),
                'Reactivation changed the monitoring team.'
            );
            $lifecycleActions = collect(data_get($inlineVisit->settings, 'lifecycle_history', []))
                ->pluck('action')
                ->all();
            foreach (['schedule_updated', 'deactivated', 'reactivated'] as $expectedAction) {
                $this->assertTrue(
                    in_array($expectedAction, $lifecycleActions, true),
                    "The {$expectedAction} lifecycle event was not recorded."
                );
            }
            $this->get(route('biannual-site-visits.edit', $inlineVisit))
                ->assertOk()
                ->assertSee($updatedInlineTitle);

            echo "BIANNUAL_SITE_VISITS_E2E_OK\n";
        } finally {
            DB::rollBack();
            $this->app['auth']->forgetGuards();
        }
    }

    private function assertSchema(): void
    {
        foreach ([
            'biannual_site_visit_templates',
            'biannual_site_visit_sections',
            'biannual_site_visit_topics',
            'biannual_site_visit_questions',
            'biannual_site_visit_profiles',
            'biannual_site_visit_answers',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table {$table}.");
        }
        $this->assertTrue(
            Schema::hasColumn('biannual_site_visit_profiles', 'score_percentage'),
            'The report score column is missing.'
        );
    }

    private function assertCustomBuilderRoundTrip(User $admin): BiAnnualSiteVisitTemplate
    {
        $template = BiAnnualSiteVisitTemplate::create([
            'code' => 'smoke-custom-'.Str::lower(Str::random(8)),
            'version' => 1,
            'name' => 'Custom Scoring Smoke Template',
            'status' => BiAnnualSiteVisitTemplate::STATUS_DRAFT,
            'is_default' => false,
            'settings' => [],
            'visibility' => [],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $service = app(BiAnnualSiteVisitTemplateService::class);
        $definition = [
            [
                'key' => 'Setup',
                'title' => 'Setup',
                'topics' => [[
                    'key' => 'Eligibility',
                    'title' => 'Eligibility',
                    'questions' => [[
                        'key' => 'Gate Key',
                        'prompt' => 'Is risk scoring required?',
                        'response_type' => 'single_choice',
                        'required' => true,
                        'options' => [
                            ['value' => 'yes', 'label' => 'Yes'],
                            ['value' => 'no', 'label' => 'No'],
                        ],
                    ]],
                ]],
            ],
            [
                'key' => 'Risk',
                'title' => 'Risk',
                'guidance' => 'Preserve section guidance.',
                'weight' => 0,
                'settings' => ['display' => 'compact'],
                'visibility_rules' => ['question_key' => 'Gate Key', 'operator' => 'equals', 'value' => 'yes'],
                'topics' => [[
                    'key' => 'Controls',
                    'title' => 'Controls',
                    'weight' => 0,
                    'settings' => ['accent' => 'gold'],
                    'questions' => [[
                        'key' => 'Residual-Risk',
                        'prompt' => 'Residual risk rating',
                        'response_type' => 'scored_assessment',
                        'required' => false,
                        'required_when' => ['question_key' => 'Gate Key', 'operator' => 'equals', 'value' => 'yes'],
                        'scoring_direction' => 'negative',
                        'weight' => 0,
                        'allows_na' => true,
                        'minimum_score' => 1,
                        'maximum_score' => 5,
                        'options' => [
                            [
                                'value' => '001',
                                'score' => 1,
                                'label' => 'No material risk',
                                'description' => 'Controls are operating with only minor gaps.',
                            ],
                            [
                                'value' => 'critical',
                                'score' => 5,
                                'label' => 'Critical risk',
                                'description' => 'Major control failure requiring immediate action.',
                            ],
                            [
                                'value' => 'na',
                                'score' => 1,
                                'label' => 'Not Applicable',
                                'description' => 'The control does not apply to this Think Tank.',
                                'is_not_applicable' => true,
                            ],
                        ],
                    ]],
                ]],
            ],
        ];

        $service->replaceStructure($template, $definition, $admin->id);
        $structure = $service->builderStructure($template->fresh());
        $structure[0]['topics'][0]['questions'][0]['key'] = 'Eligibility Gate';
        $service->replaceStructure($template->fresh(), $structure, $admin->id);
        $roundTrip = $service->builderStructure($template->fresh());
        $question = $roundTrip[1]['topics'][0]['questions'][0];

        $this->assertSame('risk', $roundTrip[1]['key'], 'Section stable key was not normalized.');
        $this->assertSame('controls', $roundTrip[1]['topics'][0]['key'], 'Topic stable key was not normalized.');
        $this->assertSame('residual-risk', $question['key'], 'Question stable key was not normalized.');
        $this->assertSame(1.0, $question['minimum_score'], 'Custom minimum score was not retained.');
        $this->assertSame(5.0, $question['maximum_score'], 'Custom maximum score was not derived or retained.');
        $this->assertSame('negative', $question['scoring_direction'], 'Risk scoring direction was lost.');
        $this->assertSame(true, $question['allows_na'], 'The N/A setting was lost.');
        $this->assertSame(0.0, $roundTrip[1]['weight'], 'A zero section weight was re-enabled.');
        $this->assertSame(0.0, $roundTrip[1]['topics'][0]['weight'], 'A zero topic weight was re-enabled.');
        $this->assertSame(0.0, $question['weight'], 'A zero question weight was re-enabled.');
        $this->assertSame(
            'eligibility-gate',
            data_get($question, 'required_when.conditions.0.question_key'),
            'Conditional required logic was not retained through a key rename.'
        );
        $this->assertSame(
            'compact',
            $roundTrip[1]['settings']['display'] ?? null,
            'Section builder settings were lost.'
        );
        $this->assertSame(
            'critical',
            data_get($question, 'options.1.value'),
            'A structured option value was lost.'
        );
        $this->assertSame(
            '001',
            data_get($question, 'options.0.value'),
            'A numeric-looking option identifier was coerced.'
        );
        $this->assertSame(
            5.0,
            (float) data_get($question, 'options.1.score'),
            'A structured option score was lost.'
        );
        $this->assertSame(
            'Major control failure requiring immediate action.',
            data_get($question, 'options.1.description'),
            'A rubric description was lost.'
        );
        $this->assertSame(
            'eligibility-gate',
            data_get($roundTrip, '1.visibility_rules.conditions.0.question_key'),
            'Section visibility was not rewritten after a dependency key changed.'
        );

        $questionnaire = app(BiannualQuestionnaire::class);
        $canonical = $questionnaire->normalizeTemplate(
            $service->canonicalDefinition($template->fresh()->questionnaireSnapshot())
        );
        $hidden = $questionnaire->validateAnswers($canonical, ['eligibility-gate' => 'no'], false);
        $required = $questionnaire->completionStats($canonical, ['eligibility-gate' => 'yes']);
        $this->assertTrue(
            in_array('residual-risk', $hidden['hidden_question_keys'], true),
            'Conditional section visibility did not hide the dependent question.'
        );
        $this->assertTrue(
            in_array('residual-risk', $required['required_missing_keys'], true),
            'Conditional required logic did not activate for the visible question.'
        );

        $this->asUser($admin)
            ->get(route('biannual-site-visits.templates.edit', $template))
            ->assertOk()
            ->assertSee('001')
            ->assertSee('Major control failure requiring immediate action.');

        return $template->fresh();
    }

    /**
     * @param  list<string>  $teamMemberIds
     */
    private function assertSameScoreRatingRoundTrip(
        User $admin,
        User $leader,
        array $teamMemberIds,
        ConsortiumThinkTank $thinkTank,
        BiAnnualSiteVisitTemplate $template
    ): void {
        $title = 'Custom Rating Semantics '.Str::upper(Str::random(5));
        $this->asUser($admin);
        $this->postWithCsrf(route('biannual-site-visits.store'), [
            'think_tank_member_id' => $thinkTank->id,
            'template_id' => $template->id,
            'cycle_year' => 2097,
            'cycle_half' => 'H2',
            'title' => $title,
            'location' => 'Custom Scoring Test Office',
            'starts_on' => '2097-09-10',
            'ends_on' => '2097-09-11',
            'objectives' => 'Verify configurable rating values and rubric guidance.',
            'group_name' => 'Custom Rating Monitoring Team',
            'team_members' => $teamMemberIds,
            'team_specialisms' => array_map(
                fn (int $index): string => [
                    'Project Coordinator',
                    'Finance Management Specialist',
                    'Senior Procurement Advisor',
                    'M&E Officer',
                    'Technical Advisor',
                    'Project Aide Administrative Assistant',
                ][$index],
                array_keys($teamMemberIds)
            ),
            'group_leader_id' => $leader->id,
        ])->assertRedirect();

        $visit = BiAnnualSiteVisitProfile::query()
            ->with(['siteVisit.group.members', 'answers'])
            ->where('title', $title)
            ->firstOrFail();
        $this->assertSame(
            count($teamMemberIds),
            $visit->siteVisit->group->members->count(),
            'A monitoring team larger than five members was artificially limited.'
        );
        $baseAnswers = [
            'eligibility-gate' => [
                '_present' => 1,
                'value' => 'yes',
            ],
            'residual-risk' => [
                '_present' => 1,
                'score' => '001',
                'strength' => 'Controls are operating.',
                'weakness' => '',
                'evidence_notes' => 'Verified against the custom rubric.',
                'not_applicable_reason' => '',
            ],
        ];

        $this->asUser($leader);
        $this->putWithCsrf(
            route('biannual-site-visits.answers.update', $visit),
            ['answers' => $baseAnswers]
        )->assertRedirect();

        $answer = $visit->answers()->where('question_key', 'residual-risk')->firstOrFail();
        $this->assertSame(1.0, (float) $answer->score, 'An applicable custom score was not saved.');
        $this->assertSame(false, (bool) $answer->is_not_applicable, 'An applicable score became N/A.');
        $this->assertSame('No material risk', $answer->rating_label, 'The applicable rating label is incorrect.');

        $content = $this->get(route('biannual-site-visits.show', $visit))
            ->assertOk()
            ->assertSee('Controls are operating with only minor gaps.')
            ->getContent();
        $this->assertTrue(
            preg_match('/value="001"[^>]*checked/s', $content) === 1,
            'The applicable same-score rating was not selected in the web form.'
        );
        $this->assertTrue(
            preg_match('/value="na"[^>]*checked/s', $content) === 0,
            'The N/A radio was also selected for an applicable score.'
        );

        $oldInputContent = $this->withSession([
            '_old_input' => [
                'answers' => [
                    'residual-risk' => ['score' => 'na'],
                ],
            ],
        ])->get(route('biannual-site-visits.show', $visit))
            ->assertOk()
            ->getContent();
        $this->assertTrue(
            preg_match('/value="na"[^>]*checked/s', $oldInputContent) === 1
                && preg_match('/value="001"[^>]*checked/s', $oldInputContent) === 0,
            'A flashed custom rating value did not override the persisted selection.'
        );

        $baseAnswers['residual-risk']['score'] = 'na';
        $baseAnswers['residual-risk']['not_applicable_reason'] = 'This control is outside the visit scope.';
        $this->withSession(['_old_input' => []]);
        $this->putWithCsrf(
            route('biannual-site-visits.answers.update', $visit),
            ['answers' => $baseAnswers]
        )->assertRedirect();

        $answer = $answer->fresh();
        $this->assertSame(true, (bool) $answer->is_not_applicable, 'The explicit N/A option was not saved.');
        $this->assertSame('Not Applicable', $answer->rating_label, 'The N/A rating label is incorrect.');
        $content = $this->get(route('biannual-site-visits.show', $visit))
            ->assertOk()
            ->getContent();
        $this->assertTrue(
            preg_match('/value="na"[^>]*checked/s', $content) === 1,
            'The explicit N/A rating was not selected in the web form.'
        );
        $this->assertTrue(
            preg_match('/value="001"[^>]*checked/s', $content) === 0,
            'The applicable same-score radio was also selected for N/A.'
        );

        $pdfHtml = view('biannual-site-visits.pdf', [
            'visit' => $visit->fresh()->load([
                'siteVisit.group.members.user',
                'thinkTank',
                'template',
            ]),
            'snapshot' => [
                'sections' => app(BiAnnualSiteVisitTemplateService::class)
                    ->canonicalDefinition($visit->questionnaire_snapshot)['sections'],
            ],
            'answerMap' => [
                'residual-risk' => [
                    'score' => 0,
                    'rating_label' => $answer->rating_label,
                    'is_not_applicable' => true,
                    'not_applicable_reason' => $answer->na_reason,
                ],
            ],
            'scores' => ['overall' => null],
            'visibleQuestionKeys' => ['eligibility-gate', 'residual-risk'],
        ])->render();
        $this->assertTrue(
            str_contains($pdfHtml, 'The control does not apply to this Think Tank.'),
            'The selected rubric guidance is missing from the PDF.'
        );
    }

    private function admin(): User
    {
        $role = Role::query()->where('name', 'System Admin')->firstOrFail();
        $admin = User::query()->where('role_id', $role->id)->first();

        if ($admin) {
            return $admin;
        }

        return User::create([
            'name' => 'Bi-Annual Smoke Administrator',
            'email' => 'biannual-admin-'.Str::lower(Str::random(6)).'@example.test',
            'password' => Hash::make('Password123!'),
            'user_type' => 'admin',
            'role_id' => $role->id,
            'must_change_password' => false,
            'is_disabled' => false,
        ]);
    }

    private function asUser(User $user): self
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

    private function patchWithCsrf(string $uri, array $data = [])
    {
        $token = Str::random(40);

        return $this->withSession(['_token' => $token])
            ->patch($uri, ['_token' => $token, ...$data]);
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
            throw new RuntimeException(
                $message.' Expected '.var_export($expected, true).', got '.var_export($actual, true).'.'
            );
        }
    }
}

(new BiAnnualSiteVisitsSmoke($app))->run();
