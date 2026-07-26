<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesSiteVisitsToPortfolio;
use App\Models\BiAnnualSiteVisitTemplate;
use App\Models\ConsortiumThinkTank;
use App\Models\Sector;
use App\Models\User;
use App\Services\BiannualQuestionnaireImportService;
use App\Services\BiAnnualSiteVisitBrandingService;
use App\Services\BiAnnualSiteVisitPdfService;
use App\Services\BiAnnualSiteVisitTemplateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use JsonException;
use Throwable;

class BiAnnualSiteVisitTemplateController extends Controller
{
    use ScopesSiteVisitsToPortfolio;

    public function __construct(
        private readonly BiannualQuestionnaireImportService $importer,
        private readonly BiAnnualSiteVisitTemplateService $templates,
        private readonly BiAnnualSiteVisitBrandingService $branding,
        private readonly BiAnnualSiteVisitPdfService $pdfService
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeManage($request->user());

        $templates = BiAnnualSiteVisitTemplate::query()
            ->withCount(['sections', 'questions'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->orderByDesc('version')
            ->get();

        return view('biannual-site-visits.templates.index', compact('templates'));
    }

    public function preview(
        Request $request,
        BiAnnualSiteVisitTemplate $template
    ): View {
        $this->authorizePreview($request->user(), $template);
        [$thinkTank, $portfolioName] = $this->previewContext($request);
        $template->loadMissing('sections.topics.questions');
        $definition = $this->templates->canonicalDefinition(
            $template->questionnaireSnapshot()
        );

        return view('biannual-site-visits.templates.preview', [
            'template' => $template,
            'definition' => $definition,
            'thinkTank' => $thinkTank,
            'portfolioName' => $portfolioName,
            'logoDataUri' => $this->branding->logoDataUri(),
            'pdfUrl' => route(
                'biannual-site-visits.templates.preview.pdf',
                [
                    'template' => $template,
                    'think_tank_member_id' => $thinkTank->id,
                ]
            ),
        ]);
    }

    public function previewPdf(
        Request $request,
        BiAnnualSiteVisitTemplate $template
    ) {
        $this->authorizePreview($request->user(), $template);
        [$thinkTank, $portfolioName] = $this->previewContext($request);
        $template->loadMissing('sections.topics.questions');
        $definition = $this->templates->canonicalDefinition(
            $template->questionnaireSnapshot()
        );
        $filename = Str::slug(
            $portfolioName.'-'.$template->name.'-questionnaire'
        ).'.pdf';

        $pdf = Pdf::loadView('biannual-site-visits.templates.pdf', [
            'template' => $template,
            'definition' => $definition,
            'thinkTank' => $thinkTank,
            'portfolioName' => $portfolioName,
            'logoDataUri' => $this->branding->logoDataUri(),
            'generatedAt' => now(),
        ])
            ->setPaper('a4', 'portrait');

        return $this->pdfService
            ->stampPageNumbers($pdf)
            ->download($filename);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request->user());

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:10000'],
        ]);

        $code = $this->templateCode($validated['code'] ?? $validated['name']);
        $template = BiAnnualSiteVisitTemplate::create([
            'code' => $code,
            'version' => BiAnnualSiteVisitTemplate::nextVersionForCode($code),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => BiAnnualSiteVisitTemplate::STATUS_DRAFT,
            'settings' => [
                'rating_scale' => [
                    'minimum' => 0,
                    'maximum' => 3,
                    'options' => $this->templates->defaultRatingOptions(),
                ],
            ],
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('biannual-site-visits.templates.edit', $template)
            ->with('success', 'Draft questionnaire created. Add sections, topics, and questions below.');
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorizeManage($request->user());

        $validated = $request->validate([
            'questionnaire' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        try {
            $definition = $this->importer->import(
                $validated['questionnaire']->getRealPath()
            );
            $definition = $this->requireImportedVerificationQuestions($definition);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'questionnaire' => 'The workbook could not be imported: '.$exception->getMessage(),
            ]);
        }

        $code = $this->templateCode($definition['code'] ?? $definition['title'] ?? 'BIANNUAL-MONITORING');

        $template = DB::transaction(function () use ($definition, $code, $request) {
            $template = BiAnnualSiteVisitTemplate::create([
                'code' => $code,
                'version' => BiAnnualSiteVisitTemplate::nextVersionForCode($code),
                'name' => $definition['title'] ?? 'Imported Monitoring Questionnaire',
                'description' => 'Imported from '.data_get($definition, 'source.file', 'monitoring workbook').'.',
                'instructions' => 'Record a rating, strengths, weaknesses, and supporting evidence for each applicable verification question.',
                'status' => BiAnnualSiteVisitTemplate::STATUS_DRAFT,
                'settings' => [
                    'rating_scale' => $definition['rating_scale'] ?? null,
                    'source' => $definition['source'] ?? null,
                ],
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $this->templates->replaceStructure($template, $definition, $request->user()->id);

            return $template;
        });

        return redirect()
            ->route('biannual-site-visits.templates.edit', $template)
            ->with('success', 'Workbook imported successfully. Review the draft before publishing.');
    }

    public function edit(
        Request $request,
        BiAnnualSiteVisitTemplate $template
    ): View {
        $this->authorizeManage($request->user());
        abort_unless($template->isDraft(), 422, 'Duplicate a published template before editing it.');

        $structure = $this->templates->builderStructure($template);
        $responseTypes = BiAnnualSiteVisitTemplateService::RESPONSE_TYPES;
        $ratingScale = data_get($template->settings, 'rating_scale', []);
        $ratingOptions = is_array($ratingScale) && array_is_list($ratingScale)
            ? $ratingScale
            : data_get($ratingScale, 'options', $this->templates->defaultRatingOptions());
        $ratingScores = collect($ratingOptions)
            ->map(fn (mixed $option): mixed => is_array($option)
                ? ($option['score'] ?? $option['value'] ?? null)
                : null)
            ->filter(fn (mixed $score): bool => is_numeric($score))
            ->map(fn (mixed $score): float => (float) $score);
        $builderDefaults = [
            'minimum_score' => (float) data_get(
                $ratingScale,
                'minimum',
                $ratingScores->min() ?? 0
            ),
            'maximum_score' => (float) data_get(
                $ratingScale,
                'maximum',
                $ratingScores->max() ?? 3
            ),
            'options' => is_array($ratingOptions) ? array_values($ratingOptions) : [],
            'allows_na' => collect($ratingOptions)->contains(
                fn (mixed $option): bool => is_array($option)
                    && (
                        (bool) ($option['is_not_applicable'] ?? $option['is_na'] ?? false)
                        || in_array(
                            Str::lower(trim((string) ($option['label'] ?? ''))),
                            ['na', 'n/a', 'not applicable', 'not_applicable'],
                            true
                        )
                    )
            ),
        ];

        return view(
            'biannual-site-visits.templates.edit',
            compact('template', 'structure', 'responseTypes', 'builderDefaults')
        );
    }

    public function update(
        Request $request,
        BiAnnualSiteVisitTemplate $template
    ): RedirectResponse {
        $this->authorizeManage($request->user());
        abort_unless($template->isDraft(), 422, 'Published questionnaire versions are immutable.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'instructions' => ['nullable', 'string', 'max:20000'],
            'structure' => ['required', 'string'],
        ]);

        try {
            $structure = json_decode($validated['structure'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'structure' => 'The questionnaire builder data is invalid. Reload the page and try again.',
            ]);
        }

        if (! is_array($structure)) {
            throw ValidationException::withMessages([
                'structure' => 'The questionnaire must contain a valid section structure.',
            ]);
        }

        DB::transaction(function () use ($template, $validated, $structure, $request): void {
            $template->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'instructions' => $validated['instructions'] ?? null,
                'updated_by' => $request->user()->id,
            ]);

            $this->templates->replaceStructure($template, $structure, $request->user()->id);
        });

        return back()->with('success', 'Questionnaire draft saved.');
    }

    public function publish(
        Request $request,
        BiAnnualSiteVisitTemplate $template
    ): RedirectResponse {
        $this->authorizeManage($request->user());
        abort_unless($template->isDraft(), 422, 'This questionnaire version is already locked.');

        $template->loadCount(['sections', 'questions']);
        if ($template->sections_count < 1 || $template->questions_count < 1) {
            throw ValidationException::withMessages([
                'template' => 'Add at least one section, topic, and question before publishing.',
            ]);
        }

        DB::transaction(function () use ($template, $request): void {
            $shouldBeDefault = $template->is_default
                || BiAnnualSiteVisitTemplate::query()
                    ->published()
                    ->where('code', $template->code)
                    ->where('is_default', true)
                    ->exists()
                || ! BiAnnualSiteVisitTemplate::query()->published()->where('is_default', true)->exists();

            BiAnnualSiteVisitTemplate::query()
                ->where('code', $template->code)
                ->where('id', '!=', $template->id)
                ->where('status', BiAnnualSiteVisitTemplate::STATUS_PUBLISHED)
                ->update([
                    'status' => BiAnnualSiteVisitTemplate::STATUS_ARCHIVED,
                    'is_default' => false,
                    'updated_at' => now(),
                ]);

            if ($shouldBeDefault) {
                BiAnnualSiteVisitTemplate::query()->update(['is_default' => false]);
            }

            $template->update([
                'status' => BiAnnualSiteVisitTemplate::STATUS_PUBLISHED,
                'is_default' => $shouldBeDefault,
                'published_by' => $request->user()->id,
                'published_at' => now(),
                'updated_by' => $request->user()->id,
            ]);
        });

        return redirect()
            ->route('biannual-site-visits.templates.index')
            ->with('success', "Version {$template->version} published and locked.");
    }

    public function duplicate(
        Request $request,
        BiAnnualSiteVisitTemplate $template
    ): RedirectResponse {
        $this->authorizeManage($request->user());

        $structure = $this->templates->builderStructure($template);
        $copy = DB::transaction(function () use ($template, $structure, $request) {
            $copy = BiAnnualSiteVisitTemplate::create([
                'code' => $template->code,
                'version' => BiAnnualSiteVisitTemplate::nextVersionForCode($template->code),
                'name' => $template->name,
                'description' => $template->description,
                'instructions' => $template->instructions,
                'status' => BiAnnualSiteVisitTemplate::STATUS_DRAFT,
                'is_default' => false,
                'settings' => $template->settings,
                'visibility' => $template->visibility,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $this->templates->replaceStructure($copy, $structure, $request->user()->id);

            return $copy;
        });

        return redirect()
            ->route('biannual-site-visits.templates.edit', $copy)
            ->with('success', "Editable version {$copy->version} created from version {$template->version}.");
    }

    private function authorizeManage(?User $user): void
    {
        abort_unless($user && $user->can('biannual_site_visits.templates.manage'), 403);
    }

    private function authorizePreview(?User $user, BiAnnualSiteVisitTemplate $template): void
    {
        if (! $template->isPublished()) {
            $this->authorizeManage($user);

            return;
        }

        abort_unless(
            $user
                && (
                    $user->can('biannual_site_visits.create')
                    || $user->can('biannual_site_visits.templates.manage')
                ),
            403,
            'You are not authorized to preview Bi-Annual Site Visit questionnaires.'
        );
    }

    /**
     * @return array{0: ConsortiumThinkTank, 1: string}
     */
    private function previewContext(Request $request): array
    {
        $validated = $request->validate([
            'think_tank_member_id' => [
                'required',
                'uuid',
                Rule::exists('attp_consortium_think_tanks', 'id')
                    ->where('status', 'active'),
            ],
        ]);

        $query = ConsortiumThinkTank::query()
            ->with('consortium.programFunding.program.sector')
            ->where('status', 'active')
            ->whereKey($validated['think_tank_member_id']);

        /** @var ConsortiumThinkTank $thinkTank */
        $thinkTank = $query->firstOrFail();
        $portfolio = $this->branding->portfolioForThinkTank($thinkTank);

        if (! $portfolio) {
            throw ValidationException::withMessages([
                'think_tank_member_id' => 'The selected Think Tank is not linked to a portfolio, so a branded preview cannot be generated.',
            ]);
        }
        $this->assertPreviewGovernanceScope($portfolio, $request->user());

        return [$thinkTank, (string) $portfolio->name];
    }

    private function assertPreviewGovernanceScope(Sector $portfolio, ?User $user): void
    {
        if (! $user || ! $this->userHasSiteVisitPortfolioScope($user)) {
            return;
        }

        $portfolioIds = $this->userHasAssignedPortfolioScope($user)
            ? $this->assignedPortfolioIds($user)
            : [];
        $nodeIds = $this->userHasAssignedPortfolioScope($user)
            ? $this->assignedPortfolioNodeIds($user)
            : array_values(array_filter([(string) $user->governance_node_id]));

        abort_unless(
            in_array((string) $portfolio->id, array_map('strval', $portfolioIds), true)
                || (
                    filled($portfolio->governance_node_id)
                    && in_array(
                        (string) $portfolio->governance_node_id,
                        array_map('strval', $nodeIds),
                        true
                    )
                ),
            403,
            'You do not have access to the selected Think Tank portfolio.'
        );
    }

    private function templateCode(string $value): string
    {
        $code = Str::upper(Str::slug($value, '-'));

        return Str::limit($code ?: 'BIANNUAL-MONITORING', 100, '');
    }

    /**
     * Imported verification rows represent the workbook's assessment checklist.
     * Treat them as required ratings; assessors can still choose N/A and explain.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function requireImportedVerificationQuestions(array $definition): array
    {
        foreach ($definition['sections'] ?? [] as &$section) {
            foreach ($section['topics'] ?? [] as &$topic) {
                foreach ($topic['questions'] ?? [] as &$question) {
                    $question['required'] = true;
                }
                unset($question);
            }
            unset($topic);
        }
        unset($section);

        return $definition;
    }
}
