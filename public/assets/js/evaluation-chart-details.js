(() => {
    'use strict';
    if (window.attpEvaluationChartDetails) return;
    window.attpEvaluationChartDetails = true;

    function initialize() {
        const figures = [...document.querySelectorAll('[data-eval-chart]')];
        if (!figures.length) return;
        const tooltip = document.createElement('div');
        tooltip.className = 'eval-chart-tooltip';
        tooltip.id = 'eval-chart-detail-tooltip';
        tooltip.role = 'tooltip';
        tooltip.hidden = true;
        document.body.append(tooltip);
        let active = null;
        let pinned = false;
        let hideTimer;
        let pendingFocus = null;
        let focusSettleTimer = 0;

        const element = (tag, className, text) => {
            const node = document.createElement(tag);
            if (className) node.className = className;
            if (text !== undefined) node.textContent = String(text);
            return node;
        };
        const cancelPendingFocus = () => {
            clearTimeout(focusSettleTimer);
            focusSettleTimer = 0;
            pendingFocus = null;
        };
        const hide = (cancelFocus = true) => {
            if (cancelFocus) cancelPendingFocus();
            clearTimeout(hideTimer);
            if (active) {
                active.classList.remove('is-active');
                active.removeAttribute('aria-describedby');
            }
            active = null;
            pinned = false;
            tooltip.hidden = true;
        };
        const scheduleHide = () => {
            if (!pinned && document.activeElement !== active) hideTimer = setTimeout(hide, 180);
        };
        const position = (target, pointer) => {
            const bounds = target.getBoundingClientRect();
            const anchorX = pointer?.clientX ?? bounds.left + bounds.width / 2;
            const anchorY = pointer?.clientY ?? bounds.top;
            const box = tooltip.getBoundingClientRect();
            const inset = 12;
            let left = anchorX + 16;
            let top = anchorY - box.height - 14;
            if (left + box.width > window.innerWidth - inset) left = anchorX - box.width - 16;
            if (top < inset) top = anchorY + 20;
            tooltip.style.left = `${Math.max(inset, Math.min(left, window.innerWidth - box.width - inset))}px`;
            tooltip.style.top = `${Math.max(inset, Math.min(top, window.innerHeight - box.height - inset))}px`;
        };
        const show = (figure, target, records, pointer) => {
            const record = records[Number(target.dataset.evalRecord)];
            if (!record) return;
            cancelPendingFocus();
            clearTimeout(hideTimer);
            if (active && active !== target) {
                active.classList.remove('is-active');
                active.removeAttribute('aria-describedby');
                pinned = false;
            }
            const pie = figure.dataset.evalKind === 'pie';
            const color = /^#[a-f0-9]{6}$/i.test(record.color) ? record.color : '#176b87';
            if (active !== target || tooltip.hidden) {
                const heading = element('div', 'eval-chart-tooltip-heading', pie ? record.name : record.applicant);
                const series = element('div', 'eval-chart-tooltip-series');
                const swatch = element('span', 'eval-chart-swatch');
                swatch.style.backgroundColor = color;
                swatch.setAttribute('aria-hidden', 'true');
                series.append(swatch, element('span', '', pie ? 'Recorded outcome' : record.evaluator));
                const values = element('dl', 'eval-chart-tooltip-values');
                const addValue = (label, value) => {
                    const row = element('div', 'eval-chart-tooltip-value');
                    row.append(element('dt', '', label), element('dd', '', value));
                    values.append(row);
                };
                addValue(pie ? 'Recorded count' : record.metric || 'Score', record.display_value);
                if (pie) addValue('Share of recorded total', record.display_percentage);
                tooltip.replaceChildren(element('span', 'eval-chart-tooltip-kicker', pie ? 'Outcome details' : 'Applicant score'), heading, series, values,
                    element('p', 'eval-chart-tooltip-footnote', 'Escape or tap outside to close. Arrow keys compare chart values.'));
                if (figure.dataset.evalKind === 'line') {
                    const coincident = records.filter((other) => other !== record && other.applicant === record.applicant && (other.value ?? 0) === (record.value ?? 0));
                    if (coincident.length) {
                        const comparison = element('div', 'eval-chart-tooltip-comparison');
                        comparison.append(element('span', 'eval-chart-tooltip-kicker', 'At the same chart position'));
                        coincident.forEach((other) => {
                            const row = element('div', 'eval-chart-tooltip-comparison-row');
                            const otherSwatch = element('span', 'eval-chart-swatch');
                            otherSwatch.style.backgroundColor = /^#[a-f0-9]{6}$/i.test(other.color) ? other.color : '#176b87';
                            otherSwatch.setAttribute('aria-hidden', 'true');
                            row.append(otherSwatch, element('span', '', other.evaluator), element('strong', '', other.display_value));
                            comparison.append(row);
                        });
                        tooltip.insertBefore(comparison, tooltip.lastChild);
                    }
                }
                tooltip.style.setProperty('--eval-point-color', color);
            }
            active = target;
            target.classList.add('is-active');
            target.setAttribute('aria-describedby', tooltip.id);
            tooltip.hidden = false;
            position(target, pointer);
        };
        const revealWhenFocusScrollSettles = () => {
            clearTimeout(focusSettleTimer);
            focusSettleTimer = setTimeout(() => {
                const pending = pendingFocus;
                if (pending && document.activeElement === pending.target) show(pending.figure, pending.target, pending.records);
                else cancelPendingFocus();
            }, 120);
        };
        const showAfterFocusScroll = (figure, target, records) => {
            hide();
            pendingFocus = { figure, target, records };
            // The report uses smooth scrolling: native focus can keep moving
            // the viewport for a full second. Each scroll resets this idle
            // timer; Escape or a user pan cancels the pending reveal entirely.
            revealWhenFocusScrollSettles();
        };

        figures.forEach((figure) => {
            let records;
            try { records = JSON.parse(figure.dataset.evalRecords || '[]'); } catch { return; }
            const allTargets = [...figure.querySelectorAll('.eval-chart-target')];
            const targets = [...new Map(allTargets.map((target) => [target.dataset.evalRecord, target])).values()]
                .sort((left, right) => Number(left.dataset.evalRecord) - Number(right.dataset.evalRecord));
            if (!targets.length || !Array.isArray(records)) return;
            // One Tab stop per graph; arrows expose every observation, including
            // coincident points, recorded zeroes and missing observations.
            allTargets.forEach((target) => {
                target.setAttribute('tabindex', target === targets[0] ? '0' : '-1');
                // Native titles remain available when JavaScript is disabled;
                // remove the duplicate browser popup after enhancement.
                target.querySelector('title')?.remove();
            });
            const help = figure.querySelector('.eval-chart-help');
            if (help) help.hidden = false;
            const targetFor = (event) => event.target instanceof Element ? event.target.closest('.eval-chart-target') : null;
            figure.addEventListener('pointerover', (event) => {
                const target = targetFor(event);
                if (target && event.pointerType !== 'touch' && !pinned && !pendingFocus) show(figure, target, records, event);
            });
            figure.addEventListener('pointermove', (event) => {
                const target = targetFor(event);
                if (target && event.pointerType !== 'touch' && !pinned && !pendingFocus) show(figure, target, records, event);
            });
            figure.addEventListener('pointerout', (event) => {
                if (active && !active.contains(event.relatedTarget) && !tooltip.contains(event.relatedTarget)) scheduleHide();
            });
            figure.addEventListener('focusin', (event) => {
                const target = targetFor(event);
                if (target) showAfterFocusScroll(figure, target, records);
            });
            figure.addEventListener('focusout', (event) => {
                const nextTarget = event.relatedTarget instanceof Element ? event.relatedTarget.closest('.eval-chart-target') : null;
                if (!nextTarget || !figure.contains(nextTarget)) hide();
            });
            figure.addEventListener('click', (event) => {
                const target = targetFor(event);
                if (!target) return;
                if (active === target && pinned) return hide();
                show(figure, target, records);
                pinned = true;
            });
            figure.addEventListener('keydown', (event) => {
                const target = targetFor(event);
                if (!target) return;
                const index = targets.findIndex((point) => point.dataset.evalRecord === target.dataset.evalRecord);
                let next;
                if (event.key === 'ArrowRight' || event.key === 'ArrowDown') next = (index + 1) % targets.length;
                if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') next = (index + targets.length - 1) % targets.length;
                if (event.key === 'Home') next = 0;
                if (event.key === 'End') next = targets.length - 1;
                if (next !== undefined) {
                    event.preventDefault();
                    targets.forEach((point) => point.setAttribute('tabindex', '-1'));
                    targets[next].setAttribute('tabindex', '0');
                    targets[next].focus({ preventScroll: true });
                    targets[next].scrollIntoView({ block: 'nearest', inline: 'nearest' });
                    showAfterFocusScroll(figure, targets[next], records);
                } else if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    if (active === target && pinned) hide();
                    else { show(figure, target, records); pinned = true; }
                }
            });
        });
        tooltip.addEventListener('pointerenter', () => clearTimeout(hideTimer));
        tooltip.addEventListener('pointerleave', scheduleHide);
        document.addEventListener('keydown', (event) => {
            if (['Escape', 'PageUp', 'PageDown'].includes(event.key)) hide();
        });
        document.addEventListener('pointerdown', (event) => {
            const selected = active || pendingFocus?.target;
            if (selected && !selected.contains(event.target) && !tooltip.contains(event.target)) hide();
        });
        document.addEventListener('wheel', (event) => { if (!tooltip.contains(event.target)) hide(); }, { passive: true });
        document.addEventListener('touchmove', (event) => { if (!tooltip.contains(event.target)) hide(); }, { passive: true });
        // Native pan/scroll remains available on touch devices; do not trap a
        // document or chart scroll behind a stale, detached tooltip.
        window.addEventListener('scroll', (event) => {
            if (event.target !== tooltip && !(event.target instanceof Node && tooltip.contains(event.target))) {
                const focusIsScrolling = pendingFocus && document.activeElement === pendingFocus.target;
                hide(!focusIsScrolling);
                if (focusIsScrolling) revealWhenFocusScrollSettles();
            }
        }, { capture: true, passive: true });
        window.addEventListener('resize', hide, { passive: true });
        window.addEventListener('beforeprint', hide);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
})();
