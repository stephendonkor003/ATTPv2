<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesSiteVisitsToPortfolio;
use App\Mail\BiAnnualSiteVisitCreatedMail;
use App\Models\BiAnnualSiteVisitAnswer;
use App\Models\BiAnnualSiteVisitProfile;
use App\Models\BiAnnualSiteVisitTemplate;
use App\Models\ConsortiumThinkTank;
use App\Models\Sector;
use App\Models\SiteVisit;
use App\Models\SiteVisitGroup;
use App\Models\SiteVisitGroupMember;
use App\Models\User;
use App\Services\BiAnnualSiteVisitBrandingService;
use App\Services\BiAnnualSiteVisitPdfService;
use App\Services\BiAnnualSiteVisitTemplateService;
use App\Support\BiannualQuestionnaire;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BiAnnualSiteVisitController extends Controller
{
    use ScopesSiteVisitsToPortfolio;

    public function __construct(
        private readonly BiannualQuestionnaire $questionnaire,
        private readonly BiAnnualSiteVisitTemplateService $templateService,
        private readonly BiAnnualSiteVisitBrandingService $branding,
        private readonly BiAnnualSiteVisitPdfService $pdfService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->authorizeAnyPermission($user, [
            'biannual_site_visits.view',
            'biannual_site_visits.create',
            'biannual_site_visits.respond',
            'biannual_site_visits.submit',
            'biannual_site_visits.approve',
            'biannual_site_visits.export',
        ]);
        $base = BiAnnualSiteVisitProfile::query()
            ->with([
                'siteVisit.group.leader',
                'siteVisit.group.members.user',
                'thinkTank.consortium.programFunding.program.sector',
                'template',
            ]);

        $this->applyAccessScope($base, $user);

        $years = (clone $base)
            ->reorder()
            ->select('cycle_year')
            ->distinct()
            ->orderByDesc('cycle_year')
            ->pluck('cycle_year');

        $statsBase = clone $base;
        $stats = [
            'total' => (clone $statsBase)->count(),
            'active' => (clone $statsBase)->whereHas(
                'siteVisit',
                fn (Builder $query) => $query->whereIn('status', ['draft', 'returned', 'in_progress'])
            )->count(),
            'submitted' => (clone $statsBase)->whereHas(
                'siteVisit',
                fn (Builder $query) => $query->where('status', 'submitted')
            )->count(),
            'approved' => (clone $statsBase)->whereHas(
                'siteVisit',
                fn (Builder $query) => $query->where('status', 'approved')
            )->count(),
        ];

        $visits = $base
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $query->whereHas(
                    'siteVisit',
                    fn (Builder $siteVisit) => $siteVisit->where('status', $request->string('status'))
                );
            })
            ->when(
                $request->filled('cycle_year'),
                fn (Builder $query) => $query->where('cycle_year', (int) $request->input('cycle_year'))
            )
            ->orderByDesc('cycle_year')
            ->orderByDesc('cycle_half')
            ->orderByDesc('starts_on')
            ->paginate(20);

        return view('biannual-site-visits.index', compact('visits', 'years', 'stats'));
    }

    public function create(Request $request): View
    {
        $this->authorizePermission($request->user(), 'biannual_site_visits.create');

        $thinkTanksQuery = ConsortiumThinkTank::query()
            ->with('consortium.programFunding.program.sector')
            ->where('status', 'active')
            ->whereHas('consortium.programFunding.program.sector');
        $this->applyBiAnnualGovernanceScope(
            $thinkTanksQuery,
            $request->user(),
            'consortium.programFunding'
        );
        $thinkTanks = $thinkTanksQuery
            ->orderBy('name')
            ->get();

        $templates = BiAnnualSiteVisitTemplate::query()
            ->published()
            ->withCount('questions')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->orderByDesc('version')
            ->get();

        $usersQuery = User::query()
            ->with(['role.permissions', 'permissions'])
            ->where(fn (Builder $query) => $query
                ->whereNull('is_disabled')
                ->orWhere('is_disabled', false))
            ->where(fn (Builder $query) => $query
                ->whereNull('is_blacklisted')
                ->orWhere('is_blacklisted', false))
            ->where(fn (Builder $query) => $query
                ->whereNull('user_type')
                ->orWhereNotIn('user_type', [
                    'funding_partner',
                    'vendor',
                    'think_tank',
                    'applicant',
                    'member_state',
                ]))
            ->orderBy('name');
        $this->applyBiAnnualAssignableUserScope($usersQuery, $request->user());
        $users = $usersQuery
            ->get(['id', 'name', 'email', 'role_id'])
            ->filter(fn (User $user): bool => filter_var($user->email, FILTER_VALIDATE_EMAIL)
                && (
                    $user->can('biannual_site_visits.respond')
                    || $user->can('biannual_site_visits.approve')
                ))
            ->values();

        return view('biannual-site-visits.create', compact('thinkTanks', 'templates', 'users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request->user(), 'biannual_site_visits.create');

        $validated = $request->validate([
            'think_tank_member_id' => [
                'required',
                Rule::exists('attp_consortium_think_tanks', 'id')->where('status', 'active'),
            ],
            'template_id' => [
                'required',
                Rule::exists('biannual_site_visit_templates', 'id')->where('status', 'published'),
            ],
            'cycle_year' => ['required', 'integer', 'between:2020,2100'],
            'cycle_half' => ['required', Rule::in(['H1', 'H2'])],
            'title' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'objectives' => ['nullable', 'string', 'max:10000'],
            'group_name' => ['required', 'string', 'max:255'],
            'team_members' => ['required', 'array', 'min:1'],
            'team_members.*' => ['required', 'uuid', 'distinct', 'exists:users,id'],
            'team_specialisms' => ['nullable', 'array'],
            'team_specialisms.*' => ['nullable', 'string', 'max:120'],
            'group_leader_id' => ['required', 'uuid', 'exists:users,id'],
        ], [
            'team_members.min' => 'Select at least one monitoring-team member.',
            'team_members.*.distinct' => 'Each monitoring-team member must be different.',
        ]);

        if (! in_array($validated['group_leader_id'], $validated['team_members'], true)) {
            throw ValidationException::withMessages([
                'group_leader_id' => 'The team lead must be one of the selected monitoring-team members.',
            ]);
        }

        $this->assertBiAnnualThinkTankInScope(
            (string) $validated['think_tank_member_id'],
            $request->user()
        );

        $thinkTank = ConsortiumThinkTank::query()
            ->with('consortium.programFunding.program.sector')
            ->where('status', 'active')
            ->findOrFail($validated['think_tank_member_id']);
        $portfolioSnapshot = $this->branding->portfolioSnapshot($thinkTank);

        if (! $portfolioSnapshot) {
            throw ValidationException::withMessages([
                'think_tank_member_id' => 'The selected Think Tank is not linked to a portfolio. Link its consortium and programme before scheduling a visit.',
            ]);
        }

        if ($this->userHasSiteVisitPortfolioScope($request->user())) {
            $this->assertAssignableSiteVisitUsersInScope($validated['team_members']);
        }

        $activeTeam = User::query()
            ->with(['role.permissions', 'permissions'])
            ->whereIn('id', $validated['team_members'])
            ->where(fn (Builder $query) => $query
                ->whereNull('is_disabled')
                ->orWhere('is_disabled', false))
            ->where(fn (Builder $query) => $query
                ->whereNull('is_blacklisted')
                ->orWhere('is_blacklisted', false))
            ->where(fn (Builder $query) => $query
                ->whereNull('user_type')
                ->orWhereNotIn('user_type', [
                    'funding_partner',
                    'vendor',
                    'think_tank',
                    'applicant',
                    'member_state',
                ]))
            ->get();

        if (
            $activeTeam->count() !== count($validated['team_members'])
            || $activeTeam->contains(
                fn (User $user): bool => ! $user->can('biannual_site_visits.respond')
                    && ! $user->can('biannual_site_visits.approve')
            )
        ) {
            throw ValidationException::withMessages([
                'team_members' => 'Every selected monitoring-team member must be an active internal user authorized to complete assessments.',
            ]);
        }

        if ($activeTeam->contains(
            fn (User $user): bool => ! filter_var($user->email, FILTER_VALIDATE_EMAIL)
        )) {
            throw ValidationException::withMessages([
                'team_members' => 'Every monitoring-team member must have a valid email address so the assignment notification can be delivered.',
            ]);
        }

        $leader = $activeTeam->firstWhere('id', $validated['group_leader_id']);
        if (
            ! $leader
            || (
                ! $leader->can('biannual_site_visits.submit')
                && ! $leader->can('biannual_site_visits.approve')
            )
        ) {
            throw ValidationException::withMessages([
                'group_leader_id' => 'The team lead is not authorized to submit Bi-Annual Site Visits.',
            ]);
        }

        $half = $validated['cycle_half'] === 'H1'
            ? BiAnnualSiteVisitProfile::FIRST_HALF
            : BiAnnualSiteVisitProfile::SECOND_HALF;

        $duplicate = BiAnnualSiteVisitProfile::query()
            ->where('think_tank_member_id', $validated['think_tank_member_id'])
            ->where('cycle_year', $validated['cycle_year'])
            ->where('cycle_half', $half)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'cycle_half' => 'This Think Tank already has a visit for the selected half-year cycle.',
            ]);
        }

        $template = BiAnnualSiteVisitTemplate::query()
            ->published()
            ->withStructure()
            ->findOrFail($validated['template_id']);

        $profile = DB::transaction(function () use (
            $validated,
            $half,
            $template,
            $request,
            $portfolioSnapshot
        ) {
            $siteVisit = SiteVisit::create([
                'assignment_type' => 'group',
                'visit_type' => BiAnnualSiteVisitProfile::VISIT_TYPE,
                'visit_date' => $validated['starts_on'],
                'status' => 'draft',
                'created_by' => $request->user()->id,
                'assigned_by' => $request->user()->id,
            ]);

            $group = SiteVisitGroup::create([
                'site_visit_id' => $siteVisit->id,
                'group_name' => $validated['group_name'],
                'leader_id' => $validated['group_leader_id'],
            ]);

            $specialisms = [];
            foreach ($validated['team_members'] as $index => $userId) {
                SiteVisitGroupMember::create([
                    'group_id' => $group->id,
                    'user_id' => $userId,
                    'role' => (string) $userId === (string) $validated['group_leader_id']
                        ? 'leader'
                        : 'member',
                ]);

                $specialisms[$userId] = $validated['team_specialisms'][$index] ?? null;
            }

            return BiAnnualSiteVisitProfile::create([
                'site_visit_id' => $siteVisit->id,
                'think_tank_member_id' => $validated['think_tank_member_id'],
                'template_id' => $template->id,
                'reference_number' => $this->referenceNumber(
                    (int) $validated['cycle_year'],
                    $half
                ),
                'title' => $validated['title'],
                'template_version' => $template->version,
                'cycle_year' => $validated['cycle_year'],
                'cycle_half' => $half,
                'location' => $validated['location'] ?? null,
                'starts_on' => $validated['starts_on'],
                'ends_on' => $validated['ends_on'],
                'objectives' => $validated['objectives'] ?? null,
                'questionnaire_snapshot' => $template->questionnaireSnapshot(),
                'settings' => [
                    'cycle_label' => $validated['cycle_half'],
                    'team_specialisms' => $specialisms,
                    'portfolio' => $portfolioSnapshot,
                ],
                'visibility_snapshot' => $template->visibility,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        });

        $profile->loadMissing([
            'siteVisit.group.leader',
            'siteVisit.group.members.user',
            'thinkTank.consortium.programFunding.program.sector',
            'template',
        ]);
        $portfolioName = $this->branding->portfolioNameForVisit($profile);
        $leaderId = (string) $profile->siteVisit?->group?->leader_id;

        $profile->siteVisit?->group?->members
            ?->map(fn (SiteVisitGroupMember $member) => $member->user)
            ->filter(fn (?User $user): bool => $user
                && filter_var($user->email, FILTER_VALIDATE_EMAIL))
            ->unique(fn (User $user): string => (string) $user->id)
            ->each(function (User $recipient) use ($profile, $portfolioName, $leaderId): void {
                Mail::to($recipient)->queue(new BiAnnualSiteVisitCreatedMail(
                    $profile,
                    $recipient,
                    (string) $recipient->id === $leaderId,
                    $portfolioName
                ));
            });

        return redirect()
            ->route('biannual-site-visits.show', $profile)
            ->with('success', 'Bi-Annual Site Visit scheduled with the selected monitoring team.');
    }

    public function submittedReport(Request $request): View
    {
        $this->authorizeAnyPermission($request->user(), [
            'biannual_site_visits.view',
            'biannual_site_visits.approve',
            'biannual_site_visits.export',
        ]);

        return view(
            'biannual-site-visits.reports.submitted',
            $this->submittedReportPayload($request, true)
        );
    }

    public function submittedReportPdf(Request $request)
    {
        abort_unless(
            $request->user()
                && (
                    $request->user()->can('biannual_site_visits.export')
                    || $request->user()->can('biannual_site_visits.approve')
                ),
            403,
            'You are not authorized to export submitted Bi-Annual Site Visit reports.'
        );

        $payload = $this->submittedReportPayload($request, false);
        $filename = Str::slug(
            $payload['portfolioName'].'-bi-annual-submitted-site-visits'
        ).'.pdf';

        $pdf = Pdf::loadView('biannual-site-visits.reports.submitted-pdf', $payload)
            ->setPaper('a4', 'landscape');

        return $this->pdfService
            ->stampPageNumbers($pdf, 24, 17)
            ->download($filename);
    }

    public function show(Request $request, BiAnnualSiteVisitProfile $visit): View
    {
        $this->loadVisit($visit);
        $this->authorizeVisitAccess($visit, $request->user());

        return view('biannual-site-visits.show', $this->viewPayload($visit, $request->user()));
    }

    public function updateAnswers(
        Request $request,
        BiAnnualSiteVisitProfile $visit
    ): RedirectResponse {
        $this->loadVisit($visit);
        $this->authorizeVisitEditing($visit, $request->user());

        $validated = $request->validate([
            'answers' => ['nullable', 'array'],
            'answers.*' => ['array'],
            'answers.*._present' => ['nullable', 'boolean'],
            'answers.*.score' => ['nullable'],
            'answers.*.value' => ['nullable'],
            'answers.*.strength' => ['nullable', 'string', 'max:20000'],
            'answers.*.weakness' => ['nullable', 'string', 'max:20000'],
            'answers.*.evidence_notes' => ['nullable', 'string', 'max:30000'],
            'answers.*.not_applicable_reason' => ['nullable', 'string', 'max:5000'],
        ]);

        $definition = $this->templateService->canonicalDefinition($visit->questionnaire_snapshot);
        $canonical = $this->questionnaire->normalizeTemplate($definition);
        $rawQuestions = $this->flattenDefinitionQuestions($definition);
        $canonicalQuestions = $this->questionnaire->flattenQuestions($canonical);
        $submitted = $validated['answers'] ?? [];

        DB::transaction(function () use (
            $visit,
            $request,
            $submitted,
            $canonical,
            $rawQuestions,
            $canonicalQuestions
        ): void {
            $lockedSiteVisit = SiteVisit::query()
                ->lockForUpdate()
                ->findOrFail($visit->site_visit_id);

            if (! in_array($lockedSiteVisit->status, ['draft', 'returned'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'This questionnaire became read-only before the draft could be saved.',
                ]);
            }

            $visit->setRelation('siteVisit', $lockedSiteVisit);
            $visit->unsetRelation('answers');
            $visit->load('answers');
            $candidateAnswers = $this->flatAnswers($visit);
            $canonicalToRaw = [];

            foreach ($rawQuestions as $index => $rawQuestion) {
                $canonicalQuestion = $canonicalQuestions[$index] ?? null;
                if (! is_array($canonicalQuestion)) {
                    continue;
                }

                $rawKey = (string) $rawQuestion['key'];
                $canonicalKey = (string) $canonicalQuestion['key'];
                $canonicalToRaw[$canonicalKey] = $rawKey;

                if (! array_key_exists($rawKey, $submitted)) {
                    continue;
                }

                $input = is_array($submitted[$rawKey]) ? $submitted[$rawKey] : [];
                $candidateAnswers[$canonicalKey] = ($canonicalQuestion['type'] ?? null) === 'scored_assessment'
                    ? ($input['score'] ?? null)
                    : ($input['value'] ?? null);
            }

            $draftValidation = $this->questionnaire->validateAnswers(
                $canonical,
                $candidateAnswers,
                false
            );

            if (! $draftValidation['valid']) {
                $messages = [];
                foreach ($draftValidation['errors'] as $canonicalKey => $errors) {
                    $rawKey = $canonicalToRaw[$canonicalKey] ?? $canonicalKey;
                    $messages["answers.{$rawKey}"] = implode(' ', $errors);
                }

                throw ValidationException::withMessages($messages);
            }

            $visibleKeys = array_fill_keys($draftValidation['visible_question_keys'], true);
            $normalizedAnswers = $draftValidation['answers'];
            $existingAnswers = $visit->answers->keyBy('question_key');

            foreach ($rawQuestions as $index => $rawQuestion) {
                $rawKey = (string) $rawQuestion['key'];
                $canonicalQuestion = $canonicalQuestions[$index] ?? null;
                if (! is_array($canonicalQuestion)) {
                    continue;
                }

                $canonicalKey = (string) $canonicalQuestion['key'];
                $existing = $existingAnswers->get($rawKey);

                if (! isset($visibleKeys[$canonicalKey])) {
                    $existing?->delete();

                    continue;
                }

                if (! array_key_exists($rawKey, $submitted)) {
                    continue;
                }

                $input = is_array($submitted[$rawKey]) ? $submitted[$rawKey] : [];
                $isScored = ($canonicalQuestion['type'] ?? null) === 'scored_assessment';
                $normalizedValue = $normalizedAnswers[$canonicalKey] ?? null;
                $isNotApplicable = $isScored
                    && $normalizedValue === BiannualQuestionnaire::NOT_APPLICABLE;

                $hasFinding = collect([
                    $input['strength'] ?? null,
                    $input['weakness'] ?? null,
                    $input['evidence_notes'] ?? null,
                    $input['not_applicable_reason'] ?? null,
                ])->contains(fn ($value) => filled($value));
                $hasValue = ! $this->blankAnswerValue($normalizedValue);

                if (! $hasFinding && ! $hasValue) {
                    $existing?->delete();

                    continue;
                }

                $answer = $existing ?: new BiAnnualSiteVisitAnswer([
                    'profile_id' => $visit->id,
                    'question_key' => $rawKey,
                    'answered_by' => $request->user()->id,
                ]);

                $score = $isScored && ! $isNotApplicable && is_numeric($normalizedValue)
                    ? (float) $normalizedValue
                    : null;

                $answer->fill([
                    'question_id' => $rawQuestion['id'] ?? null,
                    'value' => ['value' => $normalizedValue],
                    'score' => $score,
                    'maximum_score' => $isScored
                        ? (float) data_get($canonicalQuestion, 'score.max', 3)
                        : null,
                    'score_weight' => $isScored
                        ? (float) ($canonicalQuestion['weight'] ?? 1)
                        : 0,
                    'rating_label' => $isScored
                        ? $this->ratingLabel(
                            $rawQuestion,
                            $isNotApplicable ? 0 : $score,
                            $isNotApplicable
                        )
                        : null,
                    'strength' => $input['strength'] ?? null,
                    'weakness' => $input['weakness'] ?? null,
                    'evidence_notes' => $input['evidence_notes'] ?? null,
                    'is_not_applicable' => $isNotApplicable,
                    'na_reason' => $input['not_applicable_reason'] ?? null,
                    'question_snapshot' => $rawQuestion,
                    'updated_by' => $request->user()->id,
                    'answered_at' => now(),
                ]);
                $answer->save();
            }

            $visit->refresh()->load('answers');
            $canonical = $this->questionnaire->normalizeTemplate(
                $this->templateService->canonicalDefinition($visit->questionnaire_snapshot)
            );
            $flatAnswers = $this->flatAnswers($visit);
            $stats = $this->questionnaire->completionStats($canonical, $flatAnswers);
            $scorePercentage = data_get(
                $this->questionnaire->aggregateScores($canonical, $flatAnswers),
                'overall.percentage'
            );

            $visit->update([
                'completion_percentage' => $stats['completion_percentage'],
                'score_percentage' => $scorePercentage,
                'updated_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'Questionnaire draft saved.');
    }

    public function submit(
        Request $request,
        BiAnnualSiteVisitProfile $visit
    ): RedirectResponse {
        $this->loadVisit($visit);
        $this->authorizeVisitSubmission($visit, $request->user());

        DB::transaction(function () use ($visit, $request): void {
            $lockedSiteVisit = SiteVisit::query()
                ->with(['group.members.user'])
                ->lockForUpdate()
                ->findOrFail($visit->site_visit_id);
            $visit->setRelation('siteVisit', $lockedSiteVisit);
            $visit->unsetRelation('answers');
            $visit->load('answers');
            $this->authorizeVisitSubmission($visit, $request->user());

            if (! in_array($lockedSiteVisit->status, ['draft', 'returned'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only a draft or returned questionnaire can be submitted.',
                ]);
            }

            if (! $this->hasValidMonitoringTeam($visit)) {
                throw ValidationException::withMessages([
                    'team' => 'The monitoring team must contain at least one distinct, active, authorized internal member and a selected lead before submission.',
                ]);
            }

            $canonical = $this->questionnaire->normalizeTemplate(
                $this->templateService->canonicalDefinition($visit->questionnaire_snapshot)
            );
            $flatAnswers = $this->flatAnswers($visit);
            $validation = $this->questionnaire->validateAnswers(
                $canonical,
                $flatAnswers,
                true
            );
            $completion = $this->questionnaire->completionStats($canonical, $flatAnswers);
            $scorePercentage = data_get(
                $this->questionnaire->aggregateScores($canonical, $flatAnswers),
                'overall.percentage'
            );
            $missingNaReasons = $visit->answers
                ->where('is_not_applicable', true)
                ->filter(fn (BiAnnualSiteVisitAnswer $answer) => blank($answer->na_reason))
                ->pluck('question_key')
                ->values();

            if (
                ! $validation['valid']
                || $missingNaReasons->isNotEmpty()
                || (
                    $completion['answerable_question_count'] > 0
                    && $completion['answered_question_count'] === 0
                )
            ) {
                $invalid = collect(array_keys($validation['errors']))
                    ->merge($missingNaReasons)
                    ->unique()
                    ->take(12)
                    ->implode(', ');

                throw ValidationException::withMessages([
                    'questionnaire' => 'Complete the required assessment responses and N/A justifications before submission.'
                        .($invalid ? " Check: {$invalid}." : ''),
                ]);
            }

            $lockedSiteVisit->update(['status' => 'submitted']);
            $visit->update([
                'completion_percentage' => $completion['completion_percentage'],
                'score_percentage' => $scorePercentage,
                'submitted_by' => $request->user()->id,
                'submitted_at' => now(),
                'updated_by' => $request->user()->id,
            ]);
        });

        return back()->with('success', 'The consolidated site visit was submitted for review.');
    }

    public function review(
        Request $request,
        BiAnnualSiteVisitProfile $visit
    ): RedirectResponse {
        $this->authorizePermission($request->user(), 'biannual_site_visits.approve');
        $this->loadVisit($visit);
        $this->assertBiAnnualProfileInScope($visit, $request->user());

        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'returned'])],
            'remarks' => [
                Rule::requiredIf(fn () => $request->input('status') === 'returned'),
                'nullable',
                'string',
                'max:10000',
            ],
        ]);

        DB::transaction(function () use ($visit, $request, $validated): void {
            $lockedSiteVisit = SiteVisit::query()
                ->lockForUpdate()
                ->findOrFail($visit->site_visit_id);

            if ($lockedSiteVisit->status !== 'submitted') {
                throw ValidationException::withMessages([
                    'status' => 'Only a submitted site visit can be reviewed.',
                ]);
            }

            $lockedSiteVisit->approvals()->create([
                'reviewer_id' => $request->user()->id,
                'status' => $validated['status'],
                'remarks' => $validated['remarks'] ?? null,
            ]);
            $lockedSiteVisit->update(['status' => $validated['status']]);
            $visit->update([
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'updated_by' => $request->user()->id,
            ]);
        });

        $message = $validated['status'] === 'approved'
            ? 'Bi-Annual Site Visit approved and finalized.'
            : 'Bi-Annual Site Visit returned to the team for correction.';

        return back()->with('success', $message);
    }

    public function pdf(
        Request $request,
        BiAnnualSiteVisitProfile $visit
    ) {
        $this->loadVisit($visit);
        $this->authorizeVisitAccess($visit, $request->user());
        abort_unless(
            $request->user()->can('biannual_site_visits.export')
                || $request->user()->can('biannual_site_visits.approve'),
            403,
            'You are not authorized to export Bi-Annual Site Visit reports.'
        );

        $payload = $this->viewPayload($visit, $request->user());
        $filename = Str::slug($visit->reference_number.'-'.$visit->thinkTank?->name).'.pdf';

        $pdf = Pdf::loadView('biannual-site-visits.pdf', $payload)
            ->setPaper('a4', 'portrait');

        return $this->pdfService
            ->stampPageNumbers($pdf)
            ->download($filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function submittedReportPayload(Request $request, bool $paginate): array
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'portfolio_id' => ['nullable', 'uuid', 'exists:myb_sectors,id'],
            'think_tank_id' => ['nullable', 'uuid', 'exists:attp_consortium_think_tanks,id'],
            'cycle_year' => ['nullable', 'integer', 'between:2020,2100'],
            'cycle_half' => ['nullable', Rule::in(['1', '2'])],
            'status' => ['nullable', Rule::in(['submitted', 'approved'])],
            'submitted_from' => ['nullable', 'date'],
            'submitted_to' => ['nullable', 'date', 'after_or_equal:submitted_from'],
        ]);

        $user = $request->user();
        $optionProfiles = $this->submittedReportQuery($user, false)
            ->orderByDesc('submitted_at')
            ->get();

        $options = [
            'portfolios' => $optionProfiles
                ->map(function (BiAnnualSiteVisitProfile $visit): array {
                    return [
                        'id' => (string) (
                            $this->branding->portfolioIdForVisit($visit) ?: ''
                        ),
                        'name' => $this->branding->portfolioNameForVisit($visit),
                    ];
                })
                ->filter(fn (array $portfolio): bool => $portfolio['id'] !== '')
                ->unique('id')
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all(),
            'think_tanks' => $optionProfiles
                ->map(fn (BiAnnualSiteVisitProfile $visit): array => [
                    'id' => (string) $visit->think_tank_member_id,
                    'name' => (string) ($visit->thinkTank?->name ?: 'Unnamed Think Tank'),
                ])
                ->unique('id')
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all(),
            'years' => $optionProfiles
                ->pluck('cycle_year')
                ->filter()
                ->unique()
                ->sortDesc()
                ->values()
                ->all(),
        ];

        $query = $this->submittedReportQuery($user);
        $this->applySubmittedReportFilters($query, $filters);

        $statsQuery = clone $query;
        $total = (clone $statsQuery)->count();
        $averageScore = $this->averageSubmittedScore($statsQuery);
        $averageCompletion = (clone $statsQuery)->avg('completion_percentage');
        $stats = [
            'total' => $total,
            'awaiting' => (clone $statsQuery)->whereHas(
                'siteVisit',
                fn (Builder $siteVisit) => $siteVisit->where('status', 'submitted')
            )->count(),
            'approved' => (clone $statsQuery)->whereHas(
                'siteVisit',
                fn (Builder $siteVisit) => $siteVisit->where('status', 'approved')
            )->count(),
            'average_score' => $averageScore === null
                ? null
                : round((float) $averageScore, 2),
            'average_completion' => $total === 0 || $averageCompletion === null
                ? null
                : round((float) $averageCompletion, 2),
        ];

        $selectedPortfolio = collect($options['portfolios'])
            ->firstWhere('id', (string) ($filters['portfolio_id'] ?? ''));
        $portfolioName = $selectedPortfolio['name'] ?? null;

        if (! $portfolioName) {
            $portfolioNames = (clone $query)
                ->withoutEagerLoads()
                ->with('thinkTank.consortium.programFunding.program.sector')
                ->get(['id', 'think_tank_member_id', 'settings'])
                ->map(
                    fn (BiAnnualSiteVisitProfile $visit): string => $this->branding
                        ->portfolioNameForVisit($visit)
                )
                ->filter()
                ->unique()
                ->values();
            $portfolioName = $portfolioNames->count() === 1
                ? (string) $portfolioNames->first()
                : 'ATTP Multi-Portfolio';
        }

        $paginator = null;
        $orderedQuery = $query
            ->orderByDesc('submitted_at')
            ->orderByDesc('cycle_year');

        if ($paginate) {
            $paginator = $orderedQuery->paginate(20)->withQueryString();
            $rows = $paginator
                ->getCollection()
                ->map(fn (BiAnnualSiteVisitProfile $visit): array => $this->submittedReportRow($visit))
                ->values();
            $paginator->setCollection($rows);
        } else {
            $rows = $orderedQuery
                ->get()
                ->map(fn (BiAnnualSiteVisitProfile $visit): array => $this->submittedReportRow($visit))
                ->values();
        }

        return [
            'rows' => $rows,
            'paginator' => $paginator,
            'stats' => $stats,
            'filters' => $filters,
            'options' => $options,
            'portfolioName' => $portfolioName,
            'logoDataUri' => $this->branding->logoDataUri(),
            'generatedAt' => now(),
            'reportPdfUrl' => route(
                'biannual-site-visits.reports.submitted.pdf',
                array_filter(
                    $filters,
                    fn (mixed $value): bool => $value !== null && $value !== ''
                )
            ),
        ];
    }

    private function submittedReportQuery(User $user, bool $withDetails = true): Builder
    {
        $query = BiAnnualSiteVisitProfile::query()
            ->whereNotNull('submitted_at')
            ->whereHas(
                'siteVisit',
                fn (Builder $siteVisit) => $siteVisit->whereIn(
                    'status',
                    ['submitted', 'approved']
                )
            );

        $query->with($withDetails
            ? [
                'siteVisit.group.leader',
                'thinkTank.consortium.programFunding.program.sector',
                'submittedBy',
            ]
            : [
                'thinkTank.consortium.programFunding.program.sector',
            ]);

        if (! $withDetails) {
            $query->select([
                'id',
                'site_visit_id',
                'think_tank_member_id',
                'cycle_year',
                'settings',
                'submitted_at',
            ]);
        }

        $this->applyAccessScope($query, $user);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applySubmittedReportFilters(Builder $query, array $filters): void
    {
        $query
            ->when(
                filled($filters['q'] ?? null),
                function (Builder $search) use ($filters): void {
                    $term = '%'.trim((string) $filters['q']).'%';
                    $search->where(function (Builder $scope) use ($term): void {
                        $scope
                            ->where('reference_number', 'like', $term)
                            ->orWhere('title', 'like', $term)
                            ->orWhereHas(
                                'thinkTank',
                                fn (Builder $thinkTank) => $thinkTank->where('name', 'like', $term)
                            )
                            ->orWhereHas(
                                'siteVisit.group.leader',
                                fn (Builder $leader) => $leader
                                    ->where('name', 'like', $term)
                                    ->orWhere('email', 'like', $term)
                            );
                    });
                }
            )
            ->when(
                filled($filters['portfolio_id'] ?? null),
                function (Builder $portfolio) use ($filters): void {
                    $portfolioId = (string) $filters['portfolio_id'];
                    $portfolio->where(function (Builder $scope) use ($portfolioId): void {
                        $scope
                            ->where('settings->portfolio->id', $portfolioId)
                            ->orWhere(function (Builder $legacy) use ($portfolioId): void {
                                $legacy
                                    ->whereNull('settings->portfolio->id')
                                    ->whereHas(
                                        'thinkTank.consortium.programFunding.program',
                                        fn (Builder $program) => $program->where(
                                            'sector_id',
                                            $portfolioId
                                        )
                                    );
                            });
                    });
                }
            )
            ->when(
                filled($filters['think_tank_id'] ?? null),
                fn (Builder $thinkTank) => $thinkTank->where(
                    'think_tank_member_id',
                    $filters['think_tank_id']
                )
            )
            ->when(
                filled($filters['cycle_year'] ?? null),
                fn (Builder $year) => $year->where('cycle_year', (int) $filters['cycle_year'])
            )
            ->when(
                filled($filters['cycle_half'] ?? null),
                fn (Builder $half) => $half->where('cycle_half', (int) $filters['cycle_half'])
            )
            ->when(
                filled($filters['status'] ?? null),
                fn (Builder $status) => $status->whereHas(
                    'siteVisit',
                    fn (Builder $siteVisit) => $siteVisit->where(
                        'status',
                        $filters['status']
                    )
                )
            )
            ->when(
                filled($filters['submitted_from'] ?? null),
                fn (Builder $from) => $from->whereDate(
                    'submitted_at',
                    '>=',
                    $filters['submitted_from']
                )
            )
            ->when(
                filled($filters['submitted_to'] ?? null),
                fn (Builder $to) => $to->whereDate(
                    'submitted_at',
                    '<=',
                    $filters['submitted_to']
                )
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function submittedReportRow(BiAnnualSiteVisitProfile $visit): array
    {
        $score = $visit->score_percentage;

        if ($score === null) {
            $score = $this->submittedScorePercentage($visit);
        }

        return [
            'visit' => $visit,
            'portfolio_name' => $this->branding->portfolioNameForVisit($visit),
            'think_tank_name' => (string) ($visit->thinkTank?->name ?: 'Unnamed Think Tank'),
            'score_percentage' => $score === null ? null : round((float) $score, 2),
            'completion_percentage' => round((float) $visit->completion_percentage, 2),
            'lead_name' => (string) (
                $visit->siteVisit?->group?->leader?->name
                    ?: 'Not assigned'
            ),
            'submitted_by_name' => (string) (
                $visit->submittedBy?->name
                    ?: 'Unknown user'
            ),
            'submitted_at' => $visit->submitted_at,
            'status' => (string) $visit->siteVisit?->status,
        ];
    }

    /**
     * Include profiles created before score persistence without mutating data
     * during a read-only report request.
     */
    private function averageSubmittedScore(Builder $query): ?float
    {
        $persisted = (clone $query)
            ->withoutEagerLoads()
            ->reorder()
            ->whereNotNull('score_percentage')
            ->selectRaw(
                'COUNT(score_percentage) AS score_count, COALESCE(SUM(score_percentage), 0) AS score_total'
            )
            ->first();

        $scoreCount = (int) ($persisted?->score_count ?? 0);
        $scoreTotal = (float) ($persisted?->score_total ?? 0);

        $legacyProfiles = (clone $query)
            ->withoutEagerLoads()
            ->reorder()
            ->whereNull('score_percentage')
            ->with('answers')
            ->select(['id', 'questionnaire_snapshot', 'score_percentage'])
            ->lazyById(100);

        foreach ($legacyProfiles as $legacyProfile) {
            $fallbackScore = $this->submittedScorePercentage($legacyProfile);

            if ($fallbackScore === null) {
                continue;
            }

            $scoreTotal += $fallbackScore;
            $scoreCount++;
        }

        return $scoreCount === 0
            ? null
            : round($scoreTotal / $scoreCount, 2);
    }

    private function submittedScorePercentage(
        BiAnnualSiteVisitProfile $visit
    ): ?float {
        $visit->loadMissing('answers');
        $canonical = $this->questionnaire->normalizeTemplate(
            $this->templateService->canonicalDefinition($visit->questionnaire_snapshot)
        );
        $score = data_get(
            $this->questionnaire->aggregateScores(
                $canonical,
                $this->flatAnswers($visit)
            ),
            'overall.percentage'
        );

        return $score === null ? null : round((float) $score, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function viewPayload(BiAnnualSiteVisitProfile $visit, User $user): array
    {
        $definition = $this->templateService->canonicalDefinition($visit->questionnaire_snapshot);
        $canonical = $this->questionnaire->normalizeTemplate($definition);
        $flatAnswers = $this->flatAnswers($visit);
        $visibility = $this->questionnaire->validateAnswers($canonical, $flatAnswers, false);
        $completionResult = $this->questionnaire->completionStats($canonical, $flatAnswers);
        $scoreResult = $this->questionnaire->aggregateScores($canonical, $flatAnswers);
        $overallPercentage = data_get($scoreResult, 'overall.percentage');
        $rawQuestions = $this->flattenDefinitionQuestions($definition);
        $canonicalQuestions = $this->questionnaire->flattenQuestions($canonical);
        $visibleCanonicalKeys = array_fill_keys($visibility['visible_question_keys'], true);
        $visibleQuestionKeys = [];

        foreach ($canonicalQuestions as $index => $question) {
            if (isset($visibleCanonicalKeys[$question['key']]) && isset($rawQuestions[$index]['key'])) {
                $visibleQuestionKeys[] = (string) $rawQuestions[$index]['key'];
            }
        }

        $answerMap = $visit->answers->mapWithKeys(fn (BiAnnualSiteVisitAnswer $answer): array => [
            $answer->question_key => [
                'value' => data_get($answer->value, 'value'),
                'score' => $answer->is_not_applicable ? 0 : (
                    $answer->score === null ? null : (float) $answer->score
                ),
                'rating_label' => $answer->rating_label,
                'is_not_applicable' => (bool) $answer->is_not_applicable,
                'strength' => $answer->strength,
                'weakness' => $answer->weakness,
                'evidence_notes' => $answer->evidence_notes,
                'not_applicable_reason' => $answer->na_reason,
            ],
        ])->all();

        $completion = [
            'total' => $completionResult['answerable_question_count'],
            'answered' => $completionResult['answered_question_count'],
            'percentage' => $completionResult['completion_percentage'],
            'required_missing' => $completionResult['required_missing_keys'],
        ];
        $scores = [
            'overall' => $overallPercentage === null ? null : round((float) $overallPercentage, 2),
            'rated' => data_get($scoreResult, 'overall.applicable_question_count', 0),
            'not_applicable' => $visit->answers->where('is_not_applicable', true)->count(),
            'details' => $scoreResult,
        ];

        $status = $visit->siteVisit->status;
        $isMember = $this->isTeamMember($visit, $user);
        $isLead = (string) $visit->siteVisit->group?->leader_id === (string) $user->id;
        $canOverride = $user->can('biannual_site_visits.approve');

        return [
            'visit' => $visit,
            'snapshot' => [
                'name' => $definition['name'],
                'description' => $definition['description'],
                'sections' => $definition['sections'],
            ],
            'answerMap' => $answerMap,
            'completion' => $completion,
            'scores' => $scores,
            'visibleQuestionKeys' => $visibleQuestionKeys,
            'canEdit' => in_array($status, ['draft', 'returned'], true)
                && (
                    ($isMember && $user->can('biannual_site_visits.respond'))
                    || $canOverride
                ),
            'canSubmit' => in_array($status, ['draft', 'returned'], true)
                && (
                    ($isLead && $user->can('biannual_site_visits.submit'))
                    || $canOverride
                ),
            'canReview' => $status === 'submitted'
                && $user->can('biannual_site_visits.approve'),
            'portfolioName' => $this->branding->portfolioNameForVisit($visit),
            'logoDataUri' => $this->branding->logoDataUri(),
        ];
    }

    private function loadVisit(BiAnnualSiteVisitProfile $visit): void
    {
        $visit->loadMissing([
            'siteVisit.group.leader',
            'siteVisit.group.members.user',
            'siteVisit.approvals.reviewer',
            'thinkTank.consortium.programFunding.program.sector',
            'template',
            'answers.question',
        ]);

        abort_unless(
            $visit->siteVisit
                && $visit->siteVisit->visit_type === BiAnnualSiteVisitProfile::VISIT_TYPE,
            404
        );
    }

    private function applyAccessScope(Builder $query, User $user): void
    {
        if (
            $user->can('biannual_site_visits.view')
            || $user->can('biannual_site_visits.approve')
        ) {
            $this->applyBiAnnualGovernanceScope(
                $query,
                $user,
                'thinkTank.consortium.programFunding'
            );

            return;
        }

        $query->whereHas(
            'siteVisit.group.members',
            fn (Builder $memberQuery) => $memberQuery->where('user_id', $user->id)
        );
    }

    private function authorizeVisitAccess(BiAnnualSiteVisitProfile $visit, User $user): void
    {
        if ($this->isTeamMember($visit, $user)) {
            abort_unless(
                $this->userCanAny($user, [
                    'biannual_site_visits.view',
                    'biannual_site_visits.respond',
                    'biannual_site_visits.submit',
                    'biannual_site_visits.export',
                    'biannual_site_visits.approve',
                ]),
                403,
                'You are not authorized to access Bi-Annual Site Visits.'
            );

            return;
        }

        abort_unless(
            $user->can('biannual_site_visits.view')
                || $user->can('biannual_site_visits.approve'),
            403,
            'You are not assigned to this Bi-Annual Site Visit.'
        );

        $this->assertBiAnnualProfileInScope($visit, $user);
    }

    private function authorizeVisitEditing(BiAnnualSiteVisitProfile $visit, User $user): void
    {
        if ($this->isTeamMember($visit, $user)) {
            abort_unless(
                $user->can('biannual_site_visits.respond'),
                403,
                'You are not authorized to complete Bi-Annual Site Visit questionnaires.'
            );
        } else {
            abort_unless(
                $user->can('biannual_site_visits.approve'),
                403,
                'Only assigned monitoring-team members can update this questionnaire.'
            );
            $this->assertBiAnnualProfileInScope($visit, $user);
        }

        abort_unless(
            in_array($visit->siteVisit->status, ['draft', 'returned'], true),
            422,
            'This questionnaire is read-only in its current workflow state.'
        );
    }

    private function authorizeVisitSubmission(BiAnnualSiteVisitProfile $visit, User $user): void
    {
        if ((string) $visit->siteVisit->group?->leader_id === (string) $user->id) {
            abort_unless(
                $user->can('biannual_site_visits.submit'),
                403,
                'You are not authorized to submit Bi-Annual Site Visits.'
            );

            return;
        }

        abort_unless(
            $user->can('biannual_site_visits.approve'),
            403,
            'Only the monitoring-team lead can submit the consolidated questionnaire.'
        );
        $this->assertBiAnnualProfileInScope($visit, $user);
    }

    private function authorizePermission(?User $user, string $permission): void
    {
        abort_unless($user && $user->can($permission), 403);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function authorizeAnyPermission(?User $user, array $permissions): void
    {
        abort_unless($user && $this->userCanAny($user, $permissions), 403);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userCanAny(User $user, array $permissions): bool
    {
        return collect($permissions)->contains(
            fn (string $permission): bool => $user->can($permission)
        );
    }

    private function isTeamMember(BiAnnualSiteVisitProfile $visit, User $user): bool
    {
        return $visit->siteVisit?->group?->members
            ?->contains(fn (SiteVisitGroupMember $member) => (string) $member->user_id === (string) $user->id)
            ?? false;
    }

    private function applyBiAnnualGovernanceScope(
        Builder $query,
        User $user,
        string $programFundingRelation
    ): void {
        if (! $this->userHasSiteVisitPortfolioScope($user)) {
            return;
        }

        $portfolioIds = $this->userHasAssignedPortfolioScope($user)
            ? $this->assignedPortfolioIds($user)
            : [];
        $nodeIds = $this->userHasAssignedPortfolioScope($user)
            ? $this->assignedPortfolioNodeIds($user)
            : array_values(array_filter([(string) $user->governance_node_id]));

        if ($portfolioIds === [] && $nodeIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $applyLiveScope = function (Builder $funding) use ($portfolioIds, $nodeIds): void {
            $funding->where(function (Builder $scope) use ($portfolioIds, $nodeIds): void {
                if ($portfolioIds !== []) {
                    $scope->whereHas(
                        'program',
                        fn (Builder $program) => $program->whereIn(
                            'sector_id',
                            $portfolioIds
                        )
                    );
                }

                if ($nodeIds !== []) {
                    $method = $portfolioIds !== [] ? 'orWhereIn' : 'whereIn';
                    $scope->{$method}('governance_node_id', $nodeIds);
                }
            });
        };

        if (! $query->getModel() instanceof BiAnnualSiteVisitProfile) {
            $query->whereHas($programFundingRelation, $applyLiveScope);

            return;
        }

        $snapshotPortfolioIds = collect($portfolioIds)
            ->merge(
                Sector::query()
                    ->whereIn('governance_node_id', $nodeIds)
                    ->pluck('id')
            )
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        $query->where(function (Builder $access) use (
            $programFundingRelation,
            $applyLiveScope,
            $snapshotPortfolioIds
        ): void {
            if ($snapshotPortfolioIds !== []) {
                $access->whereIn('settings->portfolio->id', $snapshotPortfolioIds);
            } else {
                $access->whereRaw('1 = 0');
            }

            $access->orWhere(function (Builder $legacy) use (
                $programFundingRelation,
                $applyLiveScope
            ): void {
                $legacy
                    ->whereNull('settings->portfolio->id')
                    ->whereHas($programFundingRelation, $applyLiveScope);
            });
        });
    }

    private function applyBiAnnualAssignableUserScope(Builder $query, User $user): void
    {
        if (! $this->userHasSiteVisitPortfolioScope($user)) {
            return;
        }

        $nodeIds = $this->userHasAssignedPortfolioScope($user)
            ? $this->assignedPortfolioNodeIds($user)
            : array_values(array_filter([(string) $user->governance_node_id]));

        if ($nodeIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $portfolioUserIds = Sector::query()
            ->whereIn('governance_node_id', $nodeIds)
            ->get(['portfolio_manager_user_id', 'me_manager_user_id'])
            ->flatMap(fn (Sector $sector): array => [
                $sector->portfolio_manager_user_id,
                $sector->me_manager_user_id,
            ])
            ->filter()
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        $query->where(function (Builder $scope) use ($nodeIds, $portfolioUserIds): void {
            $scope->whereIn('governance_node_id', $nodeIds);

            if ($portfolioUserIds !== []) {
                $scope->orWhereIn('id', $portfolioUserIds);
            }
        });
    }

    private function assertBiAnnualThinkTankInScope(string $thinkTankId, User $user): void
    {
        if (! $this->userHasSiteVisitPortfolioScope($user)) {
            return;
        }

        $query = ConsortiumThinkTank::query()->whereKey($thinkTankId);
        $this->applyBiAnnualGovernanceScope($query, $user, 'consortium.programFunding');

        abort_unless(
            $query->exists(),
            403,
            'You do not have access to the selected Think Tank.'
        );
    }

    private function assertBiAnnualProfileInScope(
        BiAnnualSiteVisitProfile $visit,
        User $user
    ): void {
        if (! $this->userHasSiteVisitPortfolioScope($user)) {
            return;
        }

        $query = BiAnnualSiteVisitProfile::query()->whereKey($visit->id);
        $this->applyBiAnnualGovernanceScope(
            $query,
            $user,
            'thinkTank.consortium.programFunding'
        );

        abort_unless(
            $query->exists(),
            403,
            'You do not have access to this Bi-Annual Site Visit.'
        );
    }

    /**
     * Pair canonical question positions with the immutable raw keys stored in DB.
     *
     * @return array<string, mixed>
     */
    private function flatAnswers(BiAnnualSiteVisitProfile $visit): array
    {
        $definition = $this->templateService->canonicalDefinition($visit->questionnaire_snapshot);
        $canonical = $this->questionnaire->normalizeTemplate($definition);
        $rawQuestions = $this->flattenDefinitionQuestions($definition);
        $canonicalQuestions = $this->questionnaire->flattenQuestions($canonical);
        $answers = $visit->answers->keyBy('question_key');
        $flat = [];

        foreach ($canonicalQuestions as $index => $canonicalQuestion) {
            $rawKey = $rawQuestions[$index]['key'] ?? null;
            if (! $rawKey) {
                continue;
            }

            /** @var BiAnnualSiteVisitAnswer|null $answer */
            $answer = $answers->get($rawKey);
            if (! $answer) {
                $flat[$canonicalQuestion['key']] = null;

                continue;
            }

            $flat[$canonicalQuestion['key']] = ($canonicalQuestion['type'] ?? null) === 'scored_assessment'
                ? ($answer->is_not_applicable ? BiannualQuestionnaire::NOT_APPLICABLE : (
                    $answer->score === null ? null : (float) $answer->score
                ))
                : data_get($answer->value, 'value');
        }

        return $flat;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return list<array<string, mixed>>
     */
    private function flattenDefinitionQuestions(array $definition): array
    {
        $questions = [];

        foreach ($definition['sections'] ?? [] as $section) {
            foreach ($section['topics'] ?? [] as $topic) {
                foreach ($topic['questions'] ?? [] as $question) {
                    $questions[] = $question;
                }
            }
        }

        return $questions;
    }

    private function referenceNumber(int $year, int $half): string
    {
        do {
            $reference = sprintf(
                'BASV-%d-H%d-%s',
                $year,
                $half,
                Str::upper(Str::random(5))
            );
        } while (BiAnnualSiteVisitProfile::query()->where('reference_number', $reference)->exists());

        return $reference;
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function ratingLabel(
        array $question,
        int|float|null $score,
        bool $isNotApplicable = false
    ): ?string {
        foreach ($question['options'] ?? [] as $option) {
            $optionIsNotApplicable = is_array($option)
                && (bool) (
                    $option['is_not_applicable']
                        ?? $option['is_na']
                        ?? in_array(
                            Str::lower(trim((string) ($option['value'] ?? $option['label'] ?? ''))),
                            ['na', 'n/a', 'not applicable', 'not_applicable'],
                            true
                        )
                );

            if (
                is_array($option)
                && $optionIsNotApplicable === $isNotApplicable
                && (
                    $isNotApplicable
                    || (string) ($option['value'] ?? '') === (string) $score
                    || (string) ($option['score'] ?? '') === (string) $score
                )
            ) {
                return (string) ($option['label'] ?? $score);
            }
        }

        if ($isNotApplicable) {
            return 'Not Applicable';
        }

        $labels = $question['rating_labels'] ?? [];

        return isset($labels[(string) $score])
            ? (string) $labels[(string) $score]
            : null;
    }

    private function hasValidMonitoringTeam(BiAnnualSiteVisitProfile $visit): bool
    {
        $group = $visit->siteVisit?->group;
        if (! $group) {
            return false;
        }

        $storedMemberIds = $group->members
            ->pluck('user_id')
            ->map(fn ($id): string => (string) $id);
        $memberIds = $storedMemberIds
            ->unique()
            ->values();

        if (
            $memberIds->isEmpty()
            || $memberIds->count() !== $storedMemberIds->count()
            || ! $memberIds->contains((string) $group->leader_id)
        ) {
            return false;
        }

        $members = User::query()
            ->with(['role.permissions', 'permissions'])
            ->whereIn('id', $memberIds)
            ->where(fn (Builder $query) => $query
                ->whereNull('is_disabled')
                ->orWhere('is_disabled', false))
            ->where(fn (Builder $query) => $query
                ->whereNull('is_blacklisted')
                ->orWhere('is_blacklisted', false))
            ->where(fn (Builder $query) => $query
                ->whereNull('user_type')
                ->orWhereNotIn('user_type', [
                    'funding_partner',
                    'vendor',
                    'think_tank',
                    'applicant',
                    'member_state',
                ]))
            ->get();

        if (
            $members->count() !== $memberIds->count()
            || $members->contains(
                fn (User $user): bool => ! $user->can('biannual_site_visits.respond')
                    && ! $user->can('biannual_site_visits.approve')
            )
        ) {
            return false;
        }

        $leader = $members->first(
            fn (User $user): bool => (string) $user->id === (string) $group->leader_id
        );

        return $leader
            && (
                $leader->can('biannual_site_visits.submit')
                || $leader->can('biannual_site_visits.approve')
            );
    }

    private function blankAnswerValue(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return is_array($value) && Arr::where($value, fn ($item) => filled($item)) === [];
    }
}
