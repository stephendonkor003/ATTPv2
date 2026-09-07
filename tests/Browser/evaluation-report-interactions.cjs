/* Live HTTP regression. All POST/PUT/PATCH/DELETE requests are aborted before reaching Laravel. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {EventEmitter} = require('node:events');
const {chromium} = require('playwright');

const base = process.env.EVALUATION_BROWSER_BASE_URL || 'http://127.0.0.1:8000';
assert(['127.0.0.1', 'localhost', '[::1]'].includes(new URL(base).hostname), 'Only loopback application URLs are allowed.');
const scoredPath = process.env.EVALUATION_BROWSER_SCORED_PATH || '/reports/evaluations/method/services/procurement/attp-think-tank-call-for-applications-2025';
const pendingPath = process.env.EVALUATION_BROWSER_PENDING_PATH || '/reports/evaluations/method/services/procurement/selection-of-a-consulting-firm-to-conduct-a-feasibility-study-for-the-endowment-fund-and-designing-a-resource-mobilization-strategy-for-the-africa-think-tank-platform-endowment-fund';
const output = fs.mkdtempSync(path.join(os.tmpdir(), 'evaluation-report-interactions-'));
const helper = path.join(__dirname, 'evaluation-report-session.php');
const php = process.env.PHP_BINARY || 'php';
const results = {actualHttp: true, output, scoredPath, pendingPath, checks: [], pageErrors: [], cspViolations: [], blockedMutations: []};
const states = [];
const mutationEvents = new EventEmitter();

async function openReport(context, reportPath) {
    const started = Date.now();
    const page = await context.newPage();
    page.on('pageerror', error => results.pageErrors.push(error.stack || error.message));
    page.on('dialog', dialog => dialog.accept());
    await page.exposeFunction('recordEvaluationCsp', details => results.cspViolations.push(details));
    await page.addInitScript(() => document.addEventListener('securitypolicyviolation', event => window.recordEvaluationCsp({directive: event.violatedDirective, blocked: event.blockedURI})));
    await page.addInitScript(() => {
        window.evaluationInteractionEvents = [];
        for (const name of ['focusin', 'focusout', 'scroll']) window.addEventListener(name, event => {
            if (name !== 'scroll' && !event.target.closest?.('[data-eval-chart]')) return;
            const active = document.activeElement;
            window.evaluationInteractionEvents.push({event: name, time: Math.round(performance.now()), y: scrollY,
                activeKind: active?.closest?.('[data-eval-chart]')?.dataset.evalKind,
                activeRecord: active?.dataset?.evalRecord,
                tooltipHidden: document.querySelector('.eval-chart-tooltip')?.hidden});
            if (window.evaluationInteractionEvents.length > 60) window.evaluationInteractionEvents.shift();
        }, true);
    });
    const response = await page.goto(base + reportPath, {waitUntil: 'networkidle', timeout: 60000});
    console.log(`REPORT_HTTP ${response.status()} ${((Date.now() - started) / 1000).toFixed(1)}s ${reportPath}`);
    if (response.status() !== 200) fs.writeFileSync(path.join(output, 'failed-report.html'), await page.content());
    assert.equal(response.status(), 200, `Report failed: ${page.url()}`);
    assert.equal(new URL(page.url()).pathname, new URL(base + reportPath).pathname, 'Authentication redirected away from the report.');
    assert(response.headers()['content-security-policy'], 'Real HTTP security headers are missing.');
    return page;
}

async function authenticatedContext(browser, access) {
    const statePath = path.join(output, `${access}-private-session.json`);
    execFileSync(php, [helper, 'create', statePath, access], {stdio: 'pipe'});
    states.push(statePath);
    const {cookie} = JSON.parse(fs.readFileSync(statePath, 'utf8'));
    const context = await browser.newContext({viewport: {width: 1440, height: 1000}, hasTouch: true});
    await context.addCookies([{...cookie, url: base, httpOnly: true, sameSite: 'Lax'}]);
    await context.route('**/*', async route => {
        const request = route.request();
        if (!['GET', 'HEAD', 'OPTIONS'].includes(request.method())) {
            const isForm = (request.headers()['content-type'] || '').includes('application/x-www-form-urlencoded');
            const body = new URLSearchParams(isForm ? (request.postData() || '') : '');
            const payload = {};
            for (const key of new Set(body.keys())) {
                if (['_token', 'password'].includes(key)) continue;
                payload[key] = body.getAll(key);
            }
            const blocked = {url: request.url(), method: request.method(), payload};
            results.blockedMutations.push(blocked);
            await route.abort('blockedbyclient');
            mutationEvents.emit('aborted', blocked);
            return;
        }
        return route.continue();
    });
    return context;
}

async function checkRework(page) {
    const panel = page.locator('[data-evaluation-rework-panel]');
    await panel.waitFor({state: 'visible'});
    const forms = panel.locator('[data-report-rework-form]');
    const evaluatorForms = await forms.count();
    assert(evaluatorForms > 0, 'Admin has no evaluator rework forms on the scored report.');
    const search = panel.locator('[data-rework-search]');
    const groups = panel.locator('[data-rework-evaluator]');
    await search.fill('No matching evaluator browser fixture 482691');
    assert.equal(await panel.locator('[data-rework-evaluator]:visible').count(), 0, 'Evaluator search did not filter nonmatches.');
    assert(await panel.locator('[data-rework-no-matches]').isVisible(), 'No-match search has no feedback.');
    await search.fill('');
    assert.equal(await panel.locator('[data-rework-evaluator]:visible').count(), await groups.count(), 'Clearing search did not restore evaluators.');
    const eligible = [];
    for (const form of await forms.all()) {
        if (await form.locator('input[name="submission_ids[]"]:not(:disabled)').count()) eligible.push(form);
    }
    assert(eligible.length > 0, 'No submitted evaluation can be selected for the browser form check.');
    const form = eligible[0];
    const expand = async target => {
        const details = target.locator('xpath=ancestor::details[1]');
        if (!await details.evaluate(element => element.open)) await details.locator('summary').first().click();
    };
    await expand(form);
    const send = form.locator('[data-rework-send]');
    const choices = form.locator('input[name="submission_ids[]"]:not(:disabled)');
    const reason = form.locator('textarea[name="reason"]');
    assert(await send.isDisabled(), 'Rework send should start disabled without selected evaluations.');
    await choices.first().check();
    assert.equal(await form.evaluate(element => element.checkValidity()), false, 'An empty reason passed browser validation.');
    await reason.fill('short');
    assert.equal(await form.evaluate(element => element.checkValidity()), false, 'A short reason passed browser validation.');
    const mutationCount = results.blockedMutations.length;
    if (await send.isEnabled()) await send.click({noWaitAfter: true});
    assert.equal(results.blockedMutations.length, mutationCount, 'Invalid rework guidance attempted submission.');

    await form.locator('[data-rework-select-all]').click();
    assert.equal(await form.locator('input[name="submission_ids[]"]:checked').count(), Math.min(50, await choices.count()), 'Select all omitted eligible evaluations or exceeded the batch limit.');
    await form.locator('[data-rework-clear]').click();
    assert.equal(await form.locator('input[name="submission_ids[]"]:checked').count(), 0, 'Clear retained selected evaluations.');
    assert(await send.isDisabled(), 'Clearing evaluations left send enabled.');

    await choices.first().check();
    if (await choices.count() > 1) await choices.nth(1).check();
    const guidance = 'Browser-only check: clarify the evidence supporting the recorded criterion scores before resubmission.';
    await reason.fill(guidance);
    const selected = await form.locator('input[name="submission_ids[]"]:checked').evaluateAll(inputs => inputs.map(input => input.value));
    const evaluator = await form.locator('input[name="evaluator_id"]').inputValue();
    const override = form.locator('[data-rework-override] input');
    let overrideRequired = false;
    if (await override.count() && await override.isVisible()) {
        overrideRequired = true;
        assert(await send.isDisabled(), 'Rework requiring an administrator override could be sent without confirmation.');
        assert.equal(await form.evaluate(element => element.checkValidity()), false, 'Missing administrator override passed browser validation.');
        await override.check();
    }
    assert.equal(await form.evaluate(element => element.checkValidity()), true, 'Valid selected evaluations and guidance were rejected.');
    assert(await send.isEnabled(), 'Valid rework request remains disabled.');
    if (eligible.length > 1) {
        await expand(eligible[1]);
        assert.equal(await eligible[1].locator('input[name="submission_ids[]"]:checked').count(), 0, 'Selections leaked to a different evaluator.');
        assert.equal(await eligible[1].locator('textarea[name="reason"]').inputValue(), '', 'Guidance leaked to a different evaluator.');
        assert.deepEqual(await form.locator('input[name="submission_ids[]"]:checked').evaluateAll(inputs => inputs.map(input => input.value)), selected, 'Opening another evaluator lost the first draft selection.');
        assert.equal(await reason.inputValue(), guidance, 'Opening another evaluator lost its draft guidance.');
    }
    await expand(form);
    await form.scrollIntoViewIfNeeded();
    await form.screenshot({path: path.join(output, 'rework-desktop.png')});
    await panel.screenshot({path: path.join(output, 'rework-panel-expanded-desktop.png')});
    await panel.evaluate(element => window.scrollTo({left: 0, top: Math.max(0, element.getBoundingClientRect().top + window.scrollY - 95), behavior: 'instant'}));
    await page.waitForTimeout(150);
    await page.screenshot({path: path.join(output, 'rework-panel-viewport-desktop.png')});
    await page.setViewportSize({width: 390, height: 844});
    await form.scrollIntoViewIfNeeded();
    const box = await form.boundingBox();
    assert(box.x >= 0 && box.x + box.width <= 391, 'Rework form exceeds the mobile viewport.');
    await form.screenshot({path: path.join(output, 'rework-mobile.png')});
    const action = await form.getAttribute('action');
    assert.equal(new URL(action, base).origin, new URL(base).origin, 'Rework form posts outside this portal.');
    const requestPromise = new Promise((resolve, reject) => {
        const listener = request => {
            if (request.method === 'POST' && request.url === new URL(action, base).href) {
                clearTimeout(timer); mutationEvents.off('aborted', listener); resolve(request);
            }
        };
        const timer = setTimeout(() => { mutationEvents.off('aborted', listener); reject(new Error('Expected rework request was not intercepted and aborted.')); }, 10000);
        mutationEvents.on('aborted', listener);
    });
    await send.click({noWaitAfter: true}).catch(error => { if (!String(error).includes('ERR_FAILED')) throw error; });
    const posted = await requestPromise;
    assert(posted, 'Expected rework submission was not intercepted.');
    assert.deepEqual(posted.payload.evaluator_id, [evaluator], 'Submitted evaluator differs from the selected form.');
    assert.deepEqual(posted.payload['submission_ids[]']?.sort(), selected.sort(), 'Submitted evaluation IDs differ from the selected checkboxes.');
    assert.deepEqual(posted.payload.reason, [guidance], 'Rework guidance changed in the request payload.');
    if (overrideRequired) assert.deepEqual(posted.payload.override_proposal_round_lock, ['1'], 'Confirmed administrator override is missing from the request payload.');
    results.checks.push({rework: 'passed; actual request aborted', evaluatorForms, selectedCount: selected.length, formIsolation: eligible.length > 1, overrideRequired});
}

async function checkCharts(page) {
    const figures = page.locator('[data-eval-chart]');
    assert(await figures.count() > 0, 'Interactive report charts are missing.');
    const tooltip = page.locator('.eval-chart-tooltip');
    await tooltip.waitFor({state: 'attached'});
    const getRecords = async figure => JSON.parse(await figure.getAttribute('data-eval-records'));
    const checkTooltip = async (record, pie = false) => {
        await tooltip.waitFor({state: 'visible', timeout: 5000});
        await page.waitForFunction(expected => {
            const tooltip = document.querySelector('.eval-chart-tooltip');
            return tooltip && !tooltip.hidden && tooltip.querySelector('.eval-chart-tooltip-heading')?.textContent === expected.heading
                && tooltip.querySelector('.eval-chart-tooltip-values dd')?.textContent === expected.value
                && (expected.pie || tooltip.querySelector('.eval-chart-tooltip-series')?.textContent.includes(expected.evaluator));
        }, {heading: pie ? record.name : record.applicant, value: record.display_value, evaluator: record.evaluator, pie}, {timeout: 5000});
        const text = await tooltip.innerText();
        assert(text.includes(record.display_value), `Tooltip score/count differs from source data: ${text}`);
        if (pie) assert(text.includes(record.display_percentage), 'Pie share differs from the recorded data.');
        else assert(text.includes(record.evaluator), 'Tooltip identifies a different evaluator.');
    };
    const bar = page.locator('[data-eval-chart][data-eval-kind="bar"]').first();
    const hasBars = await bar.count() > 0;
    let finiteRecord, finiteTarget;
    if (hasBars) {
        const records = await getRecords(bar);
        const firstIndex = records.findIndex(record => Number.isFinite(record.value) && record.value !== 0);
        assert(firstIndex >= 0, 'Scored report has no recorded numeric tooltip point.');
        finiteRecord = records[firstIndex];
        finiteTarget = bar.locator(`.eval-chart-target[data-eval-record="${firstIndex}"]`).first();
        await finiteTarget.scrollIntoViewIfNeeded();
        await page.waitForTimeout(200);
        await finiteTarget.hover();
        await checkTooltip(finiteRecord);
        assert.equal(await finiteTarget.getAttribute('aria-describedby'), await tooltip.getAttribute('id'), 'Tooltip is not linked to its focused chart observation.');
        await page.screenshot({path: path.join(output, 'chart-hover-desktop.png')});
        await page.mouse.move(5, 5);
        await tooltip.waitFor({state: 'hidden'});
        await finiteTarget.focus();
        await checkTooltip(finiteRecord);
        const focusedBefore = await page.evaluate(() => document.activeElement?.getAttribute('data-eval-record'));
        await page.keyboard.press('ArrowRight');
        const focusedAfter = await page.evaluate(() => document.activeElement?.getAttribute('data-eval-record'));
        assert.notEqual(focusedAfter, focusedBefore, 'Arrow navigation did not move to another observation.');
        await checkTooltip(records[Number(focusedAfter)]);
        await page.keyboard.press('Enter');
        await page.mouse.move(6, 6);
        assert(await tooltip.isVisible(), 'Pinned chart details disappeared when the pointer moved away.');
        await page.keyboard.press('Escape');
        await tooltip.waitFor({state: 'hidden'});
        assert.equal(await bar.locator('.eval-chart-target[tabindex="0"]').count(), 1, 'Each chart must have one keyboard entry point.');
        const missingIndex = records.findIndex(record => record.value === null);
        if (missingIndex >= 0) {
            await bar.locator(`.eval-chart-target[data-eval-record="${missingIndex}"]`).first().focus();
            await checkTooltip(records[missingIndex]);
            assert.match(records[missingIndex].display_value, /not recorded/i, 'Missing observation was represented as zero.');
            await page.keyboard.press('Escape');
        }
        const data = bar.locator('.eval-chart-data');
        await data.locator('summary').click();
        assert.equal(await data.locator('tbody tr').count(), records.length, 'Accessible chart data omits observations.');
        const rowText = await data.locator('tbody tr').nth(firstIndex).innerText();
        for (const value of [finiteRecord.applicant, finiteRecord.evaluator, finiteRecord.display_value]) assert(rowText.includes(value), 'Chart data table differs from the tooltip observation.');
        await data.locator('summary').click();
        results.checks.push({barHoverFocusKeyboard: true, missingDistinctFromZero: missingIndex >= 0, dataTableObservations: records.length});
    }
    const line = page.locator('[data-eval-chart][data-eval-kind="line"]').first();
    if (await line.count()) {
        const records = await getRecords(line);
        const index = records.findIndex(record => Number.isFinite(record.value));
        await line.locator(`.eval-chart-target[data-eval-record="${index}"]`).first().focus();
        await checkTooltip(records[index]);
        await page.keyboard.press('Escape');
        await tooltip.waitFor({state: 'hidden'});
        results.checks.push({lineDetails: true});
    }
    const pie = page.locator('[data-eval-chart][data-eval-kind="pie"]').first();
    assert(await pie.count(), 'Report coverage pie is missing.');
    const pieRecords = await getRecords(pie);
    const sum = pieRecords.reduce((total, record) => total + (Number.isFinite(record.value) ? record.value : 0), 0);
    for (let index = 0; index < pieRecords.length; index++) {
        const record = pieRecords[index];
        const target = pie.locator(`.eval-chart-target[data-eval-record="${index}"]`).filter({has: page.locator('rect')}).first();
        await target.focus();
        await checkTooltip(record, true);
        if (record.value !== null && sum > 0) assert.equal(record.display_percentage, `${(record.value / sum * 100).toFixed(1)}%`, 'Pie percentage is inconsistent with the recorded counts.');
        await page.keyboard.press('Escape');
    }
    results.checks.push({pieCountsShares: true, categories: pieRecords.length, zeroCountPresent: pieRecords.some(record => record.value === 0)});
    if (hasBars) {
        await page.setViewportSize({width: 390, height: 844});
        await finiteTarget.scrollIntoViewIfNeeded();
        await page.waitForFunction(() => {
            const lastScroll = window.evaluationInteractionEvents?.filter(event => event.event === 'scroll').at(-1);
            return !lastScroll || performance.now() - lastScroll.time > 180;
        }, null, {timeout: 5000});
        await finiteTarget.tap();
        await checkTooltip(finiteRecord);
        const bounds = await tooltip.boundingBox();
        assert(bounds.x >= 0 && bounds.x + bounds.width <= 391 && bounds.y >= 0 && bounds.y + bounds.height <= 845, 'Tooltip extends outside the mobile viewport.');
        await page.screenshot({path: path.join(output, 'chart-tap-mobile.png')});
        await page.touchscreen.tap(2, 2);
        await tooltip.waitFor({state: 'hidden'});
        await page.setViewportSize({width: 1440, height: 1000});
        results.checks.push({mobileTapDetailsDismiss: true});
    }
}

(async () => {
    let browser;
    try {
        const access = JSON.parse(execFileSync(php, [helper, 'inspect'], {encoding: 'utf8'}));
        assert(access.admin, 'No eligible existing administrative account.');
        results.viewerAvailable = access.viewer;
        browser = await chromium.launch({channel: process.env.PLAYWRIGHT_CHANNEL || 'msedge', headless: true});
        const admin = await authenticatedContext(browser, 'admin');
        const scored = await openReport(admin, scoredPath);
        if (!process.argv.includes('--rework-only')) await checkCharts(scored);
        if (!process.argv.includes('--charts-only')) {
            if (await scored.locator('[data-report-rework-form]').count()) await checkRework(scored);
            else {
                const panel = scored.locator('[data-evaluation-rework-panel]');
                const blockedGroup = panel.locator('[data-rework-evaluator]').first();
                if (await blockedGroup.count()) await blockedGroup.locator('summary').first().click();
                assert.match(await panel.innerText(), /unavailable|locked|no submitted/i, 'No rework forms and no explanation is provided.');
                results.checks.push({scoredReportRework: 'unavailable due to existing workflow restrictions'});
                const reports = JSON.parse(execFileSync(php, [helper, 'reports'], {encoding: 'utf8'}));
                const alternative = process.env.EVALUATION_BROWSER_REWORK_PATH || reports.find(report => report.available > 0)?.path;
                assert(alternative, 'No existing eligible rework report; no business records were changed to manufacture one.');
                results.reworkPath = alternative;
                const reworkPage = await openReport(admin, alternative);
                await checkRework(reworkPage);
            }
        }
        const pending = await openReport(admin, pendingPath);
        if (!process.argv.includes('--rework-only')) await checkCharts(pending);
        assert.equal(await pending.locator('[data-report-rework-form] input[name="submission_ids[]"]:not(:disabled)').count(), 0, 'Pending report offers unsubmitted records for rework.');
        await pending.screenshot({path: path.join(output, 'pending-report.png'), fullPage: false});
        results.checks.push({pendingReport: 'no selectable unsubmitted records'});
        if (access.viewer) {
            const viewer = await authenticatedContext(browser, 'viewer');
            const readOnly = await openReport(viewer, scoredPath);
            assert.equal(await readOnly.locator('[data-report-rework-form]').count(), 0, 'Report-only user sees rework submission forms.');
            results.checks.push({readOnlyAccount: 'rework controls absent'});
        } else results.checks.push({readOnlyAccount: 'unavailable among existing active/current accounts; backend fixture coverage required'});
        const unexpectedErrors = results.pageErrors.filter(error => !error.includes('daterangepicker is not a function') && !error.includes('circleProgress is not a function'));
        assert.deepEqual(unexpectedErrors, [], 'Unexpected browser JavaScript errors.');
        assert.deepEqual(results.cspViolations, [], 'Content Security Policy violations.');
        results.status = 'passed';
        console.log(JSON.stringify(results, null, 2));
    } catch (error) {
        results.status = 'failed'; results.error = String(error.stack || error);
        const lastPage = browser?.contexts().flatMap(context => context.pages()).at(-1);
        if (lastPage) {
            results.browserState = await lastPage.evaluate(() => ({
                events: window.evaluationInteractionEvents,
                scrollBehavior: getComputedStyle(document.documentElement).scrollBehavior,
                tooltipHidden: document.querySelector('.eval-chart-tooltip')?.hidden,
                tooltipText: document.querySelector('.eval-chart-tooltip')?.textContent,
                activeRecord: document.activeElement?.getAttribute('data-eval-record'),
                activeKind: document.activeElement?.closest('[data-eval-chart]')?.getAttribute('data-eval-kind'),
            })).catch(() => null);
            await lastPage.screenshot({path: path.join(output, 'failure-viewport.png')}).catch(() => {});
        }
        throw error;
    } finally {
        await browser?.close();
        for (const state of states) execFileSync(php, [helper, 'destroy', state], {stdio: 'pipe'});
        fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify(results, null, 2));
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
