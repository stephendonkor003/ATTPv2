const assetBase = new URL('../vendor/document-preview/', import.meta.url);
let runtimePromise;
const loadReaders = () => runtimePromise ??= import(new URL('runtime.js', assetBase).href);
class PreviewError extends Error {}

const element = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
};

// The session is returned immediately, so closing/switching files can cancel
// asynchronous loading before a renderer has finished opening the document.
export function createPreview(container, file) {
    let alive = true;
    let loadingTask;
    let renderTask;
    let pdf;
    let observer;
    let resize;
    const abort = new AbortController();
    const root = element('div', 'doc-preview');
    root.dataset.localDocumentPreview = '';
    const toolbar = element('div', 'doc-preview-toolbar');
    toolbar.setAttribute('role', 'toolbar');
    toolbar.setAttribute('aria-label', 'Document preview controls');
    const stage = element('div', 'doc-preview-stage');
    const status = element('div', 'doc-preview-status', 'Opening your document...');
    status.setAttribute('role', 'status');
    root.append(toolbar, stage, status);
    container.replaceChildren(root);

    function state(value, message) {
        if (!alive) return;
        root.dataset.previewState = value;
        root.setAttribute('aria-busy', String(value === 'loading'));
        status.textContent = message;
    }
    function fail(error) {
        if (!alive || error?.name === 'AbortError' || error?.name === 'RenderingCancelledException') return;
        toolbar.hidden = true;
        const message = error instanceof PreviewError || error?.name === 'LegacyWordPreviewError'
            ? error.message : 'This document could not be opened. It may be incomplete or damaged. Please check the file and try again.';
        stage.replaceChildren(element('div', 'doc-preview-error', message));
        state('error', 'Preview unavailable. Your selected file has not been changed; you can still upload it.');
    }
    function button(label, text, action) {
        const node = element('button', 'doc-preview-button', text);
        node.type = 'button';
        node.setAttribute('aria-label', label);
        node.title = label;
        node.addEventListener('click', action);
        toolbar.append(node);
        return node;
    }
    function navigation(total, onPage, onZoom) {
        let current = 1;
        let zoom = 1;
        let busy = false;
        const previous = button('Previous page', 'Previous', () => onPage(current - 1));
        const page = element('input', 'doc-preview-page');
        page.type = 'number'; page.min = '1'; page.max = String(total); page.value = '1';
        page.dataset.previewPage = '';
        page.setAttribute('aria-label', 'Page number');
        page.addEventListener('change', () => onPage(Math.max(1, Math.min(total, Math.trunc(Number(page.value)) || 1))));
        toolbar.append(page, element('span', 'doc-preview-page-count', `of ${total}`));
        const next = button('Next page', 'Next', () => onPage(current + 1));
        toolbar.append(element('span', 'doc-preview-toolbar-space'));
        const out = button('Zoom out', '-', () => onZoom(Math.max(.5, zoom - .25)));
        const fit = button('Fit to width', '100%', () => onZoom(1));
        const into = button('Zoom in', '+', () => onZoom(Math.min(2.5, zoom + .25)));
        return (pageNumber, zoomValue, isBusy = false) => {
            current = pageNumber; zoom = zoomValue; busy = isBusy;
            page.value = String(current); page.disabled = busy;
            previous.disabled = busy || current <= 1; next.disabled = busy || current >= total;
            out.disabled = busy || zoom <= .5; into.disabled = busy || zoom >= 2.5; fit.disabled = busy;
            fit.textContent = `${Math.round(zoom * 100)}%`;
        };
    }
    async function bytes() {
        if (!file.url) return file.arrayBuffer();
        const url = new URL(file.url, location.href);
        if (url.origin !== location.origin) throw new PreviewError('This saved document must be opened from this portal.');
        const response = await fetch(url, { credentials: 'same-origin', signal: abort.signal });
        if (!response.ok || response.redirected) throw new PreviewError('This saved document is unavailable. Refresh the page and sign in again if needed.');
        return response.arrayBuffer();
    }
    function requestPassword(updatePassword, reason, reasons) {
        if (!alive) return;
        const form = element('form', 'doc-preview-password');
        const label = element('label', '', 'Enter the password to preview this PDF');
        const input = element('input', 'form-control'); input.type = 'password'; input.autocomplete = 'off';
        input.setAttribute('aria-label', 'PDF password'); input.required = true;
        const submit = element('button', 'btn btn-aa-primary', 'Open preview'); submit.type = 'submit';
        label.append(input); form.append(label, submit);
        form.addEventListener('submit', event => {
            event.preventDefault();
            const password = input.value; input.value = '';
            state('loading', 'Opening your PDF...'); updatePassword(password);
        });
        stage.replaceChildren(form);
        state('password', reason === reasons.INCORRECT_PASSWORD ? 'That password did not open the PDF. Try again.' : 'The password is used only to open the preview on this device.');
        input.focus();
    }
    async function showPdf(data, readers) {
        const lib = readers.pdfjs;
        lib.GlobalWorkerOptions.workerSrc = new URL('pdf.worker.min.js', assetBase).href;
        loadingTask = lib.getDocument({ data: new Uint8Array(data),
            cMapUrl: new URL('cmaps/', assetBase).href, cMapPacked: true,
            standardFontDataUrl: new URL('standard_fonts/', assetBase).href,
            wasmUrl: new URL('wasm/', assetBase).href, iccUrl: new URL('iccs/', assetBase).href, isEvalSupported: false });
        loadingTask.onPassword = (callback, reason) => requestPassword(callback, reason, lib.PasswordResponses);
        try { pdf = await loadingTask.promise; }
        catch (error) {
            if (!alive) return;
            throw new PreviewError('This PDF could not be opened. It may be incomplete or damaged. Select a valid PDF and try again.');
        }
        if (!alive) return;
        let pageNumber = 1, zoom = 1, busy = false;
        const sheet = element('div', 'doc-preview-paper');
        const canvas = element('canvas', 'doc-preview-canvas');
        canvas.setAttribute('role', 'img'); sheet.append(canvas); stage.replaceChildren(sheet);
        const sync = navigation(pdf.numPages, value => { if (!busy) { pageNumber = value; draw(); } }, value => { if (!busy) { zoom = value; draw(); } });
        async function draw() {
            if (!alive || busy) return;
            busy = true; sync(pageNumber, zoom, true); state('loading', `Rendering page ${pageNumber} of ${pdf.numPages}...`);
            try {
                const page = await pdf.getPage(pageNumber);
                if (!alive) return;
                const base = page.getViewport({ scale: 1 });
                const scale = Math.max(.05, (stage.clientWidth - 36) / base.width) * zoom;
                const viewport = page.getViewport({ scale });
                const density = Math.min(window.devicePixelRatio || 1, 2, Math.sqrt(12000000 / (viewport.width * viewport.height)));
                canvas.width = Math.max(1, Math.floor(viewport.width * density));
                canvas.height = Math.max(1, Math.floor(viewport.height * density));
                canvas.style.width = `${viewport.width}px`; canvas.style.height = `${viewport.height}px`;
                canvas.setAttribute('aria-label', `${file.name}, page ${pageNumber} of ${pdf.numPages}`);
                renderTask = page.render({ canvasContext: canvas.getContext('2d'), viewport, transform: density === 1 ? null : [density, 0, 0, density, 0, 0] });
                await renderTask.promise;
                if (!alive) return;
                stage.scrollTop = 0;
                state('ready', `Page ${pageNumber} of ${pdf.numPages}. Use the controls to change pages or zoom.`);
            } catch (error) { fail(error); }
            finally { busy = false; if (alive) sync(pageNumber, zoom); }
        }
        let resizeTimer;
        resize = () => { clearTimeout(resizeTimer); resizeTimer = setTimeout(() => draw(), 120); };
        window.addEventListener('resize', resize);
        await draw();
    }
    async function showDocx(data, readers) {
        checkWordArchive(data);
        const archive = await readers.JSZip.loadAsync(data);
        let externalLinks = 0;
        for (const entry of Object.values(archive.files)) {
            if (entry.dir || !entry.name.endsWith('.rels')) continue;
            const xml = new DOMParser().parseFromString(await entry.async('string'), 'application/xml');
            let changed = false;
            for (const relation of [...xml.getElementsByTagNameNS('*', 'Relationship')]) {
                if (relation.getAttribute('TargetMode')?.toLowerCase() === 'external') {
                    relation.remove(); changed = true; externalLinks++;
                }
            }
            if (changed) archive.file(entry.name, new XMLSerializer().serializeToString(xml));
        }
        if (externalLinks) data = await archive.generateAsync({ type: 'arraybuffer', compression: 'DEFLATE' });
        if (!alive) return;
        const frame = element('iframe', 'doc-preview-word-frame');
        frame.dataset.wordPreviewFrame = '';
        frame.title = `Word document preview: ${file.name}`;
        // A same-origin, script-disabled document isolates Word styles from the
        // portal. Its policy also prevents external document relationships from
        // contacting remote servers. Embedded resources use data URLs.
        frame.setAttribute('sandbox', 'allow-same-origin');
        const ready = new Promise(resolve => frame.addEventListener('load', resolve, { once: true }));
        frame.srcdoc = '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data:; style-src \'unsafe-inline\'; font-src data:; base-uri \'none\'; form-action \'none\'"><style>html,body{margin:0;background:#e8eef4}body{overflow:auto}.docx-wrapper{padding:18px!important;box-sizing:border-box}.docx-wrapper>section{margin-bottom:18px!important}</style></head><body></body></html>';
        stage.replaceChildren(frame); await ready;
        if (!alive) return;
        const target = frame.contentDocument;
        // The renderer clears its containers. Keep the document policy and
        // viewport styles outside them so every page fits the preview frame.
        const styles = target.createElement('div');
        const content = target.createElement('main');
        target.body.append(styles, content);
        await readers.docx.renderAsync(data, content, styles, { useBase64URL: true, renderAltChunks: false,
            ignoreFonts: false, breakPages: true, ignoreLastRenderedPageBreak: false, renderComments: false });
        if (!alive) return;
        target.querySelectorAll('a').forEach(link => { link.removeAttribute('href'); link.removeAttribute('target'); });
        const pages = [...target.querySelectorAll('section.docx')];
        const wrapper = target.querySelector('.docx-wrapper');
        if (!pages.length || !wrapper) throw new PreviewError('This Word document has no readable pages.');
        let pageNumber = 1, zoom = 1;
        const pageWidth = Math.max(...pages.map(page => page.getBoundingClientRect().width));
        const fit = () => {
            if (!alive) return;
            wrapper.style.width = `${pageWidth + 36}px`;
            wrapper.style.zoom = String(Math.max(.1, (frame.clientWidth - 4) / (pageWidth + 36)) * zoom);
            sync(pageNumber, zoom);
        };
        const sync = navigation(pages.length, value => {
            pageNumber = value; pages[value - 1].scrollIntoView({ block: 'start' }); sync(pageNumber, zoom);
        }, value => { zoom = value; fit(); });
        target.addEventListener('click', event => { if (event.target.closest('a')) event.preventDefault(); });
        target.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                event.preventDefault();
                document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
            }
        });
        observer = new frame.contentWindow.IntersectionObserver(entries => {
            for (const entry of entries) if (entry.isIntersecting) { pageNumber = pages.indexOf(entry.target) + 1; sync(pageNumber, zoom); break; }
        }, { threshold: .15 });
        pages.forEach(page => observer.observe(page));
        resize = fit; window.addEventListener('resize', resize); fit();
        state('ready', `Word layout preview. Page breaks may differ slightly from Microsoft Word.${externalLinks ? ' Externally linked content is not loaded.' : ''}`);
    }
    async function start() {
        state('loading', 'Opening your document on this device...');
        const [data, readers] = await Promise.all([bytes(), loadReaders()]);
        if (!alive) return;
        const extension = file.name.split('.').pop().toLowerCase();
        if (extension === 'pdf' || file.type === 'application/pdf') await showPdf(data, readers);
        else if (extension === 'docx') await showDocx(data, readers);
        else {
            const result = await readers.extractLegacyDoc(data);
            if (!alive) return;
            const sections = { body: '', headers: 'Headers', footers: 'Footers', footnotes: 'Footnotes', endnotes: 'Endnotes', textboxes: 'Text boxes', annotations: 'Annotations' };
            const text = typeof result === 'string' ? result : Object.entries(sections).filter(([key]) => result[key]?.trim()).map(([key, label]) => (label ? `${label}\n\n` : '') + result[key]).join('\n\n');
            if (!text?.trim()) throw new PreviewError('This Word file has no readable text or is password-protected. Try an unprotected .docx copy.');
            const preview = element('pre', 'doc-preview-legacy', text);
            preview.dataset.legacyWordPreview = '';
            toolbar.hidden = true; stage.replaceChildren(preview);
            state('ready', 'Word (.doc) text preview. The original file keeps its formatting and images.');
        }
    }
    start().catch(fail);
    return { destroy() {
        alive = false; abort.abort(); observer?.disconnect();
        if (resize) window.removeEventListener('resize', resize);
        renderTask?.cancel();
        loadingTask?.destroy()?.catch(() => {});
        root.remove();
    } };
}

function checkWordArchive(buffer) {
    const view = new DataView(buffer);
    let end = -1;
    for (let offset = buffer.byteLength - 22; offset >= Math.max(0, buffer.byteLength - 65557); offset--) {
        if (view.getUint32(offset, true) === 0x06054b50) { end = offset; break; }
    }
    if (end < 0) throw new PreviewError('This .docx file is damaged or password-protected. Try an unprotected Word document.');
    const count = view.getUint16(end + 10, true);
    let offset = view.getUint32(end + 16, true), total = 0;
    if (count > 5000) throw new PreviewError('This document is too complex for a local preview. You can still upload the original file.');
    for (let index = 0; index < count; index++) {
        if (offset + 46 > buffer.byteLength || view.getUint32(offset, true) !== 0x02014b50) throw new PreviewError('This Word document is incomplete or damaged.');
        if (view.getUint16(offset + 8, true) & 1) throw new PreviewError('Use an unprotected Word document to preview its contents.');
        total += view.getUint32(offset + 24, true);
        if (total > 80 * 1024 * 1024) throw new PreviewError('This document is too large to expand in a local preview. You can still upload the original file.');
        offset += 46 + view.getUint16(offset + 28, true) + view.getUint16(offset + 30, true) + view.getUint16(offset + 32, true);
    }
}
