@php
    $level = max(1, min(4, (int) $level));
    $rootIndex = (int) ($rootIndex ?? 0);
    $children = $sectionsByParent->get((string) $section->id, collect());
    $criteria = $section->criteria->sortBy('created_at')->values();
    $sectionNumber = implode('.', $path);
    $nextLevel = $level + 1;
    $nextLevelLabel = $levelLabels[$nextLevel] ?? null;
    $nodeId = 'section-node-' . $section->id;
    $contentId = 'section-content-' . $section->id;
    $childFormId = 'section-child-form-' . $section->id;
    $editFormId = 'section-edit-form-' . $section->id;
    $criteriaFormId = 'criteria-form-' . $section->id;
    $directTotal = (float) $criteria->sum('max_score');
    $subtreeTotal = (float) $sectionSubtotals->get((string) $section->id, $directTotal);
    $showSubtotal = (bool) ($section->show_subtotal ?? false);
    $childFormHasErrors = old('form_context') === 'child-section-' . $section->id && $errors->any();
    $editFormHasErrors = old('form_context') === 'edit-section-' . $section->id && $errors->any();
    $selectedParentId = $editFormHasErrors ? old('parent_section_id') : $section->parent_section_id;
    $descendantIds = $descendantIdsBySection->get((string) $section->id, collect());
    $subtreeHeight = (int) $subtreeHeights->get((string) $section->id, 1);
    $subtreeCriteriaCount = (int) $subtreeCriteriaCounts->get((string) $section->id, $criteria->count());
    $displayLevelLabel = $levelLabels[$level];
    $summarySettingLabel = $isServices ? 'Subtotal' : 'Category summary';
    $deleteTargetLabel = Str::lower($displayLevelLabel);
    $deleteConfirmation = 'Delete this ' . $deleteTargetLabel
        . ($children->isNotEmpty() ? ' and all of its child sections' : '')
        . '? This cannot be undone.';
    $siblingCount = $level === 1
        ? $rootSections->count()
        : $sectionsByParent->get((string) $section->parent_section_id, collect())->count();
    $siblingPosition = (int) collect($path)->last();
    $eligibleParents = $orderedTreeSections->filter(function ($candidate) use ($section, $descendantIds, $depthById, $subtreeHeight): bool {
        $candidateId = (string) $candidate->id;

        return $candidateId !== (string) $section->id
            && ! $descendantIds->contains($candidateId)
            && ((int) $depthById->get($candidateId, 1) + $subtreeHeight) <= 4;
    });
@endphp

<div class="builder-node level-{{ $level }} hierarchy-tone-{{ $rootIndex % 8 }}" id="{{ $nodeId }}" data-section-node
    data-section-id="{{ $section->id }}" data-level="{{ $level }}" data-root-index="{{ $rootIndex }}">
    <article class="builder-node-shell">
        <header class="builder-node-header">
            <button type="button" class="node-collapse-btn" data-collapse-node aria-expanded="true"
                aria-controls="{{ $contentId }}" title="Collapse {{ $section->name }}">
                <i class="feather-chevron-down" aria-hidden="true"></i>
                <span class="visually-hidden">Collapse {{ $section->name }}</span>
            </button>

            <div class="level-marker" aria-hidden="true">{{ $sectionNumber }}</div>

            <div class="node-heading">
                <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                    <span class="tier-badge tier-{{ $level }}">{{ $displayLevelLabel }}</span>
                    <span class="node-path">{{ $sectionNumber }}</span>
                </div>
                <h3 class="node-title">{{ $section->name }}</h3>
                @if (filled($section->description))
                    <p class="node-description">{{ $section->description }}</p>
                @endif
            </div>

            <div class="node-meta">
                <span class="meta-pill" title="Questions directly in this {{ Str::lower($displayLevelLabel) }}">
                    <i class="feather-list" aria-hidden="true"></i>
                    <strong data-direct-criteria-count>{{ $criteria->count() }}</strong>
                    direct
                    <span class="meta-divider" aria-hidden="true">&middot;</span>
                    <strong data-node-total-criteria>{{ $subtreeCriteriaCount }}</strong>
                    total questions
                </span>
                @if ($children->isNotEmpty())
                    <span class="meta-pill" title="Immediate child sections">
                        <i class="feather-layers" aria-hidden="true"></i>
                        {{ $children->count() }} {{ Str::plural('child', $children->count()) }}
                    </span>
                @endif
                <div class="subtotal-control" data-subtotal-setting>
                    @if ($showSubtotal)
                        @if ($isServices)
                            <span class="subtotal-pill is-enabled" title="Includes criteria in this section and all children">
                                Subtotal <strong data-node-subtotal>{{ number_format($subtreeTotal, 2) }}</strong>
                            </span>
                        @else
                            <span class="subtotal-pill is-enabled" title="A category-count summary will appear for this section">
                                Category summary on
                            </span>
                        @endif
                    @else
                        <span class="subtotal-pill is-disabled" title="{{ $summarySettingLabel }} display is disabled for this section">
                            {{ $isServices ? 'Subtotal' : 'Summary' }} off
                        </span>
                    @endif
                    @if ($canEdit)
                        <button type="button" class="subtotal-edit-btn" data-edit-subtotal
                            data-toggle-panel="{{ $editFormId }}"
                            data-panel-focus="edit-subtotal-{{ $section->id }}"
                            aria-controls="{{ $editFormId }}"
                            aria-expanded="{{ $editFormHasErrors ? 'true' : 'false' }}"
                            aria-label="Edit {{ Str::lower($summarySettingLabel) }} for {{ $displayLevelLabel }} {{ $section->name }}; currently {{ $showSubtotal ? 'on' : 'off' }}"
                            title="Edit {{ Str::lower($summarySettingLabel) }} setting">
                            <i class="feather-edit-2" aria-hidden="true"></i>
                            <span>Edit {{ $isServices ? 'subtotal' : 'summary' }}</span>
                        </button>
                    @endif
                </div>
            </div>

            @if ($canEdit)
                <div class="node-actions" aria-label="Actions for {{ $section->name }}">
                    @if ($level < 4)
                        <button type="button" class="btn btn-sm btn-primary-soft" data-toggle-panel="{{ $childFormId }}"
                            aria-expanded="{{ $childFormHasErrors ? 'true' : 'false' }}" title="Add {{ $nextLevelLabel }}">
                            <i class="feather-plus" aria-hidden="true"></i>
                            <span>Add {{ $nextLevelLabel }}</span>
                        </button>
                    @endif
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle-panel="{{ $criteriaFormId }}"
                        aria-expanded="false" title="Add questions">
                        <i class="feather-list" aria-hidden="true"></i>
                        <span>Questions</span>
                    </button>
                    @if ($level > 1)
                        <form method="POST" action="{{ route('evals.cfg.sec.del', $section) }}"
                            class="node-delete-form" data-ajax-delete data-delete-kind="section"
                            data-confirm="{{ $deleteConfirmation }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" type="submit"
                                title="Delete {{ $displayLevelLabel }}"
                                aria-label="Delete {{ $displayLevelLabel }} {{ $section->name }}">
                                <i class="feather-trash-2" aria-hidden="true"></i>
                                <span>Delete</span>
                            </button>
                        </form>
                    @endif
                    <div class="dropdown">
                        <button class="btn btn-sm btn-icon btn-outline-secondary" type="button" data-bs-toggle="dropdown"
                            aria-expanded="false" aria-label="More actions for {{ $section->name }}">
                            <i class="feather-more-vertical" aria-hidden="true"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <button type="button" class="dropdown-item" data-toggle-panel="{{ $editFormId }}"
                                    aria-expanded="{{ $editFormHasErrors ? 'true' : 'false' }}">
                                    <i class="feather-edit-2 me-2" aria-hidden="true"></i>Edit section
                                </button>
                            </li>
                            @if ($siblingPosition > 1)
                                <li>
                                    <button type="button" class="dropdown-item" data-shift-section="-1">
                                        <i class="feather-arrow-up me-2" aria-hidden="true"></i>Move up
                                    </button>
                                </li>
                            @endif
                            @if ($siblingPosition < $siblingCount)
                                <li>
                                    <button type="button" class="dropdown-item" data-shift-section="1">
                                        <i class="feather-arrow-down me-2" aria-hidden="true"></i>Move down
                                    </button>
                                </li>
                            @endif
                            @if ($level === 1)
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('evals.cfg.sec.del', $section) }}"
                                        data-ajax-delete data-delete-kind="section"
                                        data-confirm="{{ $deleteConfirmation }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="dropdown-item text-danger" type="submit">
                                            <i class="feather-trash-2 me-2" aria-hidden="true"></i>Delete {{ $displayLevelLabel }}
                                        </button>
                                    </form>
                                </li>
                            @endif
                        </ul>
                    </div>
                </div>
            @endif
        </header>

        <div class="builder-node-content" id="{{ $contentId }}">
            @if ($canEdit && $level < 4)
                <div id="{{ $childFormId }}" class="inline-panel {{ $childFormHasErrors ? '' : 'd-none' }}" data-inline-panel>
                    <div class="inline-panel-heading">
                        <div>
                            <span class="inline-panel-kicker">Level {{ $nextLevel }}</span>
                            <strong>Add {{ $nextLevelLabel }} under &ldquo;{{ $section->name }}&rdquo;</strong>
                        </div>
                        <button type="button" class="btn-close" data-close-panel="{{ $childFormId }}"
                            aria-label="Close add {{ $nextLevelLabel }} form"></button>
                    </div>
                    <form method="POST" action="{{ route('evals.cfg.sec.add', $evaluation) }}" class="section-inline-form"
                        data-section-form data-success-panel="{{ $childFormId }}">
                        @csrf
                        <input type="hidden" name="form_context" value="child-section-{{ $section->id }}">
                        <input type="hidden" name="parent_section_id" value="{{ $section->id }}">
                        <input type="hidden" name="sort_order" value="{{ $children->count() + 1 }}">
                        <div class="row g-3">
                            <div class="col-lg-5">
                                <label class="form-label" for="child-name-{{ $section->id }}">{{ $nextLevelLabel }} name</label>
                                <input id="child-name-{{ $section->id }}" name="name" type="text"
                                    class="form-control {{ $childFormHasErrors && $errors->has('name') ? 'is-invalid' : '' }}"
                                    value="{{ $childFormHasErrors ? old('name') : '' }}"
                                    placeholder="e.g. {{ $level === 1 ? 'Technical capacity' : 'Supporting evidence' }}" required maxlength="255">
                                @if ($childFormHasErrors && $errors->has('name'))
                                    <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                                @endif
                            </div>
                            <div class="col-lg-7">
                                <label class="form-label" for="child-description-{{ $section->id }}">Description <span class="text-muted">(optional)</span></label>
                                <input id="child-description-{{ $section->id }}" name="description" type="text"
                                    class="form-control {{ $childFormHasErrors && $errors->has('description') ? 'is-invalid' : '' }}"
                                    value="{{ $childFormHasErrors ? old('description') : '' }}" placeholder="Brief guidance for evaluators">
                                @if ($childFormHasErrors && $errors->has('description'))
                                    <div class="invalid-feedback">{{ $errors->first('description') }}</div>
                                @endif
                            </div>
                            <div class="col-12">
                                <input type="hidden" name="show_subtotal" value="0">
                                <div class="form-check form-switch subtotal-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" name="show_subtotal" value="1"
                                        id="child-subtotal-{{ $section->id }}" data-subtotal-toggle
                                        aria-describedby="child-subtotal-help-{{ $section->id }}"
                                        {{ $childFormHasErrors && old('show_subtotal') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="child-subtotal-{{ $section->id }}">
                                        {{ $isServices ? 'Show a subtotal' : 'Show a category summary' }} after this {{ Str::lower($nextLevelLabel) }}
                                    </label>
                                    <span id="child-subtotal-help-{{ $section->id }}">
                                        {{ $isServices
                                            ? 'Includes its own maximum points and every child below it.'
                                            : 'Shows category counts for its own criteria and every child below it; it does not create a numeric score.' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="inline-panel-footer">
                            <button type="button" class="btn btn-light" data-close-panel="{{ $childFormId }}">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="feather-plus me-1" aria-hidden="true"></i>Add {{ $nextLevelLabel }}
                            </button>
                        </div>
                    </form>
                </div>
            @endif

            @if ($canEdit)
                <div id="{{ $editFormId }}" class="inline-panel {{ $editFormHasErrors ? '' : 'd-none' }}" data-inline-panel>
                    <div class="inline-panel-heading">
                        <div>
                            <span class="inline-panel-kicker">Section settings</span>
                            <strong>Edit &ldquo;{{ $section->name }}&rdquo;</strong>
                        </div>
                        <button type="button" class="btn-close" data-close-panel="{{ $editFormId }}" aria-label="Close edit form"></button>
                    </div>
                    <form method="POST" action="{{ route('evals.cfg.sec.upd', $section) }}" class="section-inline-form"
                        data-section-form data-success-panel="{{ $editFormId }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="form_context" value="edit-section-{{ $section->id }}">
                        <div class="row g-3">
                            <div class="col-lg-5">
                                <label class="form-label" for="edit-section-name-{{ $section->id }}">Name</label>
                                <input id="edit-section-name-{{ $section->id }}" name="name"
                                    class="form-control {{ $editFormHasErrors && $errors->has('name') ? 'is-invalid' : '' }}"
                                    value="{{ $editFormHasErrors ? old('name', $section->name) : $section->name }}" required maxlength="255">
                                @if ($editFormHasErrors && $errors->has('name'))
                                    <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                                @endif
                            </div>
                            <div class="col-lg-5">
                                <label class="form-label" for="edit-section-description-{{ $section->id }}">Description <span class="text-muted">(optional)</span></label>
                                <input id="edit-section-description-{{ $section->id }}" name="description"
                                    class="form-control {{ $editFormHasErrors && $errors->has('description') ? 'is-invalid' : '' }}"
                                    value="{{ $editFormHasErrors ? old('description', $section->description) : $section->description }}" placeholder="Brief guidance for evaluators">
                                @if ($editFormHasErrors && $errors->has('description'))
                                    <div class="invalid-feedback">{{ $errors->first('description') }}</div>
                                @endif
                            </div>
                            <div class="col-lg-2">
                                <label class="form-label" for="edit-section-order-{{ $section->id }}">Display order</label>
                                <input id="edit-section-order-{{ $section->id }}" type="number" min="0" name="sort_order"
                                    class="form-control {{ $editFormHasErrors && $errors->has('sort_order') ? 'is-invalid' : '' }}"
                                    value="{{ $editFormHasErrors ? old('sort_order', $section->sort_order) : (int) ($section->sort_order ?? 0) }}">
                                @if ($editFormHasErrors && $errors->has('sort_order'))
                                    <div class="invalid-feedback">{{ $errors->first('sort_order') }}</div>
                                @endif
                            </div>
                            <div class="col-lg-6">
                                <label class="form-label" for="edit-section-parent-{{ $section->id }}">Place under</label>
                                <select id="edit-section-parent-{{ $section->id }}" name="parent_section_id"
                                    class="form-select {{ $editFormHasErrors && $errors->has('parent_section_id') ? 'is-invalid' : '' }}">
                                    <option value="" {{ blank($selectedParentId) ? 'selected' : '' }}>Top level (no parent)</option>
                                    @foreach ($eligibleParents as $candidate)
                                        <option value="{{ $candidate->id }}"
                                            {{ (string) $selectedParentId === (string) $candidate->id ? 'selected' : '' }}>
                                            {{ $outlineById->get((string) $candidate->id) }} {{ $candidate->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">Only placements that keep the form within four levels are shown.</div>
                                @if ($editFormHasErrors && $errors->has('parent_section_id'))
                                    <div class="invalid-feedback">{{ $errors->first('parent_section_id') }}</div>
                                @endif
                            </div>
                            <div class="col-12">
                                <input type="hidden" name="show_subtotal" value="0">
                                <div class="form-check form-switch subtotal-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" name="show_subtotal" value="1"
                                        id="edit-subtotal-{{ $section->id }}" data-subtotal-toggle
                                        aria-describedby="edit-subtotal-help-{{ $section->id }}"
                                        {{ ($editFormHasErrors ? old('show_subtotal') : $showSubtotal) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="edit-subtotal-{{ $section->id }}">
                                        {{ $isServices ? 'Show subtotal for this section' : 'Show category summary for this section' }}
                                    </label>
                                    <span id="edit-subtotal-help-{{ $section->id }}">
                                        {{ $isServices
                                            ? 'Its subtotal includes maximum points in every nested child.'
                                            : 'The summary groups evaluator decisions by category without calculating a score.' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="inline-panel-footer">
                            <button type="button" class="btn btn-light" data-close-panel="{{ $editFormId }}">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save section</button>
                        </div>
                    </form>
                </div>
            @endif

            @if ($canEdit)
                <div id="{{ $criteriaFormId }}" class="inline-panel criteria-composer d-none" data-inline-panel>
                    <div class="inline-panel-heading">
                        <div>
                            <span class="inline-panel-kicker">Evaluator input</span>
                            <strong>Add questions to &ldquo;{{ $section->name }}&rdquo;</strong>
                        </div>
                        <button type="button" class="btn-close" data-close-panel="{{ $criteriaFormId }}" aria-label="Close criteria form"></button>
                    </div>
                    <form method="POST" action="{{ route('evals.cfg.crt.add', $section) }}" class="criteria-bulk-form"
                        data-section-id="{{ $section->id }}" data-services="{{ $isServices ? 1 : 0 }}">
                        @csrf
                        <input type="hidden" name="criteria_payload" class="criteria-payload">
                        <div class="criteria-draft-body"></div>
                        <div class="criteria-composer-actions">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-add-criteria-row">
                                <i class="feather-plus me-1" aria-hidden="true"></i>Add another question
                            </button>
                            <div class="ms-auto d-flex align-items-center gap-2">
                                <span class="criteria-feedback" role="status" aria-live="polite"></span>
                                <button type="submit" class="btn btn-sm btn-primary btn-save-criteria">
                                    Save questions
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            @endif

            <div class="criteria-block">
                <div class="criteria-block-heading">
                    <div>
                        <span class="criteria-eyebrow">Questions</span>
                        <span class="criteria-helper">
                            @if ($isServices)
                                Every question requires its own score and supporting evidence response. Scores contribute to section and parent subtotals.
                            @elseif ($evaluation->isEoi())
                                Evaluators classify and add an evidence comment for every question.
                            @else
                                Evaluators record a Yes or No decision and evidence comment for every question.
                            @endif
                        </span>
                    </div>
                </div>

                <div class="criteria-list" data-criteria-list>
                    <div class="criteria-empty {{ $criteria->isNotEmpty() ? 'd-none' : '' }}" data-criteria-empty>
                        <i class="feather-inbox" aria-hidden="true"></i>
                        <span>No questions in this {{ Str::lower($displayLevelLabel) }} yet.</span>
                    </div>
                    @foreach ($criteria as $criterionIndex => $criterion)
                        <div class="criterion-item" data-criterion-item data-update-url="{{ route('evals.cfg.crt.upd', $criterion) }}">
                            <div class="criterion-index">{{ $criterionIndex + 1 }}</div>
                            <div class="criterion-main">
                                <div data-criterion-view>
                                    <strong class="criterion-name" data-criterion-name>{{ $criterion->name }}</strong>
                                    @if (filled($criterion->description))
                                        <p data-criterion-description>{{ $criterion->description }}</p>
                                    @else
                                        <p class="text-muted" data-criterion-description>No description provided</p>
                                    @endif
                                </div>
                                @if ($canEdit)
                                    <div class="criterion-edit-grid d-none" data-criterion-edit>
                                        <div>
                                            <label class="form-label" for="criterion-name-{{ $criterion->id }}">Question</label>
                                            <input id="criterion-name-{{ $criterion->id }}" class="form-control form-control-sm criterion-edit-name"
                                                value="{{ $criterion->name }}" maxlength="255" required>
                                        </div>
                                        <div>
                                            <label class="form-label" for="criterion-description-{{ $criterion->id }}">Description</label>
                                            <input id="criterion-description-{{ $criterion->id }}" class="form-control form-control-sm criterion-edit-description"
                                                value="{{ $criterion->description }}">
                                        </div>
                                        @if ($isServices)
                                            <div>
                                                <label class="form-label" for="criterion-score-{{ $criterion->id }}">Max score</label>
                                                <input id="criterion-score-{{ $criterion->id }}" type="number" min="1" step="0.01"
                                                    class="form-control form-control-sm criterion-edit-score" value="{{ $criterion->max_score }}" required>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="criterion-feedback" role="status" aria-live="polite"></div>
                                @endif
                            </div>
                            @if ($isServices)
                                <div class="criterion-score">
                                    <span data-criterion-score>{{ number_format((float) $criterion->max_score, 2) }}</span>
                                    <small>max</small>
                                </div>
                            @endif
                            @if ($canEdit)
                                <div class="criterion-actions">
                                    <button type="button" class="btn btn-sm btn-icon btn-outline-secondary btn-edit-criterion"
                                        title="Edit question" aria-label="Edit {{ $criterion->name }}">
                                        <i class="feather-edit-2" aria-hidden="true"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-success btn-save-criterion d-none">Save</button>
                                    <button type="button" class="btn btn-sm btn-light btn-cancel-criterion d-none">Cancel</button>
                                    <form method="POST" action="{{ route('evals.cfg.crt.del', $criterion) }}"
                                        data-ajax-delete data-delete-kind="criterion" data-confirm="Delete this question?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-icon btn-outline-danger" type="submit" title="Delete question"
                                            aria-label="Delete {{ $criterion->name }}">
                                            <i class="feather-trash-2" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </article>

    @if ($children->isNotEmpty())
        <div class="builder-children" data-builder-children>
            @foreach ($children as $childIndex => $child)
                @include('evaluations.partials.section-builder-node', [
                    'section' => $child,
                    'level' => $level + 1,
                    'path' => array_merge($path, [$childIndex + 1]),
                    'rootIndex' => $rootIndex,
                ])
            @endforeach
        </div>
    @endif
</div>
