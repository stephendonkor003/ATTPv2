<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (!window.bootstrap?.Modal || typeof window.fetch !== 'function') {
            return;
        }

        const modalElements = new Map(
            Array.from(document.querySelectorAll('[data-inline-config-modal]'))
                .map((element) => [element.dataset.inlineConfigModal, element])
        );

        const messagesFor = (errors) => {
            const messages = [];

            Object.values(errors || {}).forEach((value) => {
                const values = Array.isArray(value) ? value : [value];
                values.forEach((message) => {
                    const text = String(message || '').trim();
                    if (text && !messages.includes(text)) {
                        messages.push(text);
                    }
                });
            });

            return messages;
        };

        const errorFeedbackFor = (form, fieldName) => Array.from(
            form.querySelectorAll('[data-inline-error-for]')
        ).find((feedback) => feedback.dataset.inlineErrorFor === fieldName);

        const clearFormErrors = (form) => {
            const summary = form.querySelector('[data-inline-error-summary]');
            const list = form.querySelector('[data-inline-error-list]');

            summary?.classList.add('d-none');
            if (list) {
                list.replaceChildren();
            }

            form.querySelectorAll('[data-inline-config-field]').forEach((field) => {
                field.classList.remove('is-invalid');
                field.removeAttribute('aria-invalid');

                const originalDescription = field.dataset.inlineOriginalDescribedby || '';
                if (originalDescription) {
                    field.setAttribute('aria-describedby', originalDescription);
                } else {
                    field.removeAttribute('aria-describedby');
                }
            });

            form.querySelectorAll('[data-inline-error-for]').forEach((feedback) => {
                feedback.textContent = '';
                feedback.classList.remove('d-block');
            });
        };

        const showFormErrors = (form, errors = {}, fallbackMessage = '') => {
            clearFormErrors(form);

            const summary = form.querySelector('[data-inline-error-summary]');
            const list = form.querySelector('[data-inline-error-list]');
            const messages = messagesFor(errors);
            const generalMessage = String(fallbackMessage || '').trim();

            if (!messages.length && generalMessage) {
                messages.push(generalMessage);
            }
            if (!messages.length) {
                messages.push('Please check the information and try again.');
            }

            Object.entries(errors || {}).forEach(([fieldName, value]) => {
                const fieldValue = Array.isArray(value) ? value[0] : value;
                const message = String(fieldValue || '').trim();
                const field = form.elements.namedItem(fieldName);
                const feedback = errorFeedbackFor(form, fieldName);

                if (field instanceof HTMLElement) {
                    field.classList.add('is-invalid');
                    field.setAttribute('aria-invalid', 'true');

                    if (feedback?.id) {
                        const describedBy = new Set(
                            (field.getAttribute('aria-describedby') || '')
                                .split(/\s+/)
                                .filter(Boolean)
                        );
                        describedBy.add(feedback.id);
                        field.setAttribute('aria-describedby', Array.from(describedBy).join(' '));
                    }
                }

                if (feedback) {
                    feedback.textContent = message;
                    feedback.classList.add('d-block');
                }
            });

            if (list) {
                messages.forEach((message) => {
                    const item = document.createElement('li');
                    item.textContent = message;
                    list.appendChild(item);
                });
            }
            summary?.classList.remove('d-none');

            const firstInvalid = Array.from(form.querySelectorAll('.is-invalid'))
                .find((field) => field instanceof HTMLElement && field.getAttribute('type') !== 'hidden');

            window.requestAnimationFrame(() => {
                if (firstInvalid) {
                    firstInvalid.focus();
                } else {
                    summary?.focus();
                }
            });
        };

        const setSubmitting = (form, submitting) => {
            const submit = form.querySelector('[data-inline-submit]');
            const spinner = form.querySelector('[data-inline-submit-spinner]');
            const icon = form.querySelector('[data-inline-submit-icon]');
            const label = form.querySelector('[data-inline-submit-label]');

            form.setAttribute('aria-busy', submitting ? 'true' : 'false');
            if (submit) {
                submit.disabled = submitting;
            }
            spinner?.classList.toggle('d-none', !submitting);
            icon?.classList.toggle('d-none', submitting);
            if (label) {
                label.textContent = submitting ? 'Creating...' : 'Create and select';
            }
        };

        const responseFailureMessage = (status, payload) => {
            if (status === 401) {
                return 'Your sign-in has expired. Sign in again, then retry.';
            }
            if (status === 403) {
                return 'You do not have permission to create this item.';
            }
            if (status === 419) {
                return 'Your session has expired. Refresh the page, then try again.';
            }
            if (status === 429) {
                return 'Too many requests were sent. Wait a moment, then try again.';
            }
            if (status >= 500) {
                return 'The server could not complete the request. Please try again.';
            }

            return String(payload?.message || 'The item could not be created.').trim();
        };

        const optionLabel = (kind, form, data) => {
            const suppliedLabel = String(data?.label || '').trim();
            const name = String(data?.name || '').trim();
            const symbol = String(data?.symbol || '').trim();
            let label = suppliedLabel || (kind === 'unit' && symbol ? `${name} (${symbol})` : name);
            const portfolio = form.elements.namedItem('portfolio_id');

            if (portfolio instanceof HTMLSelectElement && portfolio.value) {
                const portfolioName = String(
                    data?.portfolio_name
                    || portfolio.options[portfolio.selectedIndex]?.textContent
                    || ''
                ).trim();
                const suffix = portfolioName ? ` \u2014 ${portfolioName}` : '';

                if (suffix && !label.endsWith(suffix)) {
                    label += suffix;
                }
            }

            return label;
        };

        const selectCreatedItem = (kind, form, data) => {
            const selectId = form.dataset.inlineTargetSelect;
            const select = selectId ? document.getElementById(selectId) : null;
            const id = String(data?.id || '').trim();
            const label = optionLabel(kind, form, data);

            if (!(select instanceof HTMLSelectElement) || !id || !label) {
                throw new Error('The item was created, but the page could not select it. Refresh the page to continue.');
            }

            let option = Array.from(select.options).find((item) => item.value === id);
            if (!option) {
                option = new Option(label, id, false, true);
                select.add(option);
            } else {
                option.textContent = label;
                option.selected = true;
            }

            const portfolio = form.elements.namedItem('portfolio_id');
            const portfolioId = String(
                data?.portfolio_id
                || (portfolio instanceof HTMLSelectElement || portfolio instanceof HTMLInputElement ? portfolio.value : '')
                || ''
            ).trim();
            if (portfolioId) {
                option.dataset.portfolioId = portfolioId;
                option.hidden = false;
                option.disabled = false;
            }

            select.value = id;
            select.classList.remove('is-invalid');
            select.removeAttribute('aria-invalid');
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));

            const status = document.querySelector(`[data-inline-selection-status="${kind}"]`);
            if (status) {
                status.textContent = `${label} was created and selected.`;
            }

            return select;
        };

        const initializeFrequencyInterval = (form) => {
            const unit = form.querySelector('[data-frequency-interval-unit]');
            const value = form.querySelector('[data-frequency-interval-value]');
            const wrapper = form.querySelector('[data-frequency-interval-value-wrap]');
            const hint = form.querySelector('[data-frequency-interval-hint]');

            if (!unit || !value || !wrapper || !hint) {
                return () => {};
            }

            const synchronize = () => {
                const selectedUnit = unit.value;
                const once = selectedUnit === 'once';

                wrapper.classList.toggle('d-none', once);
                value.disabled = once;
                value.required = !once;

                if (once) {
                    value.value = '';
                    hint.textContent = 'Use Once for a result that is reported only one time.';
                    return;
                }

                if (!value.value || Number(value.value) < 1) {
                    value.value = '1';
                }

                const interval = Number(value.value) || 1;
                const selectedLabel = unit.options[unit.selectedIndex]?.textContent.trim().toLowerCase() || 'period';
                const plural = interval === 1 ? selectedLabel : `${selectedLabel}s`;
                hint.textContent = `Reporting will occur every ${interval} ${plural}.`;
            };

            unit.addEventListener('change', synchronize);
            value.addEventListener('input', synchronize);
            synchronize();

            return synchronize;
        };

        const initializeFrequencyCode = (form) => {
            const name = form.querySelector('[name="name"]');
            const code = form.querySelector('[name="code"]');

            if (!(name instanceof HTMLInputElement) || !(code instanceof HTMLInputElement)) {
                return () => {};
            }

            let manuallyEdited = false;
            const suggestedCode = (value) => String(value || '')
                .normalize('NFKD')
                .replace(/[\u0300-\u036f]/g, '')
                .toUpperCase()
                .replace(/[^A-Z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '')
                .slice(0, 255);

            name.addEventListener('input', () => {
                if (!manuallyEdited) {
                    code.value = suggestedCode(name.value);
                }
            });

            code.addEventListener('input', (event) => {
                if (event.isTrusted) {
                    manuallyEdited = true;
                }
            });

            return () => {
                manuallyEdited = false;
                code.value = suggestedCode(name.value);
            };
        };

        modalElements.forEach((modalElement, kind) => {
            const form = modalElement.querySelector(`[data-inline-config-form="${kind}"]`);
            if (!(form instanceof HTMLFormElement) || form.dataset.inlineConfigReady === 'true') {
                return;
            }

            form.dataset.inlineConfigReady = 'true';
            form.querySelectorAll('[data-inline-config-field]').forEach((field) => {
                field.dataset.inlineOriginalDescribedby = field.getAttribute('aria-describedby') || '';
            });

            const synchronizeInterval = kind === 'frequency'
                ? initializeFrequencyInterval(form)
                : () => {};
            const resetFrequencyCode = kind === 'frequency'
                ? initializeFrequencyCode(form)
                : () => {};
            const modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
            const state = {
                trigger: null,
                successFocus: null,
                controller: null,
                submitting: false,
            };

            document.querySelectorAll(`[data-inline-config-open="${kind}"]`).forEach((trigger) => {
                trigger.addEventListener('click', (event) => {
                    event.preventDefault();
                    state.trigger = trigger;
                    state.successFocus = null;
                    clearFormErrors(form);
                    modal.show();
                });
            });

            modalElement.addEventListener('shown.bs.modal', () => {
                const portfolioField = form.elements.namedItem('portfolio_id');
                const autofocusField = form.querySelector('[data-inline-autofocus]');
                const needsPortfolio = portfolioField instanceof HTMLSelectElement && !portfolioField.value;
                const focusTarget = needsPortfolio ? portfolioField : autofocusField;
                focusTarget?.focus();
            });

            modalElement.addEventListener('hidden.bs.modal', () => {
                state.controller?.abort();
                state.controller = null;
                state.submitting = false;
                setSubmitting(form, false);
                form.reset();
                clearFormErrors(form);
                synchronizeInterval();
                resetFrequencyCode();

                const focusTarget = state.successFocus || state.trigger;
                state.successFocus = null;
                state.trigger = null;
                window.setTimeout(() => focusTarget?.focus({ preventScroll: true }), 0);
            });

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (state.submitting) {
                    return;
                }

                clearFormErrors(form);
                state.submitting = true;
                state.controller = new AbortController();
                setSubmitting(form, true);

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: new FormData(form),
                        signal: state.controller.signal,
                    });
                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        const errors = response.status === 422 && payload?.errors
                            ? payload.errors
                            : {};
                        showFormErrors(form, errors, responseFailureMessage(response.status, payload));
                        return;
                    }

                    if (!payload?.data) {
                        showFormErrors(form, {}, 'The server returned an incomplete response. Refresh the page and try again.');
                        return;
                    }

                    state.successFocus = selectCreatedItem(kind, form, payload.data);
                    modal.hide();
                } catch (error) {
                    if (error?.name !== 'AbortError') {
                        showFormErrors(
                            form,
                            {},
                            error?.message || 'The request could not be completed. Check your connection and try again.'
                        );
                    }
                } finally {
                    state.controller = null;
                    state.submitting = false;
                    setSubmitting(form, false);
                }
            });
        });
    });
</script>
