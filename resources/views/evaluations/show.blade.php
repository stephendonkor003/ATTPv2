@extends('layouts.app')

@section('content')
    @php
        $isServices = $evaluation->usesNumericScoring();
        $isEoi = $evaluation->isEoi();
        $canEdit = $evaluation->status === 'draft';
        $levelLabels = [
            1 => 'Main Section',
            2 => 'Sub-Section',
            3 => 'Sub-Sub Section',
            4 => 'Sub-Sub-Sub Section',
        ];

        $allSections = $evaluation->sections
            ->sortBy(fn ($section) => sprintf(
                '%010d-%s-%s',
                (int) ($section->sort_order ?? 0),
                (string) $section->created_at,
                (string) $section->id,
            ))
            ->values();

        $sectionsByParent = $allSections->groupBy(
            fn ($section) => filled($section->parent_section_id) ? (string) $section->parent_section_id : '__root__'
        );
        $rootSections = $sectionsByParent->get('__root__', collect());
        $criteriaCount = $allSections->sum(fn ($section) => $section->criteria->count());
        $summaryCount = $allSections->where('show_subtotal', true)->count();
        $overallMaximum = $isServices
            ? (float) $allSections->sum(fn ($section) => $section->criteria->sum('max_score'))
            : null;

        $sectionSubtotals = collect();
        $calculateSubtreeMaximum = function ($section) use (&$calculateSubtreeMaximum, &$sectionSubtotals, $sectionsByParent): float {
            $subtotal = (float) $section->criteria->sum('max_score');
            foreach ($sectionsByParent->get((string) $section->id, collect()) as $child) {
                $subtotal += $calculateSubtreeMaximum($child);
            }
            $sectionSubtotals->put((string) $section->id, $subtotal);

            return $subtotal;
        };
        if ($isServices) {
            foreach ($rootSections as $rootSection) {
                $calculateSubtreeMaximum($rootSection);
            }
        }

        $depthById = collect();
        $outlineById = collect();
        $orderedTreeSections = collect();
        $walkDepth = function ($nodes, int $depth = 1, array $parentPath = []) use (&$walkDepth, &$depthById, &$outlineById, &$orderedTreeSections, $sectionsByParent): void {
            foreach ($nodes->values() as $index => $node) {
                $path = array_merge($parentPath, [$index + 1]);
                $depthById->put((string) $node->id, $depth);
                $outlineById->put((string) $node->id, implode('.', $path));
                $orderedTreeSections->push($node);
                $walkDepth(
                    $sectionsByParent->get((string) $node->id, collect()),
                    min(4, $depth + 1),
                    $path,
                );
            }
        };
        $walkDepth($rootSections);

        $descendantIdsBySection = collect();
        $subtreeHeights = collect();
        $subtreeCriteriaCounts = collect();
        $analyzeBranch = function ($section) use (&$analyzeBranch, &$descendantIdsBySection, &$subtreeHeights, &$subtreeCriteriaCounts, $sectionsByParent): array {
            $descendantIds = collect();
            $height = 1;
            $criteriaInBranch = $section->criteria->count();
            foreach ($sectionsByParent->get((string) $section->id, collect()) as $child) {
                [$childDescendants, $childHeight, $childCriteriaCount] = $analyzeBranch($child);
                $descendantIds->push((string) $child->id)->push(...$childDescendants->all());
                $height = max($height, $childHeight + 1);
                $criteriaInBranch += $childCriteriaCount;
            }
            $descendantIdsBySection->put((string) $section->id, $descendantIds);
            $subtreeHeights->put((string) $section->id, $height);
            $subtreeCriteriaCounts->put((string) $section->id, $criteriaInBranch);

            return [$descendantIds, $height, $criteriaInBranch];
        };
        foreach ($rootSections as $rootSection) {
            $analyzeBranch($rootSection);
        }
        $deepestLevel = (int) ($depthById->max() ?? 0);
        $rootFormHasErrors = old('form_context') === 'root-section' && $errors->any();
    @endphp

    <div class="nxl-container evaluation-builder" data-evaluation-builder>
        <section class="builder-hero">
            <div class="builder-hero-main">
                <div class="hero-breadcrumb">
                    <a href="{{ route('evals.cfg.index') }}">Evaluations</a>
                    <i class="feather-chevron-right" aria-hidden="true"></i>
                    <span>Form builder</span>
                </div>
                <div class="d-flex align-items-start gap-3">
                    <div class="hero-icon" aria-hidden="true"><i class="feather-layers"></i></div>
                    <div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="hero-type-badge">{{ $evaluation->typeLabel() }}</span>
                            <span class="status-badge status-{{ $evaluation->status }}">
                                <span></span>{{ ucfirst($evaluation->status) }}
                            </span>
                        </div>
                        <h1>{{ $evaluation->name }}</h1>
                        <p>
                            @if ($isServices)
                                Build a scored evaluation form with up to four clear levels and optional rolled-up subtotals.
                            @elseif ($isEoi)
                                Organize EOI evidence into four levels and optionally show category-count summaries at any section.
                            @else
                                Organize compliance checks into four levels and optionally show Yes/No category summaries.
                            @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="builder-hero-actions">
                <a href="{{ route('evals.cfg.preview', $evaluation) }}" class="btn btn-light" target="_blank">
                    <i class="feather-eye me-1" aria-hidden="true"></i>Preview form
                </a>
                @if ($canEdit)
                    <a href="{{ route('evals.cfg.edit', $evaluation) }}" class="btn btn-outline-light">
                        <i class="feather-edit-3 me-1" aria-hidden="true"></i>Edit details
                    </a>
                @endif
            </div>
        </section>

        @if (session('success'))
            <div class="alert alert-success builder-alert" role="status">
                <i class="feather-check-circle" aria-hidden="true"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger builder-alert" role="alert">
                <i class="feather-alert-circle" aria-hidden="true"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger builder-alert align-items-start" role="alert">
                <i class="feather-alert-triangle mt-1" aria-hidden="true"></i>
                <div>
                    <strong>Please check the highlighted form.</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
        @unless ($canEdit)
            <div class="alert alert-warning builder-alert" role="status">
                <i class="feather-lock" aria-hidden="true"></i>
                <span>This evaluation is <strong>{{ ucfirst($evaluation->status) }}</strong>. You can review and preview its structure, but only draft evaluations can be changed.</span>
            </div>
        @endunless

        <section class="builder-overview" aria-label="Evaluation structure summary">
            <div class="overview-stat">
                <span class="overview-icon icon-blue"><i class="feather-layers" aria-hidden="true"></i></span>
                <div><strong>{{ $allSections->count() }}</strong><span>Total sections</span></div>
            </div>
            <div class="overview-stat">
                <span class="overview-icon icon-violet"><i class="feather-list" aria-hidden="true"></i></span>
                <div><strong data-overall-question-count>{{ $criteriaCount }}</strong><span>Total questions</span></div>
            </div>
            <div class="overview-stat">
                <span class="overview-icon icon-cyan"><i class="feather-git-branch" aria-hidden="true"></i></span>
                <div><strong>{{ $deepestLevel }}</strong><span>of 4 levels used</span></div>
            </div>
            <div class="overview-stat">
                <span class="overview-icon icon-amber"><i class="feather-bar-chart-2" aria-hidden="true"></i></span>
                <div>
                    @if ($isServices)
                        <strong id="overall-total">{{ number_format((float) $overallMaximum, 2) }}</strong><span>Maximum points</span>
                    @else
                        <strong>{{ $summaryCount }}</strong><span>Category summaries</span>
                    @endif
                </div>
            </div>
        </section>

        <section class="structure-guide">
            <div class="structure-guide-copy">
                <span class="guide-icon"><i class="feather-info" aria-hidden="true"></i></span>
                <div>
                    <strong>Start simple. Add depth only where it helps.</strong>
                    <span>Questions can sit at any level. A parent can contain both its own questions and child sections.</span>
                </div>
            </div>
            <div class="tier-route" aria-label="Available hierarchy levels">
                @foreach ($levelLabels as $level => $label)
                    <span class="tier-route-item tier-{{ $level }}" style="--tier-color: var(--tier-{{ $level }})">
                        <b>{{ $level }}</b>{{ $label }}
                    </span>
                    @if ($level < 4)<i class="feather-chevron-right" aria-hidden="true"></i>@endif
                @endforeach
            </div>
        </section>

        @if ($canEdit)
            <section class="root-composer {{ $rootFormHasErrors ? 'has-errors' : '' }}">
                <button type="button" class="root-composer-trigger" data-toggle-panel="root-section-form"
                    aria-expanded="{{ $rootFormHasErrors || $rootSections->isEmpty() ? 'true' : 'false' }}">
                    <span class="root-add-icon"><i class="feather-plus" aria-hidden="true"></i></span>
                    <span>
                        <strong>Add a main section</strong>
                        <small>Begin a new branch of the evaluation form</small>
                    </span>
                    <i class="feather-chevron-down ms-auto" aria-hidden="true"></i>
                </button>
                <div id="root-section-form" class="root-composer-body {{ $rootFormHasErrors || $rootSections->isEmpty() ? '' : 'd-none' }}" data-inline-panel>
                    <form method="POST" action="{{ route('evals.cfg.sec.add', $evaluation) }}"
                        data-section-form data-success-panel="root-section-form">
                        @csrf
                        <input type="hidden" name="form_context" value="root-section">
                        <input type="hidden" name="parent_section_id" value="">
                        <input type="hidden" name="sort_order" value="{{ $rootSections->count() + 1 }}">
                        <div class="row g-3 align-items-start">
                            <div class="col-lg-4">
                                <label class="form-label" for="root-section-name">Section name</label>
                                <input id="root-section-name" name="name" type="text" required maxlength="255"
                                    class="form-control {{ $rootFormHasErrors && $errors->has('name') ? 'is-invalid' : '' }}"
                                    value="{{ $rootFormHasErrors ? old('name') : '' }}" placeholder="e.g. Technical evaluation">
                                @if ($rootFormHasErrors && $errors->has('name'))
                                    <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                                @endif
                            </div>
                            <div class="col-lg-5">
                                <label class="form-label" for="root-section-description">Description <span class="text-muted">(optional)</span></label>
                                <input id="root-section-description" name="description" type="text"
                                    class="form-control {{ $rootFormHasErrors && $errors->has('description') ? 'is-invalid' : '' }}"
                                    value="{{ $rootFormHasErrors ? old('description') : '' }}" placeholder="What should evaluators consider here?">
                                @if ($rootFormHasErrors && $errors->has('description'))
                                    <div class="invalid-feedback">{{ $errors->first('description') }}</div>
                                @endif
                            </div>
                            <div class="col-lg-3 d-grid">
                                <label class="form-label d-none d-lg-block" aria-hidden="true">&nbsp;</label>
                                <button class="btn btn-primary root-submit" type="submit">
                                    <i class="feather-plus me-1" aria-hidden="true"></i>Add main section
                                </button>
                            </div>
                            <div class="col-12">
                                <input type="hidden" name="show_subtotal" value="0">
                                <div class="form-check form-switch subtotal-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" name="show_subtotal" value="1"
                                        id="root-section-subtotal" data-subtotal-toggle aria-describedby="root-section-subtotal-help"
                                        {{ $rootFormHasErrors && old('show_subtotal') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="root-section-subtotal">
                                        {{ $isServices ? 'Show a subtotal after this section' : 'Show a category summary after this section' }}
                                    </label>
                                    <span id="root-section-subtotal-help">
                                        {{ $isServices
                                            ? 'The subtotal rolls up maximum points from this section and every nested child.'
                                            : 'The summary rolls up decision counts from this section and its children without turning categories into scores.' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </section>
        @endif

        <section class="builder-workspace">
                <div class="workspace-toolbar">
                <div>
                    <span class="workspace-kicker">Evaluation outline</span>
                    <h2>Form structure</h2>
                </div>
                @if ($rootSections->isNotEmpty())
                    <div class="workspace-tools">
                        @if ($canEdit)
                            <span class="live-save-state" data-live-save-state>
                                <span aria-hidden="true"></span><span data-live-save-label>Changes save automatically</span>
                            </span>
                        @endif
                        <label class="structure-search" for="structure-search-input">
                            <i class="feather-search" aria-hidden="true"></i>
                            <span class="visually-hidden">Search form structure</span>
                            <input id="structure-search-input" type="search" placeholder="Find a section or question..." data-structure-search>
                        </label>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Tree display controls">
                            <button type="button" class="btn btn-outline-secondary" data-expand-all title="Expand all sections">
                                <i class="feather-maximize-2 me-1" aria-hidden="true"></i>Expand
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-collapse-all title="Collapse all sections">
                                <i class="feather-minimize-2 me-1" aria-hidden="true"></i>Collapse
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            @if ($rootSections->isEmpty())
                <div class="workspace-empty">
                    <span class="empty-illustration"><i class="feather-layers" aria-hidden="true"></i></span>
                    <h3>Your evaluation outline is ready to begin</h3>
                    <p>Add one main section above. You can then place questions directly inside it or add up to three nested levels.</p>
                    @if ($canEdit)
                        <button type="button" class="btn btn-primary" data-toggle-panel="root-section-form">
                            <i class="feather-plus me-1" aria-hidden="true"></i>Add the first section
                        </button>
                    @endif
                </div>
            @else
                <div class="builder-tree" data-builder-tree>
                    @foreach ($rootSections as $rootIndex => $section)
                        @include('evaluations.partials.section-builder-node', [
                            'section' => $section,
                            'level' => 1,
                            'path' => [$rootIndex + 1],
                            'rootIndex' => $rootIndex,
                        ])
                    @endforeach
                </div>
                <div class="search-empty d-none" data-search-empty>
                    <i class="feather-search" aria-hidden="true"></i>
                    <strong>No matching structure found</strong>
                    <span>Try another section or question name.</span>
                </div>
            @endif
        </section>
    </div>

    @include('evaluations.partials.hierarchy-theme')

    <style>
        .evaluation-builder {
            --builder-ink: #172033;
            --builder-muted: #667085;
            --builder-border: #e3e8ef;
            --builder-primary: #3157d5;
            --builder-primary-dark: #2444b2;
            --tier-1: #3157d5;
            --tier-2: #6f4ad8;
            --tier-3: #0f8a9d;
            --tier-4: #c56a16;
            color: var(--builder-ink);
            padding-bottom: 3rem;
        }

        .builder-hero { display: flex; justify-content: space-between; gap: 2rem; margin: 0 0 1.25rem; padding: 1.65rem 1.75rem; color: #fff; border-radius: 18px; background: radial-gradient(circle at 86% 14%, rgba(255,255,255,.18), transparent 27%), linear-gradient(126deg, #17296b 0%, #3157d5 58%, #4b72ea 100%); box-shadow: 0 18px 42px rgba(35,68,178,.18); }
        .builder-hero-main { min-width: 0; }
        .hero-breadcrumb { display: flex; align-items: center; gap: .35rem; margin-bottom: 1.15rem; font-size: .76rem; color: rgba(255,255,255,.72); }
        .hero-breadcrumb a { color: #fff; text-decoration: none; }
        .hero-icon { display: grid; flex: 0 0 48px; width: 48px; height: 48px; place-items: center; border: 1px solid rgba(255,255,255,.2); border-radius: 14px; background: rgba(255,255,255,.12); font-size: 1.25rem; }
        .builder-hero h1 { margin: 0; color: #fff; font-size: clamp(1.35rem, 2vw, 2rem); font-weight: 750; letter-spacing: -.025em; }
        .builder-hero p { max-width: 760px; margin: .45rem 0 0; color: rgba(255,255,255,.76); font-size: .9rem; }
        .hero-type-badge, .status-badge { display: inline-flex; align-items: center; gap: .35rem; padding: .28rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 700; letter-spacing: .035em; text-transform: uppercase; }
        .hero-type-badge { color: #203b98; background: #fff; }
        .status-badge { color: #fff; background: rgba(255,255,255,.13); }
        .status-badge > span { width: 6px; height: 6px; border-radius: 50%; background: #b9c6ff; }
        .status-draft > span { background: #ffd369; }
        .status-active > span { background: #77e1a7; }
        .builder-hero-actions { display: flex; align-items: flex-start; gap: .6rem; flex: 0 0 auto; padding-top: 2rem; }
        .builder-hero-actions .btn { white-space: nowrap; border-radius: 9px; font-weight: 600; }
        .builder-alert { display: flex; align-items: center; gap: .7rem; border: 0; border-radius: 12px; }

        .builder-overview { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .8rem; margin-bottom: 1rem; }
        .overview-stat { display: flex; align-items: center; gap: .8rem; min-width: 0; padding: 1rem; border: 1px solid var(--builder-border); border-radius: 13px; background: #fff; box-shadow: 0 6px 20px rgba(20,34,66,.045); }
        .overview-icon { display: grid; flex: 0 0 40px; width: 40px; height: 40px; place-items: center; border-radius: 11px; }
        .icon-blue { color: #3157d5; background: #edf1ff; }
        .icon-violet { color: #7047d7; background: #f3efff; }
        .icon-cyan { color: #087e90; background: #e9f9fb; }
        .icon-amber { color: #b65c0b; background: #fff4e7; }
        .overview-stat div { min-width: 0; }
        .overview-stat strong { display: block; font-size: 1.17rem; line-height: 1.1; }
        .overview-stat div > span { color: var(--builder-muted); font-size: .74rem; }

        .structure-guide { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; padding: .85rem 1rem; border: 1px solid #dbe3ff; border-radius: 13px; background: #f8faff; }
        .structure-guide-copy { display: flex; align-items: center; gap: .7rem; min-width: 0; }
        .structure-guide-copy > div { display: flex; flex-direction: column; }
        .structure-guide-copy strong { font-size: .82rem; }
        .structure-guide-copy span { color: var(--builder-muted); font-size: .73rem; }
        .guide-icon { display: grid; flex: 0 0 34px; width: 34px; height: 34px; place-items: center; color: var(--builder-primary); border-radius: 9px; background: #e9eeff; }
        .tier-route { display: flex; align-items: center; gap: .3rem; flex: 0 0 auto; }
        .tier-route > i { color: #aab2c2; font-size: .7rem; }
        .tier-route-item { display: inline-flex; align-items: center; gap: .32rem; padding: .3rem .48rem; color: var(--builder-muted); border: 1px solid var(--builder-border); border-radius: 8px; background: #fff; font-size: .65rem; font-weight: 600; white-space: nowrap; }
        .tier-route-item b { display: grid; width: 17px; height: 17px; place-items: center; color: #fff; border-radius: 5px; background: var(--tier-color); font-size: .6rem; }

        .root-composer { margin-bottom: 1rem; overflow: hidden; border: 1px dashed #b9c7f2; border-radius: 14px; background: #fbfcff; }
        .root-composer.has-errors { border-color: #dc3545; }
        .root-composer-trigger { display: flex; width: 100%; align-items: center; gap: .75rem; padding: .85rem 1rem; color: var(--builder-ink); border: 0; background: transparent; text-align: left; }
        .root-composer-trigger:hover { background: #f5f7ff; }
        .root-composer-trigger > span:nth-child(2) { display: flex; flex-direction: column; }
        .root-composer-trigger small { color: var(--builder-muted); font-size: .72rem; }
        .root-add-icon { display: grid; width: 34px; height: 34px; place-items: center; color: #fff; border-radius: 9px; background: var(--builder-primary); box-shadow: 0 5px 12px rgba(49,87,213,.22); }
        .root-composer-body { padding: 1rem; border-top: 1px solid #e3e8f6; background: #fff; }
        .root-submit { min-height: 40px; }

        .subtotal-switch { display: grid; grid-template-columns: auto 1fr; column-gap: .45rem; padding: .65rem .75rem .65rem 2.8rem; border: 1px solid #e5e9f2; border-radius: 10px; background: #fbfcfe; }
        .subtotal-switch .form-check-input { grid-row: 1 / span 2; margin-left: -2rem; }
        .subtotal-switch label { color: var(--builder-ink); font-size: .8rem; font-weight: 650; }
        .subtotal-switch span { color: var(--builder-muted); font-size: .7rem; }

        .builder-workspace { overflow: visible; border: 1px solid var(--builder-border); border-radius: 16px; background: #f6f8fb; box-shadow: 0 8px 28px rgba(20,34,66,.055); }
        .workspace-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.15rem; border-bottom: 1px solid var(--builder-border); border-radius: 16px 16px 0 0; background: #fff; }
        .workspace-kicker, .criteria-eyebrow, .inline-panel-kicker { display: block; color: var(--builder-primary); font-size: .62rem; font-weight: 750; letter-spacing: .08em; text-transform: uppercase; }
        .workspace-toolbar h2 { margin: .12rem 0 0; font-size: 1.05rem; font-weight: 750; }
        .workspace-tools { display: flex; align-items: center; gap: .55rem; }
        .live-save-state { display: inline-flex; align-items: center; gap: .35rem; padding: .35rem .55rem; color: #18794e; border: 1px solid #d5ecdf; border-radius: 999px; background: #f3fbf7; font-size: .62rem; font-weight: 650; white-space: nowrap; }
        .live-save-state > span:first-child { width: 6px; height: 6px; border-radius: 50%; background: #34a56f; box-shadow: 0 0 0 3px rgba(52,165,111,.12); }
        .live-save-state.is-busy { color: #7b5b0c; border-color: #f0dfae; background: #fffaf0; }
        .live-save-state.is-busy > span:first-child { background: #d79b1d; animation: savePulse .8s ease-in-out infinite alternate; }
        @keyframes savePulse { to { opacity: .35; } }
        .structure-search { display: flex; align-items: center; gap: .4rem; min-width: 235px; padding: .42rem .65rem; border: 1px solid var(--builder-border); border-radius: 9px; background: #fafbfc; }
        .structure-search:focus-within { border-color: #8ba2ee; box-shadow: 0 0 0 3px rgba(49,87,213,.08); }
        .structure-search i { color: #98a2b3; }
        .structure-search input { width: 100%; padding: 0; border: 0; outline: 0; background: transparent; font-size: .75rem; }
        .builder-tree { padding: 1rem; }
        .builder-node { --tier-color: var(--section-color, var(--tier-1)); position: relative; }
        .builder-node + .builder-node { margin-top: .8rem; }
        .builder-node-shell { overflow: visible; border: 1px solid var(--builder-border); border-left: 3px solid color-mix(in srgb, var(--tier-color) 68%, white); border-radius: 13px; background: #fff; box-shadow: 0 4px 14px rgba(20,34,66,.04); }
        .builder-node.level-1 > .builder-node-shell { overflow: hidden; border-top: 4px solid var(--tier-color); border-left-width: 1px; box-shadow: 0 9px 25px color-mix(in srgb, var(--tier-color) 9%, transparent); }
        .builder-node.is-highlighted > .builder-node-shell { animation: sectionHighlight 1.55s ease; }
        @keyframes sectionHighlight { 0%, 100% { box-shadow: 0 9px 25px color-mix(in srgb, var(--tier-color) 9%, transparent); } 28% { box-shadow: 0 0 0 5px color-mix(in srgb, var(--tier-color) 22%, transparent), 0 12px 30px color-mix(in srgb, var(--tier-color) 18%, transparent); } }
        .builder-node-header { display: grid; grid-template-columns: 26px 36px minmax(180px, 1fr) auto auto; align-items: center; gap: .65rem; min-height: 76px; padding: .75rem .8rem; }
        .builder-node.level-1 > .builder-node-shell > .builder-node-header { background: linear-gradient(90deg, var(--section-soft, #eef2ff), #fff 58%); }
        .builder-node.level-2 > .builder-node-shell > .builder-node-header { background: color-mix(in srgb, var(--section-soft, #eef2ff) 55%, white); }
        .builder-node.level-3 > .builder-node-shell > .builder-node-header { background: color-mix(in srgb, var(--section-soft, #eef2ff) 34%, white); }
        .node-collapse-btn { display: grid; width: 26px; height: 26px; place-items: center; padding: 0; color: #667085; border: 0; border-radius: 7px; background: #f2f4f7; transition: transform .18s ease; }
        .builder-node.is-collapsed .node-collapse-btn { transform: rotate(-90deg); }
        .level-marker { display: grid; min-width: 34px; height: 34px; padding: 0 .25rem; place-items: center; color: #fff; border-radius: 9px; background: var(--tier-color); font-size: .67rem; font-weight: 750; }
        .node-heading { min-width: 0; }
        .node-title { margin: 0; overflow: hidden; font-size: .92rem; font-weight: 750; line-height: 1.25; text-overflow: ellipsis; }
        .node-description { margin: .2rem 0 0; overflow: hidden; color: var(--builder-muted); font-size: .7rem; line-height: 1.35; text-overflow: ellipsis; white-space: nowrap; }
        .tier-badge { display: inline-flex; padding: .18rem .42rem; color: var(--tier-color); border-radius: 6px; background: color-mix(in srgb, var(--tier-color) 10%, white); font-size: .57rem; font-weight: 750; letter-spacing: .055em; text-transform: uppercase; }
        .node-path { color: #98a2b3; font-size: .62rem; font-weight: 650; }
        .node-meta { display: flex; align-items: center; justify-content: flex-end; gap: .35rem; flex-wrap: wrap; max-width: 310px; }
        .meta-pill, .subtotal-pill { display: inline-flex; align-items: center; gap: .28rem; padding: .3rem .46rem; border: 1px solid #e7eaf0; border-radius: 7px; color: #667085; background: #fafbfc; font-size: .63rem; white-space: nowrap; }
        .meta-divider { color: #c0c6d0; }
        .subtotal-control { display: inline-flex; align-items: center; gap: .25rem; }
        .subtotal-pill.is-enabled { color: #176b43; border-color: #ccebdc; background: #eefaf4; }
        .subtotal-pill.is-disabled { color: #8a94a4; background: #f7f8fa; }
        .subtotal-edit-btn { display: inline-flex; align-items: center; gap: .2rem; min-height: 27px; padding: .28rem .42rem; color: var(--builder-primary); border: 1px solid #d7e0fb; border-radius: 7px; background: #f6f8ff; font-size: .61rem; font-weight: 700; line-height: 1; white-space: nowrap; }
        .subtotal-edit-btn:hover { color: #fff; border-color: var(--builder-primary); background: var(--builder-primary); }
        .subtotal-edit-btn:focus-visible { outline: 0; box-shadow: 0 0 0 3px rgba(49,87,213,.18); }
        .node-actions { display: flex; align-items: center; gap: .35rem; }
        .node-actions .btn { white-space: nowrap; }
        .node-actions form { margin: 0; }
        .node-delete-form { display: inline-flex; }
        .btn-primary-soft { color: var(--section-deep, var(--builder-primary)); border-color: color-mix(in srgb, var(--tier-color) 22%, white); background: var(--section-soft, #eef2ff); }
        .btn-primary-soft:hover { color: #fff; border-color: var(--tier-color); background: var(--tier-color); }
        .btn-icon { display: inline-grid; width: 31px; height: 31px; padding: 0; place-items: center; }
        .builder-node-content { border-top: 1px solid #edf0f4; }
        .builder-node.is-collapsed > .builder-node-shell > .builder-node-content, .builder-node.is-collapsed > .builder-children { display: none; }
        .builder-children { position: relative; margin: .7rem 0 0 2.15rem; padding-left: 1.25rem; }
        .builder-children::before { position: absolute; top: -.7rem; bottom: .4rem; left: 0; width: 2px; border-radius: 999px; background: color-mix(in srgb, var(--tier-color) 24%, #d8dee8); content: ''; }
        .builder-children > .builder-node::before { position: absolute; top: 37px; left: -1.25rem; width: 1.25rem; height: 2px; background: color-mix(in srgb, var(--tier-color) 24%, #d8dee8); content: ''; }

        .inline-panel { margin: .8rem; overflow: hidden; border: 1px solid #dfe5f0; border-radius: 11px; background: #fbfcff; }
        .inline-panel-heading { display: flex; align-items: center; justify-content: space-between; gap: .8rem; padding: .7rem .85rem; border-bottom: 1px solid #e7ebf2; background: #f5f7fc; }
        .inline-panel-heading strong { display: block; font-size: .78rem; }
        .section-inline-form, .criteria-bulk-form { padding: .85rem; }
        .inline-panel-footer { display: flex; justify-content: flex-end; gap: .5rem; margin-top: .8rem; padding-top: .7rem; border-top: 1px solid #e8ecf2; }
        .evaluation-builder .form-label { margin-bottom: .3rem; color: #475467; font-size: .7rem; font-weight: 650; }
        .evaluation-builder .form-control, .evaluation-builder .form-select { border-color: #d8dee8; border-radius: 8px; font-size: .78rem; }
        .evaluation-builder .form-control:focus, .evaluation-builder .form-select:focus { border-color: #8199e9; box-shadow: 0 0 0 3px rgba(49,87,213,.08); }
        .evaluation-builder .form-text { color: var(--builder-muted); font-size: .64rem; }

        .criteria-composer { background: #fff; }
        .criteria-draft-row { display: grid; grid-template-columns: minmax(180px, 1fr) minmax(220px, 1.35fr) 110px 34px; gap: .55rem; align-items: end; padding: .55rem 0; border-bottom: 1px solid #edf0f4; }
        .criteria-draft-row:not(.has-score) { grid-template-columns: minmax(180px, .9fr) minmax(240px, 1.4fr) 34px; }
        .draft-field label { display: block; }
        .criteria-composer-actions { display: flex; align-items: center; gap: .6rem; padding-top: .7rem; }
        .criteria-feedback { color: var(--builder-muted); font-size: .67rem; }
        .criteria-feedback.is-success { color: #18794e; }
        .criteria-feedback.is-error { color: #c0392b; }

        .criteria-block { padding: .75rem .85rem .85rem; }
        .criteria-block-heading { display: flex; align-items: center; justify-content: space-between; margin-bottom: .45rem; }
        .criteria-block-heading > div { display: flex; align-items: center; gap: .55rem; }
        .criteria-helper { color: var(--builder-muted); font-size: .65rem; }
        .criteria-list { overflow: hidden; border: 1px solid #e7ebf0; border-radius: 9px; }
        .criteria-empty { display: flex; align-items: center; justify-content: center; gap: .45rem; min-height: 48px; color: #98a2b3; background: #fbfcfd; font-size: .7rem; }
        .criterion-item { display: grid; grid-template-columns: 26px minmax(0, 1fr) auto auto; align-items: center; gap: .65rem; min-height: 52px; padding: .55rem .65rem; background: #fff; }
        .criterion-item + .criterion-item { border-top: 1px solid #edf0f3; }
        .criterion-item:hover { background: #fcfdff; }
        .criterion-index { display: grid; width: 25px; height: 25px; place-items: center; color: #667085; border-radius: 7px; background: #f1f3f6; font-size: .62rem; font-weight: 700; }
        .criterion-main { min-width: 0; }
        .criterion-name { display: block; overflow: hidden; font-size: .75rem; line-height: 1.3; text-overflow: ellipsis; white-space: nowrap; }
        .criterion-main p { margin: .14rem 0 0; overflow: hidden; color: var(--builder-muted); font-size: .65rem; line-height: 1.3; text-overflow: ellipsis; white-space: nowrap; }
        .criterion-score { display: flex; flex-direction: column; min-width: 58px; padding: .28rem .42rem; color: var(--section-deep, var(--builder-primary)); border-radius: 7px; background: var(--section-soft, #eef2ff); text-align: right; }
        .criterion-score span { font-size: .72rem; font-weight: 750; }
        .criterion-score small { color: #7889bc; font-size: .55rem; text-transform: uppercase; }
        .criterion-actions { display: flex; align-items: center; gap: .25rem; }
        .criterion-actions form { display: inline-flex; margin: 0; }
        .criterion-edit-grid { display: grid; grid-template-columns: minmax(160px,.8fr) minmax(220px,1.2fr) 100px; gap: .5rem; }
        .criterion-edit-grid > div:last-child:nth-child(2) { grid-column: auto; }

        .workspace-empty, .search-empty { display: flex; flex-direction: column; align-items: center; padding: 3rem 1rem; text-align: center; }
        .empty-illustration { display: grid; width: 64px; height: 64px; margin-bottom: 1rem; place-items: center; color: var(--builder-primary); border-radius: 20px; background: #eaf0ff; font-size: 1.45rem; }
        .workspace-empty h3, .search-empty strong { margin: 0; font-size: 1rem; }
        .workspace-empty p { max-width: 540px; margin: .4rem 0 1rem; color: var(--builder-muted); font-size: .75rem; }
        .search-empty { color: var(--builder-muted); gap: .25rem; }
        .search-hidden { display: none !important; }
        [data-section-form][aria-busy="true"], [data-ajax-delete][aria-busy="true"] { opacity: .68; pointer-events: none; }
        .builder-toast-stack { position: fixed; z-index: 1095; right: 1rem; bottom: 1rem; display: grid; width: min(360px, calc(100vw - 2rem)); gap: .5rem; pointer-events: none; }
        .builder-toast { display: flex; align-items: center; gap: .6rem; padding: .78rem .85rem; color: #fff; border-radius: 11px; background: #18794e; box-shadow: 0 14px 35px rgba(20,34,66,.22); opacity: 0; transform: translateY(10px); transition: opacity .2s ease, transform .2s ease; font-size: .73rem; font-weight: 650; }
        .builder-toast.is-error { background: #b42318; }
        .builder-toast.is-visible { opacity: 1; transform: translateY(0); }

        @supports not (background: color-mix(in srgb, red 10%, white)) { .tier-badge { background: #f1f3f8; } }
        @media (max-width: 1199px) {
            .builder-node-header { grid-template-columns: 26px 36px minmax(180px, 1fr) auto; }
            .node-meta { grid-column: 3 / 4; justify-content: flex-start; max-width: none; }
            .node-actions { grid-column: 4; grid-row: 1 / span 2; }
            .structure-guide { align-items: flex-start; flex-direction: column; }
        }
        @media (max-width: 991px) {
            .builder-hero { align-items: flex-start; flex-direction: column; }
            .builder-hero-actions { padding-top: 0; }
            .builder-overview { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .workspace-toolbar { align-items: flex-start; flex-direction: column; }
            .workspace-tools { width: 100%; }
            .structure-search { flex: 1; }
            .tier-route { flex-wrap: wrap; }
            .criteria-draft-row, .criteria-draft-row:not(.has-score) { grid-template-columns: 1fr 1fr; }
            .criteria-draft-remove { justify-self: end; }
        }
        @media (max-width: 767px) {
            .builder-hero { padding: 1.2rem; border-radius: 14px; }
            .builder-hero-actions { width: 100%; flex-wrap: wrap; }
            .builder-overview { grid-template-columns: 1fr 1fr; }
            .overview-stat { padding: .75rem; }
            .tier-route > i { display: none; }
            .workspace-tools { align-items: stretch; flex-direction: column; }
            .builder-tree { padding: .65rem; }
            .builder-node-header { grid-template-columns: 26px 34px minmax(0,1fr); }
            .node-meta { grid-column: 2 / 4; }
            .node-actions { grid-column: 2 / 4; grid-row: auto; justify-content: flex-start; }
            .builder-children { margin-left: .65rem; padding-left: .75rem; }
            .builder-children > .builder-node::before { left: -.75rem; width: .75rem; }
            .criteria-block-heading > div { align-items: flex-start; flex-direction: column; gap: .15rem; }
            .criterion-item { grid-template-columns: 25px minmax(0,1fr) auto; }
            .criterion-score { grid-column: 3; }
            .criterion-actions { grid-column: 2 / 4; justify-content: flex-end; }
            .criterion-edit-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 520px) {
            .builder-overview { grid-template-columns: 1fr; }
            .hero-icon { display: none; }
            .criteria-draft-row, .criteria-draft-row:not(.has-score) { grid-template-columns: 1fr; }
            .criteria-composer-actions { align-items: stretch; flex-direction: column; }
            .criteria-composer-actions .ms-auto { width: 100%; margin-left: 0 !important; align-items: stretch !important; flex-direction: column; }
        }
    </style>

    <script>
        (() => {
            const builder = document.querySelector('[data-evaluation-builder]');
            if (!builder) return;
            const csrfToken = @json(csrf_token());
            const isNumeric = @json($isServices);
            const escapeHtml = value => String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
            const extractError = payload => {
                if (payload?.errors && typeof payload.errors === 'object') {
                    const first = Object.values(payload.errors).flat()[0];
                    if (first) return first;
                }
                if (payload?.message) return payload.message;
                return null;
            };

            function setLiveState(busy, text = '') {
                const state = builder.querySelector('[data-live-save-state]');
                if (!state) return;
                state.classList.toggle('is-busy', busy);
                const label = state.querySelector('[data-live-save-label]');
                if (label) label.textContent = busy ? (text || 'Saving changes...') : 'Changes save automatically';
            }

            function showToast(message, type = 'success') {
                let stack = document.querySelector('[data-builder-toast-stack]');
                if (!stack) {
                    stack = document.createElement('div');
                    stack.className = 'builder-toast-stack';
                    stack.dataset.builderToastStack = '';
                    stack.setAttribute('aria-live', 'polite');
                    document.body.appendChild(stack);
                }
                const toast = document.createElement('div');
                toast.className = `builder-toast is-${type}`;
                toast.innerHTML = `<i class="${type === 'success' ? 'feather-check-circle' : 'feather-alert-circle'}" aria-hidden="true"></i><span>${escapeHtml(message)}</span>`;
                stack.appendChild(toast);
                window.requestAnimationFrame(() => toast.classList.add('is-visible'));
                window.setTimeout(() => {
                    toast.classList.remove('is-visible');
                    window.setTimeout(() => toast.remove(), 220);
                }, 3600);
            }

            async function submitJsonForm(form) {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(extractError(payload) || 'Unable to save this change.');
                return payload;
            }

            function setFormBusy(form, busy) {
                form.setAttribute('aria-busy', busy ? 'true' : 'false');
                form.querySelectorAll('button[type="submit"]').forEach(button => {
                    if (!button.dataset.originalHtml) button.dataset.originalHtml = button.innerHTML;
                    button.disabled = busy;
                    button.innerHTML = busy
                        ? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Saving...'
                        : button.dataset.originalHtml;
                });
            }

            function initializeDynamicForms(scope = builder) {
                scope.querySelectorAll('.criteria-bulk-form').forEach(form => {
                    if (!form.querySelector('.criteria-draft-row')) buildDraftRow(form);
                });
            }

            async function refreshBuilderStructure(focusSectionId = null) {
                setLiveState(true, 'Refreshing structure...');
                const response = await fetch(window.location.href, {
                    headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) throw new Error('The change was saved, but the form outline could not be refreshed.');

                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                ['.builder-overview', '.root-composer', '.builder-workspace'].forEach(selector => {
                    const current = builder.querySelector(selector);
                    const incoming = page.querySelector(`[data-evaluation-builder] ${selector}`);
                    if (current && incoming) current.replaceWith(incoming);
                });

                initializeDynamicForms();
                calculateQuestionCounts();
                calculateTotals();
                setLiveState(false);

                if (focusSectionId) {
                    const focused = builder.querySelector(`[data-section-id="${focusSectionId}"]`);
                    if (focused) {
                        focused.classList.add('is-highlighted');
                        focused.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        window.setTimeout(() => focused.classList.remove('is-highlighted'), 1600);
                    }
                }
            }

            function setPanel(id, open, focusTargetId = null) {
                const panel = document.getElementById(id);
                if (!panel) return;
                panel.classList.toggle('d-none', !open);
                builder.querySelectorAll(`[data-toggle-panel="${id}"]`).forEach(button => button.setAttribute('aria-expanded', open ? 'true' : 'false'));
                if (open) {
                    const node = panel.closest('[data-section-node]');
                    if (node) setNodeCollapsed(node, false);
                    window.requestAnimationFrame(() => {
                        const requestedTarget = focusTargetId ? document.getElementById(focusTargetId) : null;
                        const focusTarget = requestedTarget && panel.contains(requestedTarget)
                            ? requestedTarget
                            : panel.querySelector('input:not([type="hidden"])');
                        focusTarget?.focus();
                    });
                }
            }

            function setNodeCollapsed(node, collapsed) {
                node.classList.toggle('is-collapsed', collapsed);
                const button = node.querySelector(':scope > .builder-node-shell > .builder-node-header [data-collapse-node]');
                button?.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                if (button) button.title = `${collapsed ? 'Expand' : 'Collapse'} section`;
            }

            function buildDraftRow(form) {
                const row = document.createElement('div');
                row.className = `criteria-draft-row ${form.dataset.services === '1' ? 'has-score' : ''}`;
                const uid = `${form.dataset.sectionId}-${Date.now()}-${Math.random().toString(16).slice(2)}`;
                row.innerHTML = `
                    <div class="draft-field"><label class="form-label" for="draft-name-${uid}">Question</label><input id="draft-name-${uid}" type="text" class="form-control form-control-sm draft-name" placeholder="What should be evaluated?" maxlength="255"></div>
                    <div class="draft-field"><label class="form-label" for="draft-description-${uid}">Description <span class="text-muted">(optional)</span></label><input id="draft-description-${uid}" type="text" class="form-control form-control-sm draft-description" placeholder="Add concise evaluator guidance"></div>
                    ${form.dataset.services === '1' ? `<div class="draft-field"><label class="form-label" for="draft-score-${uid}">Max score</label><input id="draft-score-${uid}" type="number" min="1" step="0.01" class="form-control form-control-sm draft-max-score" placeholder="0.00"></div>` : ''}
                    <button type="button" class="btn btn-sm btn-icon btn-outline-danger btn-remove-draft-row criteria-draft-remove" title="Remove row" aria-label="Remove question row"><i class="feather-x" aria-hidden="true"></i></button>`;
                form.querySelector('.criteria-draft-body')?.appendChild(row);
            }

            function setCriteriaFeedback(form, message, type = '') {
                const feedback = form.querySelector('.criteria-feedback');
                if (!feedback) return;
                feedback.textContent = message;
                feedback.className = `criteria-feedback ${type ? `is-${type}` : ''}`;
            }

            function collectDraftRows(form) {
                const numeric = form.dataset.services === '1';
                let valid = true;
                const rows = Array.from(form.querySelectorAll('.criteria-draft-row')).map(row => {
                    const nameInput = row.querySelector('.draft-name');
                    const scoreInput = row.querySelector('.draft-max-score');
                    const name = nameInput?.value.trim() ?? '';
                    const description = row.querySelector('.draft-description')?.value.trim() ?? '';
                    const score = scoreInput?.value ?? '';
                    const hasAnyValue = name !== '' || description !== '' || score !== '';
                    nameInput?.classList.toggle('is-invalid', hasAnyValue && name === '');
                    scoreInput?.classList.toggle('is-invalid', numeric && hasAnyValue && Number(score) <= 0);
                    if (hasAnyValue && (name === '' || (numeric && Number(score) <= 0))) valid = false;
                    return hasAnyValue ? { name, description, max_score: numeric ? score : null } : null;
                }).filter(Boolean);
                return { rows, valid };
            }

            function criterionMarkup(criterion, numeric) {
                const description = criterion.description?.trim() || '';
                return `<div class="criterion-index"></div><div class="criterion-main"><div data-criterion-view><strong class="criterion-name" data-criterion-name>${escapeHtml(criterion.name)}</strong><p class="${description ? '' : 'text-muted'}" data-criterion-description>${escapeHtml(description || 'No description provided')}</p></div><div class="criterion-edit-grid d-none" data-criterion-edit><div><label class="form-label">Criterion</label><input class="form-control form-control-sm criterion-edit-name" value="${escapeHtml(criterion.name)}" maxlength="255" required></div><div><label class="form-label">Description</label><input class="form-control form-control-sm criterion-edit-description" value="${escapeHtml(description)}"></div>${numeric ? `<div><label class="form-label">Max score</label><input type="number" min="1" step="0.01" class="form-control form-control-sm criterion-edit-score" value="${escapeHtml(criterion.max_score)}" required></div>` : ''}</div><div class="criterion-feedback" role="status" aria-live="polite"></div></div>${numeric ? `<div class="criterion-score"><span data-criterion-score>${Number(criterion.max_score || 0).toFixed(2)}</span><small>max</small></div>` : ''}<div class="criterion-actions"><button type="button" class="btn btn-sm btn-icon btn-outline-secondary btn-edit-criterion" title="Edit criterion" aria-label="Edit criterion"><i class="feather-edit-2" aria-hidden="true"></i></button><button type="button" class="btn btn-sm btn-success btn-save-criterion d-none">Save</button><button type="button" class="btn btn-sm btn-light btn-cancel-criterion d-none">Cancel</button><form method="POST" action="${escapeHtml(criterion.delete_url)}" data-ajax-delete data-delete-kind="criterion" data-confirm="Delete this criterion?"><input type="hidden" name="_token" value="${escapeHtml(csrfToken)}"><input type="hidden" name="_method" value="DELETE"><button class="btn btn-sm btn-icon btn-outline-danger" type="submit" title="Delete criterion" aria-label="Delete criterion"><i class="feather-trash-2" aria-hidden="true"></i></button></form></div>`;
            }

            function refreshCriterionIndexes(node) {
                const items = node.querySelectorAll(':scope > .builder-node-shell > .builder-node-content [data-criteria-list] > [data-criterion-item]');
                items.forEach((item, index) => item.querySelector('.criterion-index').textContent = index + 1);
                node.querySelector(':scope > .builder-node-shell [data-direct-criteria-count]')?.replaceChildren(document.createTextNode(items.length));
                node.querySelector(':scope > .builder-node-shell [data-criteria-empty]')?.classList.toggle('d-none', items.length > 0);
            }

            function calculateNodeQuestionCount(node) {
                const direct = node.querySelectorAll(':scope > .builder-node-shell > .builder-node-content [data-criteria-list] > [data-criterion-item]').length;
                let total = direct;
                const children = node.querySelector(':scope > [data-builder-children]');
                if (children) {
                    Array.from(children.children)
                        .filter(child => child.matches('[data-section-node]'))
                        .forEach(child => { total += calculateNodeQuestionCount(child); });
                }
                const directOutput = node.querySelector(':scope > .builder-node-shell > .builder-node-header [data-direct-criteria-count]');
                const totalOutput = node.querySelector(':scope > .builder-node-shell > .builder-node-header [data-node-total-criteria]');
                if (directOutput) directOutput.textContent = direct;
                if (totalOutput) totalOutput.textContent = total;
                return total;
            }

            function calculateQuestionCounts() {
                const tree = builder.querySelector('[data-builder-tree]');
                let overall = 0;
                if (tree) {
                    Array.from(tree.children)
                        .filter(child => child.matches('[data-section-node]'))
                        .forEach(node => { overall += calculateNodeQuestionCount(node); });
                }
                const output = builder.querySelector('[data-overall-question-count]');
                if (output) output.textContent = overall;
            }

            function appendCriterion(sectionId, criterion, numeric) {
                const node = document.querySelector(`[data-section-id="${sectionId}"]`);
                const list = node?.querySelector(':scope > .builder-node-shell > .builder-node-content [data-criteria-list]');
                if (!node || !list) return;
                const item = document.createElement('div');
                item.className = 'criterion-item';
                item.dataset.criterionItem = '';
                item.dataset.updateUrl = criterion.update_url;
                item.innerHTML = criterionMarkup(criterion, numeric);
                list.appendChild(item);
                refreshCriterionIndexes(node);
                calculateQuestionCounts();
            }

            function calculateNodeTotal(node) {
                let total = Array.from(node.querySelectorAll(':scope > .builder-node-shell > .builder-node-content [data-criteria-list] > [data-criterion-item] [data-criterion-score]')).reduce((sum, score) => sum + (Number.parseFloat(score.textContent) || 0), 0);
                const childContainer = node.querySelector(':scope > [data-builder-children]');
                if (childContainer) Array.from(childContainer.children).filter(child => child.matches('[data-section-node]')).forEach(child => total += calculateNodeTotal(child));
                const output = node.querySelector(':scope > .builder-node-shell > .builder-node-header [data-node-subtotal]');
                if (output) output.textContent = total.toFixed(2);
                return total;
            }

            function calculateTotals() {
                if (!isNumeric) return;
                const tree = builder.querySelector('[data-builder-tree]');
                let overall = 0;
                if (tree) Array.from(tree.children).filter(child => child.matches('[data-section-node]')).forEach(node => overall += calculateNodeTotal(node));
                const output = document.getElementById('overall-total');
                if (output) output.textContent = overall.toFixed(2);
            }

            function toggleCriterionEdit(item, editing) {
                item.querySelector('[data-criterion-view]')?.classList.toggle('d-none', editing);
                item.querySelector('[data-criterion-edit]')?.classList.toggle('d-none', !editing);
                item.querySelector('.btn-edit-criterion')?.classList.toggle('d-none', editing);
                item.querySelector('.btn-save-criterion')?.classList.toggle('d-none', !editing);
                item.querySelector('.btn-cancel-criterion')?.classList.toggle('d-none', !editing);
                if (editing) item.querySelector('.criterion-edit-name')?.focus();
            }

            function filterNode(node, query) {
                const childContainer = node.querySelector(':scope > [data-builder-children]');
                const childMatches = childContainer ? Array.from(childContainer.children).filter(child => child.matches('[data-section-node]')).map(child => filterNode(child, query)).some(Boolean) : false;
                const ownText = node.querySelector(':scope > .builder-node-shell')?.textContent.toLowerCase() ?? '';
                const matches = ownText.includes(query) || childMatches;
                node.classList.toggle('search-hidden', !matches);
                if (query && childMatches) setNodeCollapsed(node, false);
                return matches;
            }

            builder.addEventListener('click', async event => {
                const toggle = event.target.closest('[data-toggle-panel]');
                if (toggle) { const id = toggle.dataset.togglePanel; const panel = document.getElementById(id); setPanel(id, panel?.classList.contains('d-none') ?? true, toggle.dataset.panelFocus || null); return; }
                const closer = event.target.closest('[data-close-panel]');
                if (closer) { setPanel(closer.dataset.closePanel, false); return; }
                const collapse = event.target.closest('[data-collapse-node]');
                if (collapse) { const node = collapse.closest('[data-section-node]'); setNodeCollapsed(node, !node.classList.contains('is-collapsed')); return; }
                if (event.target.closest('[data-expand-all]')) { builder.querySelectorAll('[data-section-node]').forEach(node => setNodeCollapsed(node, false)); return; }
                if (event.target.closest('[data-collapse-all]')) { builder.querySelectorAll('[data-section-node]').forEach(node => setNodeCollapsed(node, true)); return; }
                const shift = event.target.closest('[data-shift-section]');
                if (shift) {
                    const node = shift.closest('[data-section-node]');
                    const panel = node ? document.getElementById(`section-edit-form-${node.dataset.sectionId}`) : null;
                    const form = panel?.querySelector('[data-section-form]');
                    const order = form?.querySelector('[name="sort_order"]');
                    if (form && order) {
                        order.value = Math.max(1, Number(order.value || 1) + Number(shift.dataset.shiftSection));
                        form.requestSubmit();
                    }
                    return;
                }
                const addRow = event.target.closest('.btn-add-criteria-row');
                if (addRow) { buildDraftRow(addRow.closest('.criteria-bulk-form')); return; }
                const removeRow = event.target.closest('.btn-remove-draft-row');
                if (removeRow) {
                    const form = removeRow.closest('.criteria-bulk-form'); const rows = form.querySelectorAll('.criteria-draft-row');
                    if (rows.length === 1) rows[0].querySelectorAll('input').forEach(input => { input.value = ''; input.classList.remove('is-invalid'); }); else removeRow.closest('.criteria-draft-row').remove();
                    return;
                }
                const editCriterion = event.target.closest('.btn-edit-criterion');
                if (editCriterion) { toggleCriterionEdit(editCriterion.closest('[data-criterion-item]'), true); return; }
                const cancelCriterion = event.target.closest('.btn-cancel-criterion');
                if (cancelCriterion) { toggleCriterionEdit(cancelCriterion.closest('[data-criterion-item]'), false); return; }
                const saveCriterion = event.target.closest('.btn-save-criterion');
                if (saveCriterion) {
                    const item = saveCriterion.closest('[data-criterion-item]');
                    const nameInput = item.querySelector('.criterion-edit-name'); const descriptionInput = item.querySelector('.criterion-edit-description'); const scoreInput = item.querySelector('.criterion-edit-score'); const feedback = item.querySelector('.criterion-feedback');
                    const name = nameInput.value.trim(); const score = scoreInput?.value ?? null;
                    nameInput.classList.toggle('is-invalid', !name); scoreInput?.classList.toggle('is-invalid', Number(score) <= 0);
                    if (!name || (scoreInput && Number(score) <= 0)) { feedback.textContent = !name ? 'Criterion name is required.' : 'Enter a max score greater than zero.'; feedback.className = 'criterion-feedback is-error'; return; }
                    saveCriterion.disabled = true; saveCriterion.textContent = 'Saving...';
                    const data = new FormData(); data.append('_token', csrfToken); data.append('_method', 'PUT'); data.append('name', name); data.append('description', descriptionInput.value.trim()); if (scoreInput) data.append('max_score', score);
                    try {
                        const response = await fetch(item.dataset.updateUrl, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: data });
                        const payload = await response.json().catch(() => ({})); if (!response.ok) throw new Error(extractError(payload) || 'Unable to update criterion.');
                        item.querySelector('[data-criterion-name]').textContent = name;
                        const description = descriptionInput.value.trim(); const descriptionView = item.querySelector('[data-criterion-description]'); descriptionView.textContent = description || 'No description provided'; descriptionView.classList.toggle('text-muted', !description);
                        if (scoreInput) item.querySelector('[data-criterion-score]').textContent = Number(score).toFixed(2);
                        feedback.textContent = ''; toggleCriterionEdit(item, false); calculateTotals();
                    } catch (error) { feedback.textContent = error.message || 'Unable to update criterion.'; feedback.className = 'criterion-feedback is-error'; }
                    finally { saveCriterion.disabled = false; saveCriterion.textContent = 'Save'; }
                }
            });

            builder.addEventListener('submit', async event => {
                const deleteForm = event.target.closest('[data-ajax-delete]');
                if (deleteForm) {
                    event.preventDefault();
                    if (!window.confirm(deleteForm.dataset.confirm || 'Remove this item?')) return;

                    const kind = deleteForm.dataset.deleteKind;
                    const node = deleteForm.closest('[data-section-node]');
                    const criterion = deleteForm.closest('[data-criterion-item]');
                    deleteForm.setAttribute('aria-busy', 'true');
                    setLiveState(true, 'Removing...');
                    try {
                        const payload = await submitJsonForm(deleteForm);
                        if (kind === 'section') {
                            await refreshBuilderStructure();
                        } else if (criterion && node) {
                            criterion.remove();
                            refreshCriterionIndexes(node);
                            calculateQuestionCounts();
                            calculateTotals();
                            setLiveState(false);
                        }
                        showToast(payload.message || (kind === 'section' ? 'Section removed.' : 'Question removed.'));
                    } catch (error) {
                        deleteForm.removeAttribute('aria-busy');
                        setLiveState(false);
                        showToast(error.message || 'Unable to remove this item.', 'error');
                    }
                    return;
                }

                const sectionForm = event.target.closest('[data-section-form]');
                if (!sectionForm) return;
                event.preventDefault();
                if (!sectionForm.reportValidity()) return;

                setFormBusy(sectionForm, true);
                setLiveState(true);
                try {
                    const payload = await submitJsonForm(sectionForm);
                    await refreshBuilderStructure(payload.section?.id || null);
                    showToast(payload.message || 'Section saved.');
                } catch (error) {
                    setFormBusy(sectionForm, false);
                    setLiveState(false);
                    showToast(error.message || 'Unable to save this section.', 'error');
                }
            });

            builder.addEventListener('submit', async event => {
                const form = event.target.closest('.criteria-bulk-form'); if (!form) return; event.preventDefault();
                const { rows, valid } = collectDraftRows(form);
                if (!rows.length) { setCriteriaFeedback(form, 'Add at least one criterion before saving.', 'error'); form.querySelector('.draft-name')?.focus(); return; }
                if (!valid) { setCriteriaFeedback(form, form.dataset.services === '1' ? 'Complete each criterion and enter a max score greater than zero.' : 'Give each criterion a name.', 'error'); form.querySelector('.is-invalid')?.focus(); return; }
                const submit = form.querySelector('.btn-save-criteria'); submit.disabled = true; submit.textContent = 'Saving...';
                setLiveState(true);
                const data = new FormData(); data.append('_token', csrfToken); data.append('criteria_payload', JSON.stringify(rows));
                try {
                    const response = await fetch(form.action, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: data });
                    const payload = await response.json().catch(() => ({})); if (!response.ok) throw new Error(extractError(payload) || 'Unable to save criteria.');
                    (Array.isArray(payload.criteria) ? payload.criteria : []).forEach(criterion => appendCriterion(form.dataset.sectionId, criterion, form.dataset.services === '1'));
                    form.querySelector('.criteria-draft-body').innerHTML = ''; buildDraftRow(form); setCriteriaFeedback(form, payload.message || 'Questions saved.', 'success'); calculateTotals();
                    showToast(payload.message || 'Questions saved.');
                } catch (error) { setCriteriaFeedback(form, error.message || 'Unable to save questions.', 'error'); showToast(error.message || 'Unable to save questions.', 'error'); }
                finally { submit.disabled = false; submit.textContent = 'Save questions'; setLiveState(false); }
            });

            builder.addEventListener('input', event => {
                if (!event.target.matches('[data-structure-search]')) return;
                const query = event.target.value.trim().toLowerCase(); const tree = builder.querySelector('[data-builder-tree]'); if (!tree) return;
                const anyMatch = Array.from(tree.children).filter(child => child.matches('[data-section-node]')).map(node => filterNode(node, query)).some(Boolean);
                builder.querySelector('[data-search-empty]')?.classList.toggle('d-none', anyMatch); tree.classList.toggle('d-none', !anyMatch);
            });
            initializeDynamicForms();
            calculateQuestionCounts();
            calculateTotals();
        })();
    </script>
@endsection
