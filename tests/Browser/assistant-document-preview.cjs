/*
 * Real HTTP preview regression, including the application's actual CSP headers.
 * Prerequisites: local running application, Playwright + Edge, Python pymupdf/Pillow/python-docx.
 * Example (PowerShell):
 *   $env:NODE_PATH = "$env:TEMP/aa-browser-check/node_modules"
 *   node tests/Browser/assistant-document-preview.cjs
 * No document submission occurs. A short-lived assistant session is removed in finally.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require('playwright');

const base = process.env.ASSISTANT_PREVIEW_BASE_URL || 'http://127.0.0.1:8000';
assert(['127.0.0.1', 'localhost', '[::1]'].includes(new URL(base).hostname), 'Only local application URLs are allowed.');
const target = process.env.ASSISTANT_PREVIEW_PATH || '/administrative-assistant/purchase-orders/019f7636-f37b-7171-affe-4a1c8f5a9fd4/items/019f762d-73ef-7008-b617-c4b7691982c3?year=2026&month=3';
const baseline = process.argv.includes('--baseline');
const output = fs.mkdtempSync(path.join(os.tmpdir(), 'assistant-preview-browser-'));
const state = path.join(output, 'private-session.json');
const helper = path.join(__dirname, 'assistant-preview-session.php');
const php = process.env.PHP_BINARY || 'php';
const python = process.env.PYTHON_BINARY || 'python';

(async () => {
    let browser;
    let context;
    const violations = [], pageErrors = [], requests = [], mutationAttempts = [];
    try {
        execFileSync(php, [helper, 'create', state], {stdio: 'pipe'});
        execFileSync(python, [path.join(__dirname, 'assistant-preview-fixtures.py'), output], {stdio: 'pipe'});
        fs.copyFileSync(path.join(__dirname, '../../tools/document-preview/tests/fixtures/test15.doc'), path.join(output, 'real-legacy-preview.doc'));
        browser = await chromium.launch({channel: 'msedge', headless: true});
        context = await browser.newContext({viewport: {width: 1440, height: 1000}});
        const {cookie} = JSON.parse(fs.readFileSync(state, 'utf8'));
        await context.addCookies([{...cookie, url: base, httpOnly: true, sameSite: 'Lax'}]);
        await context.route('**/*', async route => {
            const request = route.request();
            requests.push({url: request.url(), method: request.method(), kind: request.resourceType()});
            if (!['GET', 'HEAD', 'OPTIONS'].includes(request.method())) {
                mutationAttempts.push({url: request.url(), method: request.method()});
                return route.abort('blockedbyclient');
            }
            if (request.url().startsWith('https://preview-leak.invalid/')) return route.abort('blockedbyclient');
            return route.continue();
        });
        const page = await context.newPage();
        page.on('pageerror', error => pageErrors.push(error.message));
        await page.exposeFunction('recordPreviewCspViolation', details => violations.push(details));
        await page.addInitScript(() => {
            document.addEventListener('securitypolicyviolation', event => {
                window.recordPreviewCspViolation({directive: event.violatedDirective, blocked: event.blockedURI});
            });
        });
        const response = await page.goto(base + target, {waitUntil: 'networkidle', timeout: 60000});
        assert.equal(response.status(), 200, `Target failed: ${page.url()}`);
        assert.equal(new URL(page.url()).pathname, new URL(base + target).pathname, 'Authentication redirected away from the target page.');
        const csp = response.headers()['content-security-policy'];
        assert(csp?.includes("default-src 'self'"), 'Real HTTP security headers were not present.');
        assert(await page.locator('#supportingDocuments').count(), 'Expected document selection input missing.');
        const fixtureNames = ['multipage-preview.pdf', 'formatted-preview.docx', 'local-image.jpg', 'malformed-preview.pdf', 'malformed-preview.docx', 'legacy-preview.doc', 'remote-image-preview.docx', 'protected-preview.pdf', 'real-legacy-preview.doc'];
        const requestMarker = requests.length;
        await page.locator('#supportingDocuments').setInputFiles(fixtureNames.map(name => path.join(output, name)));
        assert.equal(await page.locator('.selected-file').count(), fixtureNames.length, 'Selected files were not retained.');
        const open = async name => page.getByRole('button', {name: `Preview ${name}`, exact: true}).click();
        const close = async () => { await page.keyboard.press('Escape'); await page.locator('[data-file-preview-modal]').waitFor({state: 'hidden'}); };
        if (baseline) {
            await open('multipage-preview.pdf');
            await page.waitForTimeout(600);
            const pdfFrame = await page.locator('.file-preview-frame').count();
            await page.screenshot({path: path.join(output, 'baseline-pdf.png')});
            await close();
            await open('formatted-preview.docx');
            const wordMessage = await page.locator('[data-file-preview-body]').innerText();
            await close();
            await open('local-image.jpg');
            await page.waitForTimeout(300);
            const imageWidth = await page.locator('.file-preview-image').evaluate(image => image.naturalWidth);
            await close();
            const results = {mode: 'baseline', actualHttp: true, csp, pdfFrame, imageWidth, wordMessage, violations, pageErrors, mutationAttempts};
            fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify(results, null, 2));
            console.log(JSON.stringify({...results, output}, null, 2));
            return;
        }
        const preview = page.locator('[data-local-document-preview]');
        const ready = async () => {
            await preview.waitFor({state: 'visible', timeout: 15000});
            await page.waitForFunction(() => ['ready', 'error'].includes(document.querySelector('[data-local-document-preview]')?.dataset.previewState), null, {timeout: 30000});
            assert.equal(await preview.getAttribute('data-preview-state'), 'ready', await page.locator('[data-file-preview-body]').innerText());
        };
        const expectError = async name => {
            await open(name);
            await page.waitForFunction(() => document.querySelector('[data-local-document-preview]')?.dataset.previewState === 'error', null, {timeout: 15000});
            assert(await page.locator('.doc-preview-error').innerText(), `No useful error for ${name}.`);
            await close();
        };
        const canvasSignature = () => page.locator('.doc-preview-canvas').evaluate(canvas => {
            const context = canvas.getContext('2d');
            const pixels = context.getImageData(0, 0, canvas.width, canvas.height).data;
            let nonWhite = 0;
            for (let index = 0; index < pixels.length; index += 4) {
                if (pixels[index + 3] && (pixels[index] < 245 || pixels[index + 1] < 245 || pixels[index + 2] < 245)) nonWhite++;
            }
            return {width: canvas.getBoundingClientRect().width, nonWhite, image: canvas.toDataURL()};
        });
        const expectWordFits = async () => {
            const bounds = await page.frameLocator('[data-word-preview-frame]').locator('section.docx').evaluate(section => ({
                left: section.getBoundingClientRect().left, right: section.getBoundingClientRect().right,
                viewport: innerWidth, scrollWidth: document.documentElement.scrollWidth,
            }));
            assert(bounds.left >= 0 && bounds.right <= bounds.viewport + 1 && bounds.scrollWidth <= bounds.viewport + 1,
                `Word page does not fit the preview width at 100%: ${JSON.stringify(bounds)}`);
        };

        await open('multipage-preview.pdf');
        await ready();
        assert.equal(await page.locator('.doc-preview-page-count').innerText(), 'of 3');
        const firstPage = await canvasSignature();
        assert(firstPage.nonWhite > 100, 'PDF canvas is blank.');
        assert(await page.getByRole('button', {name: 'Previous page', exact: true}).isDisabled());
        await page.getByRole('button', {name: 'Next page', exact: true}).click();
        await ready();
        assert.equal(await page.getByRole('spinbutton', {name: 'Page number'}).inputValue(), '2');
        assert.notEqual((await canvasSignature()).image, firstPage.image, 'PDF content did not change on page 2.');
        await page.getByRole('button', {name: 'Zoom in', exact: true}).click();
        await ready();
        assert.equal(await page.getByRole('button', {name: 'Fit to width', exact: true}).innerText(), '125%');
        assert((await canvasSignature()).width > firstPage.width * 1.2, 'PDF zoom did not increase rendered page size.');
        await page.getByRole('button', {name: 'Fit to width', exact: true}).click();
        await ready();
        await page.getByRole('spinbutton', {name: 'Page number'}).fill('999');
        await page.getByRole('spinbutton', {name: 'Page number'}).press('Tab');
        await ready();
        assert.equal(await page.getByRole('spinbutton', {name: 'Page number'}).inputValue(), '3', 'Page input was not bounded to the document.');
        assert(await page.getByRole('button', {name: 'Next page', exact: true}).isDisabled());
        await page.getByRole('spinbutton', {name: 'Page number'}).fill('1.5');
        await page.getByRole('spinbutton', {name: 'Page number'}).press('Tab');
        await ready();
        assert.equal(await page.getByRole('spinbutton', {name: 'Page number'}).inputValue(), '1', 'Decimal page input was not normalized to a valid page.');
        await page.screenshot({path: path.join(output, 'pdf-desktop.png')});
        await close();
        assert.equal(await page.evaluate(() => document.activeElement?.getAttribute('aria-label')), 'Preview multipage-preview.pdf', 'Closing did not restore focus to the selected document.');

        await open('formatted-preview.docx');
        await ready();
        const word = page.frameLocator('[data-word-preview-frame]');
        assert((await word.locator('body').innerText()).includes('Local Word preview'), 'Word content is missing.');
        assert.equal(await word.locator('table tr').count(), 3, 'Word table rows were lost.');
        assert(await word.getByText('bold emphasis', {exact: true}).evaluate(node => Number(getComputedStyle(node).fontWeight) >= 600), 'Word bold text was lost.');
        assert.equal(await word.getByText('italic emphasis', {exact: true}).evaluate(node => getComputedStyle(node).fontStyle), 'italic', 'Word italic text was lost.');
        await word.locator('img').waitFor({state: 'visible'});
        assert(await word.locator('img').evaluate(image => image.naturalWidth > 0), 'Embedded Word image failed to render.');
        await page.getByRole('button', {name: 'Zoom in', exact: true}).click();
        assert.equal(await page.getByRole('button', {name: 'Fit to width', exact: true}).innerText(), '125%');
        await page.getByRole('button', {name: 'Fit to width', exact: true}).click();
        if (!process.argv.includes('--diagnose-word')) await expectWordFits();
        await page.screenshot({path: path.join(output, 'word-desktop.png')});
        if (process.argv.includes('--diagnose-word')) {
            console.log(JSON.stringify(await word.locator('section.docx').evaluate(section => ({
                page: section.getBoundingClientRect().toJSON(), viewport: innerWidth,
                wrapper: section.parentElement.getBoundingClientRect().toJSON(),
                padding: getComputedStyle(section.parentElement).padding,
                zoom: getComputedStyle(section.parentElement).zoom,
                bodyScroll: document.body.scrollWidth,
            })), null, 2));
            return;
        }
        await word.getByText('Local Word preview', {exact: true}).click();
        await close();

        await open('protected-preview.pdf');
        await page.getByLabel('PDF password', {exact: true}).waitFor({state: 'visible'});
        await page.getByLabel('PDF password', {exact: true}).fill('WrongFixturePassword');
        await page.getByRole('button', {name: 'Open preview', exact: true}).click();
        await page.getByText('That password did not open the PDF. Try again.', {exact: true}).waitFor({state: 'visible'});
        await page.getByLabel('PDF password', {exact: true}).fill('PreviewFixtureOnly123');
        await page.getByRole('button', {name: 'Open preview', exact: true}).click();
        await ready();
        assert((await canvasSignature()).nonWhite > 100, 'Password-protected PDF did not render after unlocking.');
        await close();

        await open('local-image.jpg');
        await page.locator('.file-preview-image').waitFor({state: 'visible'});
        assert(await page.locator('.file-preview-image').evaluate(image => image.complete && image.naturalWidth === 640), 'Local JPEG preview failed.');
        await close();
        await expectError('malformed-preview.pdf');
        await expectError('malformed-preview.docx');
        await expectError('legacy-preview.doc');

        await open('real-legacy-preview.doc');
        await ready();
        const legacyText = await page.locator('[data-legacy-word-preview]').innerText();
        assert(legacyText.length > 30 && !legacyText.includes('\u0000'), 'Real legacy Word did not render readable text.');
        assert.match(legacyText, /Headers[\s\S]+header/i, 'Legacy Word headers were lost.');
        assert.match(legacyText, /Footers[\s\S]+footer/i, 'Legacy Word footers were lost.');
        await page.screenshot({path: path.join(output, 'legacy-word-desktop.png')});
        await close();

        await open('remote-image-preview.docx');
        await ready();
        assert((await page.frameLocator('[data-word-preview-frame]').locator('body').innerText()).includes('Local Word preview'), 'A remote image prevented safe Word text rendering.');
        await close();

        // Fire these synchronously to exercise a close while an async import/read is pending.
        await page.evaluate(() => {
            document.querySelector('[aria-label="Preview multipage-preview.pdf"]').click();
            document.dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape', bubbles: true}));
            document.querySelector('[aria-label="Preview formatted-preview.docx"]').click();
        });
        await ready();
        await page.waitForTimeout(250);
        assert.equal(await page.locator('[data-file-preview-title]').innerText(), 'formatted-preview.docx', 'An earlier preview replaced the active document.');
        assert.equal(await page.locator('[data-word-preview-frame]').count(), 1);
        assert.equal(await page.locator('.doc-preview-canvas').count(), 0, 'Stale PDF renderer survived the close/reopen race.');
        await close();

        await page.setViewportSize({width: 390, height: 844});
        for (const [filename, screenshot] of [['multipage-preview.pdf', 'pdf-mobile.png'], ['formatted-preview.docx', 'word-mobile.png']]) {
            await open(filename);
            await ready();
            const box = await page.locator('[data-file-preview-dialog]').boundingBox();
            assert(box.x >= 0 && box.x + box.width <= 391, `${filename} dialog exceeds the mobile viewport.`);
            assert(box.y >= 0 && box.y + box.height <= 845, `${filename} dialog exceeds the mobile viewport vertically.`);
            assert(await page.getByRole('button', {name: 'Zoom in', exact: true}).isVisible());
            assert(await page.getByRole('button', {name: 'Close document preview', exact: true}).isVisible());
            if (filename.endsWith('.docx')) await expectWordFits();
            await page.screenshot({path: path.join(output, screenshot)});
            await close();
        }
        assert.equal(await page.locator('.selected-file').count(), fixtureNames.length, 'Previewing removed selected documents.');
        const previewRequests = requests.slice(requestMarker);
        const externalPreviewRequests = previewRequests.filter(request => /^https?:/.test(request.url) && new URL(request.url).origin !== new URL(base).origin);
        const documentMutationAttempts = mutationAttempts.filter(request => !new URL(request.url).pathname.startsWith('/website-visit-tracker/'));
        assert.equal(externalPreviewRequests.length, 0, `Preview made external requests: ${JSON.stringify(externalPreviewRequests)}`);
        assert.equal(documentMutationAttempts.length, 0, `Preview tried to upload or alter data: ${JSON.stringify(documentMutationAttempts)}`);
        assert.equal(pageErrors.length, 0, `Uncaught browser errors: ${JSON.stringify(pageErrors)}`);
        assert.equal(violations.length, 0, `CSP blocked preview content: ${JSON.stringify(violations)}`);
        const results = {mode: 'regression', actualHttp: true, csp, files: fixtureNames.length, pdfPages: 3, pdfPagination: true, pdfZoom: true, protectedPdfUnlock: true, wordFormattingTableImage: true, wordFitToWidth: true, wordIframeKeyboardClose: true, realLegacyWordTextHeadersFooters: true, malformedRecovery: true, closeReopenRace: true, mobile: true, externalPreviewRequests, documentMutationAttempts, blockedTelemetryRequests: mutationAttempts.length, pageErrors, violations};
        fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify(results, null, 2));
        console.log(JSON.stringify({...results, output}, null, 2));
    } finally {
        await context?.close();
        await browser?.close();
        execFileSync(php, [helper, 'destroy', state], {stdio: 'pipe'});
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
