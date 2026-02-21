@php
    $oldVarsJson = old('variables_json');
    $varsFromTable = $definition?->variableRows?->map(function ($v) {
        return ['label' => $v->name, 'color' => $v->color];
    })->toArray() ?? [];
    $varsState = $oldVarsJson ? json_decode($oldVarsJson, true) : (old('variables', $varsFromTable ?: ($definition->variables ?? [])));
    $formulaText = old('formula_text', $definition->formula['expression'] ?? '');
    $stats = $stats ?? ['formulas' => '—', 'variables' => '—', 'functions' => '—'];
@endphp

<style>
    .builder-shell { background: #f5f7fb; border: 1px solid #e2e7f0; }
    .metric-card { background: #f0f4ff; border: 1px solid #dbe4ff; border-radius: 10px; padding: 10px 14px; }
    .token-chip { cursor: pointer; }
    .palette-card { background: #f8fbff; border: 1px solid #e5ebf5; border-radius: 8px; }
    .formula-preview { min-height: 54px; }
    .var-chip { color: #fff; padding: 6px 10px; border-radius: 10px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
    .var-dot { width: 10px; height: 10px; border-radius: 50%; background: rgba(255,255,255,0.85); }
    .frac { display: inline-flex; flex-direction: column; align-items: center; gap: 2px; font-weight: 600; line-height: 1; margin: 0 3px; }
    .frac .bar { border-top: 2px solid #1d4ed8; width: 100%; min-width: 28px; margin: 0 1px; }
    .frac .num, .frac .den { min-width: 28px; text-align: center; padding: 0 2px; }
</style>

<div class="card shadow-sm border-0 builder-shell mb-3">
    <div class="card-body d-flex flex-wrap gap-3">
        <div class="metric-card d-flex align-items-center gap-2">
            <i class="feather-edit text-primary"></i>
            <div>
                <div class="small text-muted">Total Formulas</div>
                <div class="fw-bold">{{ $stats['formulas'] }}</div>
            </div>
        </div>
        <div class="metric-card d-flex align-items-center gap-2">
            <i class="feather-target text-primary"></i>
            <div>
                <div class="small text-muted">Variables Used</div>
                <div class="fw-bold">{{ $stats['variables'] }}</div>
            </div>
        </div>
        <div class="metric-card d-flex align-items-center gap-2">
            <i class="feather-function text-primary"></i>
            <div>
                <div class="small text-muted">Functions Used</div>
                <div class="fw-bold">{{ $stats['functions'] }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <h5 class="fw-bold mb-3">Create New Formula</h5>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Formula Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $definition->name ?? '') }}" required placeholder="Enter formula name...">
            </div>
            <div class="col-md-6">
                <label class="form-label">Description</label>
                <input type="text" name="description" class="form-control" value="{{ old('description', $definition->description ?? '') }}" placeholder="Optional description...">
            </div>
            <div class="col-md-3">
                <label class="form-label">Code</label>
                <input type="text" name="code" class="form-control" value="{{ old('code', $definition->code ?? '') }}">
            </div>
        </div>

        <div class="row g-4 flex-column flex-lg-row">
            {{-- Editor --}}
            <div class="col-lg-7 order-2 order-lg-1">
                <h6 class="fw-semibold">Formula Editor</h6>
                <textarea id="formulaInput" class="form-control mb-2" rows="4" placeholder="Type or click items to insert">{{ $formulaText }}</textarea>

                <div class="d-flex flex-wrap gap-2 mb-2" id="operatorButtons"></div>

                <div class="mt-2">
                    <button class="btn btn-primary me-2" type="button" id="saveFormulaDraft">Save Formula</button>
                    <button class="btn btn-outline-secondary" type="button" id="clearFormula">Clear</button>
                </div>
            </div>

            {{-- Side palettes --}}
            <div class="col-lg-5 order-1 order-lg-2">
                <div class="accordion" id="sideAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#varsPanel" aria-expanded="true">
                                Variables
                            </button>
                        </h2>
                        <div id="varsPanel" class="accordion-collapse collapse show">
                            <div class="accordion-body palette-card">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-semibold">Click to insert</span>
                                    <button class="btn btn-sm btn-outline-primary" type="button" id="toggleVarAdder">+ Add</button>
                                </div>
                                <div id="variableAdder" class="border rounded p-2 mb-2 d-none bg-white">
                                    <input type="text" id="varNameInput" class="form-control form-control-sm mb-2" placeholder="Variable name">
                                    <button class="btn btn-sm btn-primary w-100" type="button" id="saveVarBtn">Save Variable</button>
                                </div>
                                <div id="variablesList" class="d-flex flex-wrap gap-2" style="min-height:60px;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#funcPanel">
                                Functions
                            </button>
                        </h2>
                        <div id="funcPanel" class="accordion-collapse collapse">
                            <div class="accordion-body palette-card" id="functionsPalette"></div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#constPanel">
                                Constants
                            </button>
                        </h2>
                        <div id="constPanel" class="accordion-collapse collapse">
                            <div class="accordion-body palette-card" id="constantsPalette"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <h6 class="fw-semibold mt-4">Formula Preview</h6>
        <div id="formulaPreview" class="alert alert-primary mb-0 formula-preview">—</div>
    </div>
</div>

<input type="hidden" name="variables_json" id="variablesJson">
<input type="hidden" name="formula_json" id="formulaJson">
<input type="hidden" name="formula_text" id="formulaTextHidden">

<div class="d-flex justify-content-end gap-2 mt-2">
    <a href="{{ route('budget.me-configuration.definitions.index') }}" class="btn btn-light border">Cancel</a>
    <button class="btn btn-success" type="submit"><i class="feather-save me-1"></i> Save Definition</button>
</div>

@push('scripts')
<script>
    const varsState = @json($varsState);
    const formulaInput = document.getElementById('formulaInput');
    const preview = document.getElementById('formulaPreview');
    const operators = ['+','-','*','/','(',')','^'];
    const excelFns = ['SUM','AVERAGE','MIN','MAX','COUNT','ROUND','POWER','SQRT','ABS','IF','AND','OR','NOT','LN','LOG','EXP'];
    const constants = [{label:'π', token:'PI'}, {label:'g', token:'9.81'}, {label:'e', token:'2.71828'}];
    const varPalette = ['#2563eb','#16a34a','#f59e0b','#d946ef','#0ea5e9','#ef4444','#10b981','#6366f1'];

    function insertAtCursor(token) {
        const start = formulaInput.selectionStart ?? formulaInput.value.length;
        const end = formulaInput.selectionEnd ?? formulaInput.value.length;
        const text = formulaInput.value;
        formulaInput.value = text.slice(0,start) + token + text.slice(end);
        const pos = start + token.length;
        formulaInput.setSelectionRange(pos,pos);
        formulaInput.focus();
        updatePreview();
    }

    function renderVariables() {
        const wrap = document.getElementById('variablesList');
        wrap.innerHTML = '';
        varsState.forEach((v, idx) => {
            const color = v.color || varPalette[idx % varPalette.length];
            v.color = color; // ensure persisted
            const chip = document.createElement('span');
            chip.className = 'var-chip token-chip';
            chip.style.backgroundColor = color;
            chip.style.borderColor = color;
            chip.innerHTML = `<span class="var-dot"></span><span>${v.label}</span>`;
            chip.onclick = () => insertAtCursor(v.label);
            wrap.appendChild(chip);
        });
        syncHidden();
    }

    function renderOps() {
        const wrap = document.getElementById('operatorButtons');
        wrap.innerHTML = '';
        // Fraction placeholder (click or drag onto editor)
        const fracBtn = document.createElement('button');
        fracBtn.type = 'button';
        fracBtn.className = 'btn btn-outline-primary btn-sm';
        fracBtn.textContent = 'a/b';
        const fracToken = '(□)/(□)';
        fracBtn.onclick = () => insertFractionFromEditor(fracToken);
        // draggable
        fracBtn.draggable = true;
        fracBtn.addEventListener('dragstart', (e) => {
            e.dataTransfer.setData('text/plain', fracToken);
        });
        wrap.appendChild(fracBtn);

        operators.forEach(op => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-outline-secondary btn-sm';
            btn.textContent = op;
            btn.onclick = () => insertAtCursor(' '+op+' ');
            wrap.appendChild(btn);
        });
    }

    function renderFns() {
        const wrap = document.getElementById('functionsPalette');
        wrap.innerHTML = '';
        excelFns.forEach(fn => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-outline-info btn-sm me-1 mb-1';
            btn.textContent = fn+'()';
            btn.onclick = () => insertAtCursor(fn+'()');
            wrap.appendChild(btn);
        });
    }

    function renderConsts() {
        const wrap = document.getElementById('constantsPalette');
        wrap.innerHTML = '';
        constants.forEach(c => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-outline-dark btn-sm me-1 mb-1';
            btn.textContent = c.label;
            btn.onclick = () => insertAtCursor(c.token);
            wrap.appendChild(btn);
        });
    }

    // Fallback accordion toggle when Bootstrap JS is not available
    if (typeof bootstrap === 'undefined' || !bootstrap.Collapse) {
        document.querySelectorAll('#sideAccordion .accordion-button').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = btn.getAttribute('data-bs-target');
                const panel = targetId ? document.querySelector(targetId) : null;
                if (!panel) return;
                const isOpen = panel.classList.contains('show');
                panel.classList.toggle('show', !isOpen);
                btn.classList.toggle('collapsed', isOpen);
                btn.setAttribute('aria-expanded', (!isOpen).toString());
            });
        });
    }

    document.getElementById('toggleVarAdder').onclick = () => {
        document.getElementById('variableAdder').classList.toggle('d-none');
        document.getElementById('varNameInput').focus();
    };
    document.getElementById('saveVarBtn').onclick = () => {
        const name = document.getElementById('varNameInput').value.trim();
        if (!name) return;
        const color = varPalette[varsState.length % varPalette.length];
        varsState.push({ label: name, color });
        document.getElementById('varNameInput').value = '';
        document.getElementById('variableAdder').classList.add('d-none');
        renderVariables();
    };

    document.getElementById('clearFormula').onclick = () => { formulaInput.value=''; updatePreview(); };
    document.getElementById('saveFormulaDraft').onclick = () => updatePreview();
    formulaInput.addEventListener('input', updatePreview);

    function insertFractionFromEditor(token='(□)/(□)') {
        insertAtCursor(token);
    }

    function escapeHtml(str) {
        return str.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function renderFractions(raw) {
        const re = /\(([^()]*)\)\/\(([^()]*)\)/g;
        let out = '';
        let last = 0;
        let m;
        while ((m = re.exec(raw)) !== null) {
            out += escapeHtml(raw.slice(last, m.index));
            const num = m[1].trim() || '□';
            const den = m[2].trim() || '□';
            out += `<span class="frac"><span class="num">${escapeHtml(num)}</span><span class="bar"></span><span class="den">${escapeHtml(den)}</span></span>`;
            last = re.lastIndex;
        }
        out += escapeHtml(raw.slice(last));
        return out;
    }

    function updatePreview() {
        const raw = formulaInput.value.trim();
        const html = raw ? renderFractions(raw) : '';
        preview.innerHTML = html || '—';
        syncHidden();
    }

    function syncHidden() {
        document.getElementById('variablesJson').value = JSON.stringify(varsState);
        document.getElementById('formulaJson').value = JSON.stringify({ mode:'expression', expression: formulaInput.value.trim() });
        document.getElementById('formulaTextHidden').value = formulaInput.value.trim();
    }

    // Enable drop from draggable tokens onto editor
    formulaInput.addEventListener('dragover', (e) => { e.preventDefault(); });
    formulaInput.addEventListener('drop', (e) => {
        e.preventDefault();
        const token = e.dataTransfer.getData('text/plain');
        if (token) insertAtCursor(token);
    });

    renderVariables();
    renderOps();
    renderFns();
    renderConsts();
    updatePreview();
</script>
@endpush
