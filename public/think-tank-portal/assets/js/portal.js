/**
 * Africa Think Tank Platform — portal interactions.
 * Kept independent from the ATTP administration JavaScript.
 */
(() => {
            const startPortalShell = () => {
                const shell = document.querySelector('[data-tt-portal-shell]');
                if (!shell) return;

                const drawer = shell.querySelector('[data-tt-settings-drawer]');
                const account = shell.querySelector('[data-tt-account-menu]');
                const preferencesForm = shell.querySelector('[data-tt-preferences-form]');
                const colourInput = shell.querySelector('[data-tt-colour-input]');
                const logoSource = shell.querySelector('[data-tt-logo-source]');
                const desktop = window.matchMedia('(min-width: 1121px)');
                const portalSearch = shell.querySelector('[data-tt-search]');
                const searchInput = shell.querySelector('[data-tt-search-input]');
                const searchResults = shell.querySelector('[data-tt-search-results]');
                const searchItems = [...shell.querySelectorAll('[data-tt-search-item]')];
                const searchEmpty = shell.querySelector('[data-tt-search-empty]');
                const navGroups = [...shell.querySelectorAll('[data-tt-nav-group]')];

                if (desktop.matches && shell.dataset.sidebarMode === 'compact') {
                    navGroups.forEach((group) => { group.open = false; });
                }

                navGroups.forEach((group) => {
                    group.addEventListener('toggle', () => {
                        if (!group.open) return;

                        if (desktop.matches && shell.dataset.sidebarMode === 'compact') {
                            shell.dataset.sidebarMode = 'expanded';
                            const expandedOption = preferencesForm?.querySelector('[name="sidebar_mode"][value="expanded"]');
                            if (expandedOption) expandedOption.checked = true;
                        }

                        navGroups.forEach((otherGroup) => {
                            if (otherGroup !== group) otherGroup.open = false;
                        });
                    });
                });

                const legacyCreatePlanTarget = shell.querySelector('[data-ppl-create-page-url]');
                if (legacyCreatePlanTarget && window.location.hash === '#new-plan') {
                    window.location.replace(legacyCreatePlanTarget.dataset.pplCreatePageUrl);
                    return;
                }

                shell.querySelectorAll('[data-tet-criteria-form]').forEach((form) => {
                    const rows = form.querySelector('[data-tet-rows]');
                    const total = form.querySelector('[data-tet-total]');
                    let nextIndex = rows?.querySelectorAll('[data-tet-row]').length || 0;

                    const updateTotal = () => {
                        if (!total) return;
                        const points = [...form.querySelectorAll('[data-tet-score]')]
                            .reduce((sum, input) => sum + (Number.parseFloat(input.value) || 0), 0);
                        const rounded = Math.round(points * 100) / 100;
                        total.textContent = `${rounded} / 100`;
                        total.classList.toggle('is-valid', Math.abs(points - 100) < 0.001);
                        total.classList.toggle('is-invalid', Math.abs(points - 100) >= 0.001);
                    };

                    const bindRow = (row) => {
                        row.querySelector('[data-tet-remove]')?.addEventListener('click', () => {
                            if ((rows?.querySelectorAll('[data-tet-row]').length || 0) <= 1) return;
                            row.remove();
                            updateTotal();
                        });
                        row.querySelector('[data-tet-score]')?.addEventListener('input', updateTotal);
                    };

                    rows?.querySelectorAll('[data-tet-row]').forEach(bindRow);
                    form.querySelector('[data-tet-add]')?.addEventListener('click', () => {
                        if (!rows || rows.querySelectorAll('[data-tet-row]').length >= 30) return;
                        const row = document.createElement('div');
                        row.className = 'tet-builder-row';
                        row.dataset.tetRow = '';
                        row.innerHTML = `
                            <input name="criteria[${nextIndex}][name]" placeholder="Criterion name" required>
                            <input name="criteria[${nextIndex}][description]" placeholder="Scoring guidance">
                            <label><input type="number" name="criteria[${nextIndex}][max_score]" min=".01" max="100" step=".01" required data-tet-score><span>points</span></label>
                            <button type="button" data-tet-remove aria-label="Remove criterion"><i class="feather-trash-2"></i></button>`;
                        nextIndex += 1;
                        rows.appendChild(row);
                        bindRow(row);
                        row.querySelector('input')?.focus();
                        updateTotal();
                    });
                    updateTotal();
                });

                const setSearchOpen = (open) => {
                    if (!searchResults || !searchInput) return;
                    searchResults.hidden = !open;
                    searchInput.setAttribute('aria-expanded', open ? 'true' : 'false');
                };

                const filterSearch = () => {
                    const query = (searchInput?.value || '').trim().toLowerCase();
                    let visible = 0;
                    searchItems.forEach((item) => {
                        const matches = query === '' || (item.dataset.searchText || '').includes(query);
                        item.hidden = !matches;
                        if (matches) visible += 1;
                    });
                    if (searchEmpty) searchEmpty.hidden = visible !== 0;
                    setSearchOpen(true);
                };

                searchInput?.addEventListener('focus', filterSearch);
                searchInput?.addEventListener('input', filterSearch);

                const setSettings = (open) => {
                    shell.classList.toggle('is-settings-open', open);
                    drawer?.setAttribute('aria-hidden', open ? 'false' : 'true');
                    document.body.style.overflow = open ? 'hidden' : '';
                    if (open) drawer?.querySelector('button, input')?.focus();
                };

                shell.querySelectorAll('[data-tt-settings-open]').forEach((button) => button.addEventListener('click', () => {
                    if (account?.open) account.open = false;
                    setSettings(true);
                }));
                shell.querySelectorAll('[data-tt-settings-close]').forEach((button) => button.addEventListener('click', () => setSettings(false)));

                shell.querySelector('[data-tt-sidebar-toggle]')?.addEventListener('click', () => {
                    if (!desktop.matches) {
                        shell.classList.toggle('is-mobile-nav-open');
                        return;
                    }
                    const next = shell.dataset.sidebarMode === 'compact' ? 'expanded' : 'compact';
                    shell.dataset.sidebarMode = next;
                    if (next === 'compact') {
                        navGroups.forEach((group) => { group.open = false; });
                    } else {
                        const activeGroup = navGroups.find((group) => group.classList.contains('is-active'));
                        if (activeGroup) activeGroup.open = true;
                    }
                    const option = preferencesForm?.querySelector(`[name="sidebar_mode"][value="${next}"]`);
                    if (option) option.checked = true;
                });
                shell.querySelector('[data-tt-mobile-overlay]')?.addEventListener('click', () => shell.classList.remove('is-mobile-nav-open'));
                shell.querySelectorAll('.tt-sidebar a').forEach((link) => link.addEventListener('click', () => shell.classList.remove('is-mobile-nav-open')));

                const resolveTheme = (preference) => preference === 'system'
                    ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                    : preference;
                const applyTheme = (preference) => {
                    const resolved = resolveTheme(preference);
                    document.documentElement.dataset.theme = resolved;
                    document.documentElement.dataset.themePreference = preference;
                    const icon = shell.querySelector('[data-tt-theme-icon]');
                    if (icon) icon.className = resolved === 'dark' ? 'feather-sun' : 'feather-moon';
                };

                preferencesForm?.querySelectorAll('[data-tt-theme-option]').forEach((input) => input.addEventListener('change', () => applyTheme(input.value)));
                shell.querySelector('[data-tt-theme-toggle]')?.addEventListener('click', () => {
                    const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
                    const option = preferencesForm?.querySelector(`[name="theme_mode"][value="${next}"]`);
                    if (option) option.checked = true;
                    applyTheme(next);
                });
                applyTheme(document.documentElement.dataset.themePreference || 'system');

                const applyColour = (colour) => {
                    if (!/^#[0-9a-f]{6}$/i.test(colour)) return;
                    shell.style.setProperty('--tt-personal-accent', colour);
                    if (colourInput) colourInput.value = colour;
                    shell.querySelectorAll('[data-tt-colour]').forEach((swatch) => swatch.classList.toggle('is-active', swatch.dataset.ttColour.toLowerCase() === colour.toLowerCase()));
                };
                shell.querySelectorAll('[data-tt-colour]').forEach((swatch) => swatch.addEventListener('click', () => applyColour(swatch.dataset.ttColour)));
                colourInput?.addEventListener('input', () => applyColour(colourInput.value));

                shell.querySelectorAll('[data-tt-sidebar-option]').forEach((input) => input.addEventListener('change', () => {
                    shell.dataset.sidebarMode = input.value;
                }));

                shell.querySelector('[data-tt-pick-logo-colour]')?.addEventListener('click', () => {
                    if (!logoSource) return;
                    try {
                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d', { willReadFrequently: true });
                        canvas.width = 48;
                        canvas.height = 48;
                        context.drawImage(logoSource, 0, 0, 48, 48);
                        const pixels = context.getImageData(0, 0, 48, 48).data;
                        const buckets = new Map();
                        for (let index = 0; index < pixels.length; index += 16) {
                            const red = pixels[index];
                            const green = pixels[index + 1];
                            const blue = pixels[index + 2];
                            const alpha = pixels[index + 3];
                            const maximum = Math.max(red, green, blue);
                            const minimum = Math.min(red, green, blue);
                            if (alpha < 180 || maximum > 238 || maximum < 45 || maximum - minimum < 28) continue;
                            const key = [red, green, blue].map((value) => Math.round(value / 32) * 32).join(',');
                            buckets.set(key, (buckets.get(key) || 0) + (maximum - minimum));
                        }
                        const winner = [...buckets.entries()].sort((a, b) => b[1] - a[1])[0]?.[0];
                        if (!winner) return;
                        const hex = winner.split(',').map((value) => Math.min(255, Number(value)).toString(16).padStart(2, '0')).join('');
                        applyColour(`#${hex}`);
                    } catch (error) {
                        console.warn('The logo colour could not be sampled.', error);
                    }
                });

                shell.querySelector('[data-tt-logo-input]')?.addEventListener('change', (event) => {
                    const file = event.target.files?.[0];
                    const preview = shell.querySelector('[data-tt-brand-preview]');
                    if (!file || !preview) return;
                    const url = URL.createObjectURL(file);
                    if (preview.tagName === 'IMG') preview.src = url;
                    else {
                        const image = document.createElement('img');
                        image.src = url;
                        image.alt = 'New logo preview';
                        preview.replaceWith(image);
                    }
                });

                document.addEventListener('click', (event) => {
                    if (account?.open && !account.contains(event.target)) account.open = false;
                    if (portalSearch && !portalSearch.contains(event.target)) setSearchOpen(false);
                    shell.querySelectorAll('.lang-switcher.open').forEach((selector) => {
                        if (selector.contains(event.target)) return;
                        selector.classList.remove('open');
                        selector.querySelector('.lang-btn')?.setAttribute('aria-expanded', 'false');
                    });
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === '/' && !['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement?.tagName)) {
                        event.preventDefault();
                        searchInput?.focus();
                        return;
                    }
                    if (event.key !== 'Escape') return;
                    if (shell.classList.contains('is-settings-open')) setSettings(false);
                    shell.classList.remove('is-mobile-nav-open');
                    if (account?.open) account.open = false;
                    shell.querySelectorAll('.lang-switcher.open').forEach((selector) => {
                        selector.classList.remove('open');
                        selector.querySelector('.lang-btn')?.setAttribute('aria-expanded', 'false');
                    });
                    setSearchOpen(false);
                });
            };

            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', startPortalShell, { once: true });
            else startPortalShell();
        })();
