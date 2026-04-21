@php
    $type = strtolower((string) ($question['type'] ?? 'text'));
    $questionKey = (string) ($question['key'] ?? ('question_' . ($sectionIndex ?? 0) . '_' . ($questionIndex ?? 0)));
    $required = (bool) ($question['required'] ?? false);
    $oldValue = old('answers.' . $questionKey);
    $options = collect($question['options'] ?? [])->filter()->values()->all();
    $matrixRows = collect($question['rows'] ?? [])->values();
    $matrixColumns = collect($question['columns'] ?? [])->values();
    $scaleMin = (int) data_get($question, 'scale.min', 1);
    $scaleMax = (int) data_get($question, 'scale.max', 5);
    $scaleStep = max((int) data_get($question, 'scale.step', 1), 1);
    $scaleLabels = collect((array) data_get($question, 'scale.labels', []))
        ->mapWithKeys(fn ($label, $value) => [(string) $value => trim((string) $label)])
        ->filter(fn ($label) => $label !== '');
    $scaleValues = collect(range($scaleMin, $scaleMax));
    $sliderValues = collect();
    for ($value = $scaleMin; $value <= $scaleMax; $value += $scaleStep) {
        $sliderValues->push($value);
    }
    $sliderValue = is_scalar($oldValue) && trim((string) $oldValue) !== ''
        ? (string) $oldValue
        : (string) $scaleMin;
    $sliderSelectedLabel = $scaleLabels->get($sliderValue, '');
    $selectionMin = data_get($question, 'min_selections');
    $selectionMax = data_get($question, 'max_selections');
    $questionTag = match ($type) {
        'textarea' => 'Long text',
        'select' => 'Dropdown',
        'multiselect' => 'Multi select',
        'radio' => 'Single choice',
        'checkbox' => 'Checkbox',
        'scale' => 'Scale',
        'slider' => 'Slider',
        'file' => 'File upload',
        'url' => 'Link',
        'matrix' => 'Grid',
        'datetime' => 'Date and time',
        default => ucfirst($type),
    };
    $questionNote = null;

    if (in_array($type, ['checkbox', 'multiselect'], true)) {
        $notes = [];
        if (is_numeric($selectionMin)) {
            $notes[] = 'Select at least ' . (int) $selectionMin . '.';
        }
        if (is_numeric($selectionMax)) {
            $notes[] = 'Select no more than ' . (int) $selectionMax . '.';
        }
        $questionNote = implode(' ', $notes) ?: 'Choose all that apply.';
    } elseif ($type === 'matrix') {
        $questionNote = 'Provide one response for each row.';
    } elseif ($type === 'slider') {
        $questionNote = 'Move the slider to the value that best reflects your view.';
    } elseif ($type === 'file') {
        $questionNote = 'Attach one supporting file if required.';
    }
@endphp

<div class="question-block"
    data-question-key="{{ $questionKey }}"
    data-question-type="{{ $type }}"
    data-question-flow="{{ strtolower((string) data_get($question, 'effective_flow_type', data_get($question, 'flow_type', 'normal'))) }}"
    data-question-route='@json($question['route'] ?? [])'
    data-question-visibility='@json($question['visibility'] ?? [])'
    data-question-required="{{ $required ? '1' : '0' }}"
    data-question-max-selections="{{ data_get($question, 'max_selections') }}"
    data-question-min-selections="{{ data_get($question, 'min_selections') }}">
    <div class="question-top">
        <div class="question-label-row">
            <div class="question-label">
                {{ $question['label'] ?? ('Question ' . (($questionIndex ?? 0) + 1)) }}
                @if ($required)
                    <span class="required">*</span>
                @endif
            </div>
            <span class="question-tag">{{ $questionTag }}</span>
        </div>

        @if (!empty($question['hint']))
            <div class="question-hint">{{ $question['hint'] }}</div>
        @endif

        @if ($questionNote)
            <div class="question-note">{{ $questionNote }}</div>
        @endif
    </div>

    <div class="question-stack">
        @if ($type === 'textarea')
            <textarea name="answers[{{ $questionKey }}]" {{ $required ? 'required' : '' }} placeholder="Type your response here">{{ is_scalar($oldValue) ? $oldValue : '' }}</textarea>
        @elseif ($type === 'select')
            <select name="answers[{{ $questionKey }}]" {{ $required ? 'required' : '' }}>
                <option value="">Select an option</option>
                @foreach ($options as $option)
                    <option value="{{ $option }}" @selected((string) $oldValue === (string) $option)>{{ $option }}</option>
                @endforeach
            </select>
        @elseif ($type === 'multiselect')
            @php
                $oldChoices = is_array($oldValue) ? $oldValue : [];
            @endphp
            <div class="choice-list {{ count($options) > 4 ? '' : 'choice-list--split' }}" data-multiselect-group="{{ $questionKey }}">
                @foreach ($options as $option)
                    <label class="choice-item" data-choice-item>
                        <input type="checkbox" name="answers[{{ $questionKey }}][]" value="{{ $option }}"
                            @checked(in_array($option, $oldChoices, true))>
                        <span class="choice-item__body">
                            <strong>{{ $option }}</strong>
                            <span>Select this response</span>
                        </span>
                    </label>
                @endforeach
            </div>
        @elseif ($type === 'radio')
            <div class="choice-list {{ count($options) > 4 ? '' : 'choice-list--split' }}">
                @foreach ($options as $option)
                    <label class="choice-item" data-choice-item>
                        <input type="radio" name="answers[{{ $questionKey }}]" value="{{ $option }}"
                            {{ $required ? 'required' : '' }}
                            @checked((string) $oldValue === (string) $option)>
                        <span class="choice-item__body">
                            <strong>{{ $option }}</strong>
                            <span>Select one option</span>
                        </span>
                    </label>
                @endforeach
            </div>
        @elseif ($type === 'checkbox')
            @php
                $oldChoices = is_array($oldValue) ? $oldValue : [];
            @endphp
            <div class="choice-list {{ count($options) > 4 ? '' : 'choice-list--split' }}" data-checkbox-group="{{ $questionKey }}">
                @foreach ($options as $option)
                    <label class="choice-item" data-choice-item>
                        <input type="checkbox" name="answers[{{ $questionKey }}][]" value="{{ $option }}"
                            @checked(in_array($option, $oldChoices, true))>
                        <span class="choice-item__body">
                            <strong>{{ $option }}</strong>
                            <span>Select all that apply</span>
                        </span>
                    </label>
                @endforeach
            </div>
        @elseif ($type === 'scale')
            <div class="scale-grid">
                @foreach ($scaleValues as $value)
                    <label class="choice-item scale-item" data-choice-item>
                        <input type="radio" name="answers[{{ $questionKey }}]" value="{{ $value }}"
                            {{ $required ? 'required' : '' }}
                            @checked((string) $oldValue === (string) $value)>
                        <strong>{{ $value }}</strong>
                        <span>{{ $scaleLabels->get((string) $value, 'Rating') }}</span>
                    </label>
                @endforeach
            </div>

            @if ($scaleLabels->isNotEmpty())
                <div class="scale-points">
                    @foreach ($scaleValues as $value)
                        @if ($scaleLabels->get((string) $value))
                            <div class="scale-point">
                                <strong>{{ $value }}</strong>
                                <span>{{ $scaleLabels->get((string) $value) }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            @elseif (data_get($question, 'scale.min_label') || data_get($question, 'scale.max_label'))
                <div class="scale-labels">
                    <span>{{ data_get($question, 'scale.min_label') }}</span>
                    <span>{{ data_get($question, 'scale.max_label') }}</span>
                </div>
            @endif
        @elseif ($type === 'slider')
            <div class="slider-panel">
                <div class="slider-head">
                    <div class="slider-output">
                        <span>Selected value</span>
                        <strong data-slider-value="{{ $questionKey }}">{{ $sliderValue }}</strong>
                    </div>
                    <div class="slider-selected-label" data-slider-selected-label="{{ $questionKey }}">
                        {{ $sliderSelectedLabel ?: 'Adjust to the most appropriate point on the scale.' }}
                    </div>
                </div>

                <input type="range"
                    min="{{ $scaleMin }}"
                    max="{{ $scaleMax }}"
                    step="{{ $scaleStep }}"
                    name="answers[{{ $questionKey }}]"
                    value="{{ $sliderValue }}"
                    data-slider-input="{{ $questionKey }}"
                    data-slider-label-map='@json($scaleLabels)'>

                @if ($scaleLabels->isNotEmpty())
                    <div class="scale-points scale-points--slider">
                        @foreach ($sliderValues as $value)
                            <div class="scale-point">
                                <strong>{{ $value }}</strong>
                                <span>{{ $scaleLabels->get((string) $value, '') }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="scale-labels">
                        <span>{{ data_get($question, 'scale.min_label') ?: $scaleMin }}</span>
                        <span>{{ data_get($question, 'scale.max_label') ?: $scaleMax }}</span>
                    </div>
                @endif
            </div>
        @elseif ($type === 'file')
            <input type="file" name="answers[{{ $questionKey }}]" {{ $required ? 'required' : '' }} data-file-input="{{ $questionKey }}">
            <div class="file-caption" data-file-caption="{{ $questionKey }}">No file selected yet.</div>
        @elseif ($type === 'url')
            <input type="url"
                name="answers[{{ $questionKey }}]"
                value="{{ is_scalar($oldValue) ? $oldValue : '' }}"
                placeholder="https://example.org/reference"
                {{ $required ? 'required' : '' }}>
        @elseif ($type === 'matrix')
            @php
                $oldMatrix = is_array($oldValue) ? $oldValue : [];
            @endphp
            <div class="matrix-wrap" data-matrix-group="{{ $questionKey }}">
                <table class="matrix-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            @foreach ($matrixColumns as $column)
                                <th>{{ data_get($column, 'label', $column) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($matrixRows as $row)
                            @php
                                $rowKey = (string) data_get($row, 'key', 'row_' . $loop->index);
                                $selectedColumn = $oldMatrix[$rowKey] ?? null;
                            @endphp
                            <tr>
                                <td>{{ data_get($row, 'label', $row) }}</td>
                                @foreach ($matrixColumns as $column)
                                    @php
                                        $columnKey = (string) data_get($column, 'key', 'column_' . $loop->index);
                                        $columnValue = (string) data_get($column, 'label', $columnKey);
                                    @endphp
                                    <td data-column-label="{{ $columnValue }}">
                                        <input type="radio"
                                            name="answers[{{ $questionKey }}][{{ $rowKey }}]"
                                            value="{{ $columnValue }}"
                                            @checked((string) $selectedColumn === (string) $columnValue)>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <input type="{{ match ($type) {
                'number', 'email', 'date' => $type,
                'datetime' => 'datetime-local',
                default => 'text',
            } }}"
                name="answers[{{ $questionKey }}]"
                value="{{ is_scalar($oldValue) ? $oldValue : '' }}"
                {{ $required ? 'required' : '' }}
                placeholder="{{ match ($type) {
                    'email' => 'name@example.org',
                    'number' => 'Enter a numeric response',
                    'date' => 'Select a date',
                    'datetime' => 'Select date and time',
                    default => 'Type your response',
                } }}">
        @endif
    </div>

    @error('answers.' . $questionKey)
        <div class="question-error">{{ $message }}</div>
    @enderror
</div>
