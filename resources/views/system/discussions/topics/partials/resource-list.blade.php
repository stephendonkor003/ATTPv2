@php
    $resourceItems = old($field, $topic->{$field} ?? []);
    $resourceItems = is_array($resourceItems) ? array_values($resourceItems) : [];
    $resourceItems = $resourceItems ?: [[]];
    $typeOptions = $typeOptions ?? [];
    $fixedType = $fixedType ?? null;
@endphp

<section class="forum-resource-group" data-resource-list data-next-index="{{ count($resourceItems) }}">
    <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-2 mb-3">
        <div>
            <h3 class="mb-1"><i class="feather-{{ $resourceIcon }} me-2"></i>{{ $resourceTitle }}</h3>
            <p class="small forum-muted mb-0">{{ $resourceHelp }}</p>
        </div>
        <button type="button" class="btn btn-sm btn-light border text-nowrap" data-add-resource>
            <i class="feather-plus me-1"></i> Add Item
        </button>
    </div>

    <div data-resource-rows>
        @foreach ($resourceItems as $resourceIndex => $resourceItem)
            @php($resourceItem = is_array($resourceItem) ? $resourceItem : [])
            <div class="resource-editor-row" data-resource-row>
                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                    <span class="small fw-bold text-dark" data-resource-number>Item {{ $loop->iteration }}</span>
                    <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none p-0"
                        data-remove-resource aria-label="Remove resource item">
                        <i class="feather-trash-2 me-1"></i> Remove
                    </button>
                </div>
                <div class="row g-2">
                    <div class="col-md-5">
                        <label for="{{ $field }}-title-{{ $resourceIndex }}" class="form-label">Title</label>
                        <input id="{{ $field }}-title-{{ $resourceIndex }}" type="text"
                            name="{{ $field }}[{{ $resourceIndex }}][title]" maxlength="180"
                            value="{{ $resourceItem['title'] ?? $resourceItem['label'] ?? '' }}" class="form-control"
                            placeholder="Clear, descriptive title">
                    </div>
                    <div class="{{ $typeOptions ? 'col-md-5' : 'col-md-7' }}">
                        <label for="{{ $field }}-url-{{ $resourceIndex }}" class="form-label">URL</label>
                        <input id="{{ $field }}-url-{{ $resourceIndex }}" type="text"
                            name="{{ $field }}[{{ $resourceIndex }}][url]" maxlength="2048"
                            value="{{ $resourceItem['url'] ?? '' }}" class="form-control"
                            placeholder="https://… or /assets/…">
                    </div>
                    @if ($typeOptions)
                        <div class="col-md-2">
                            <label for="{{ $field }}-type-{{ $resourceIndex }}" class="form-label">Type</label>
                            <select id="{{ $field }}-type-{{ $resourceIndex }}"
                                name="{{ $field }}[{{ $resourceIndex }}][type]" class="form-select">
                                <option value="">General</option>
                                @foreach ($typeOptions as $typeValue => $typeLabel)
                                    <option value="{{ $typeValue }}" @selected(($resourceItem['type'] ?? '') === $typeValue)>
                                        {{ $typeLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @elseif ($fixedType)
                        <input type="hidden" name="{{ $field }}[{{ $resourceIndex }}][type]"
                            value="{{ $fixedType }}">
                    @endif
                    <div class="col-12">
                        <label for="{{ $field }}-description-{{ $resourceIndex }}" class="form-label">
                            Description <span class="forum-muted fw-normal">(optional)</span>
                        </label>
                        <textarea id="{{ $field }}-description-{{ $resourceIndex }}"
                            name="{{ $field }}[{{ $resourceIndex }}][description]" rows="2" maxlength="500"
                            class="form-control" placeholder="Explain why this resource is useful.">{{ $resourceItem['description'] ?? '' }}</textarea>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <template data-resource-template>
        <div class="resource-editor-row" data-resource-row>
            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <span class="small fw-bold text-dark" data-resource-number>New item</span>
                <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none p-0"
                    data-remove-resource aria-label="Remove resource item">
                    <i class="feather-trash-2 me-1"></i> Remove
                </button>
            </div>
            <div class="row g-2">
                <div class="col-md-5">
                    <label for="{{ $field }}-title-__INDEX__" class="form-label">Title</label>
                    <input id="{{ $field }}-title-__INDEX__" type="text" name="{{ $field }}[__INDEX__][title]"
                        maxlength="180" class="form-control" placeholder="Clear, descriptive title">
                </div>
                <div class="{{ $typeOptions ? 'col-md-5' : 'col-md-7' }}">
                    <label for="{{ $field }}-url-__INDEX__" class="form-label">URL</label>
                    <input id="{{ $field }}-url-__INDEX__" type="text" name="{{ $field }}[__INDEX__][url]"
                        maxlength="2048" class="form-control" placeholder="https://… or /assets/…">
                </div>
                @if ($typeOptions)
                    <div class="col-md-2">
                        <label for="{{ $field }}-type-__INDEX__" class="form-label">Type</label>
                        <select id="{{ $field }}-type-__INDEX__" name="{{ $field }}[__INDEX__][type]"
                            class="form-select">
                            <option value="">General</option>
                            @foreach ($typeOptions as $typeValue => $typeLabel)
                                <option value="{{ $typeValue }}">{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif ($fixedType)
                    <input type="hidden" name="{{ $field }}[__INDEX__][type]" value="{{ $fixedType }}">
                @endif
                <div class="col-12">
                    <label for="{{ $field }}-description-__INDEX__" class="form-label">
                        Description <span class="forum-muted fw-normal">(optional)</span>
                    </label>
                    <textarea id="{{ $field }}-description-__INDEX__" name="{{ $field }}[__INDEX__][description]"
                        rows="2" maxlength="500" class="form-control"
                        placeholder="Explain why this resource is useful."></textarea>
                </div>
            </div>
        </div>
    </template>
</section>
