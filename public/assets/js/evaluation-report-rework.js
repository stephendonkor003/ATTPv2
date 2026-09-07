(() => {
    function initialise() {
        document.querySelectorAll('[data-evaluation-rework-panel]').forEach(panel => {
            if (panel.dataset.reworkReady) return;
            panel.dataset.reworkReady = 'true';
            const groups = [...panel.querySelectorAll('[data-rework-evaluator]')];
            const search = panel.querySelector('[data-rework-search]');
            search?.addEventListener('input', () => {
                const query = search.value.trim().toLocaleLowerCase();
                groups.forEach(group => { group.hidden = !(group.dataset.search || '').toLocaleLowerCase().includes(query); });
                const empty = panel.querySelector('[data-rework-no-matches]');
                if (empty) empty.hidden = groups.some(group => !group.hidden);
            });
            groups.forEach(group => {
                group.addEventListener('toggle', () => {
                    if (group.open) groups.forEach(other => { if (other !== group) other.open = false; });
                });
            });
            panel.querySelectorAll('[data-report-rework-form]').forEach(form => {
                const choices = [...form.querySelectorAll('input[name="submission_ids[]"]')];
                const reason = form.querySelector('textarea[name="reason"]');
                const send = form.querySelector('[data-rework-send]');
                const selection = form.querySelector('[data-rework-selection]');
                const count = form.querySelector('[data-rework-reason-count]');
                const feedback = form.querySelector('[data-rework-form-feedback]');
                const overrideBox = form.querySelector('[data-rework-override]');
                const override = overrideBox?.querySelector('input');
                const selectAll = form.querySelector('[data-rework-select-all]');
                const clear = form.querySelector('[data-rework-clear]');
                const maximum = Number(form.dataset.maxSelections) || 50;
                if (!reason || !send) return;
                const selected = () => choices.filter(input => input.checked && !input.disabled);
                const sync = () => {
                    const records = selected();
                    selection.textContent = `${records.length} of ${choices.length} evaluations selected`;
                    count.textContent = `${reason.value.length.toLocaleString()} / 5,000`;
                    const requiresOverride = records.some(input => input.dataset.requiresOverride === '1');
                    if (override) {
                        overrideBox.hidden = !requiresOverride;
                        override.required = requiresOverride;
                        override.disabled = !requiresOverride;
                        if (!requiresOverride) override.checked = false;
                    }
                    send.disabled = form.dataset.submitting === 'true' || records.length === 0 || records.length > maximum || reason.value.trim().length < 10 || (requiresOverride && !override?.checked);
                    if (form.dataset.submitting !== 'true') send.textContent = records.length > 0 ? `Request rework for ${records.length} ${records.length === 1 ? 'evaluation' : 'evaluations'}` : 'Request rework';
                    if (selectAll) selectAll.disabled = records.length >= Math.min(choices.length, maximum);
                    if (clear) clear.disabled = records.length === 0;
                    if (records.length > maximum) explain(`Select no more than ${maximum} evaluations in one request.`);
                };
                const explain = message => { feedback.hidden = false; feedback.textContent = message; };
                form.addEventListener('input', () => { reason.setCustomValidity(''); feedback.hidden = true; sync(); });
                form.addEventListener('change', sync);
                selectAll?.addEventListener('click', () => { choices.forEach((input, index) => { if (!input.disabled) input.checked = index < maximum; }); sync(); });
                clear?.addEventListener('click', () => { choices.forEach(input => { input.checked = false; }); sync(); });
                form.addEventListener('submit', event => {
                    if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
                    if (selected().length === 0) { event.preventDefault(); explain('Select at least one submitted evaluation to return for correction.'); choices[0]?.focus(); return; }
                    if (selected().length > maximum) { event.preventDefault(); explain(`Select no more than ${maximum} evaluations in one request.`); return; }
                    reason.value = reason.value.trim();
                    if (reason.value.length < 10) {
                        event.preventDefault(); reason.setCustomValidity('Please provide at least 10 characters explaining what the evaluator should correct.'); reason.reportValidity(); sync(); return;
                    }
                    if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); return; }
                    form.dataset.submitting = 'true'; send.disabled = true; send.textContent = 'Requesting rework...';
                });
                window.addEventListener('pageshow', () => { delete form.dataset.submitting; sync(); });
                sync();
            });
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialise, { once: true });
    else initialise();
})();
