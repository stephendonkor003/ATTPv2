# Local document preview assets

This private build produces the committed browser assets in
`public/assets/vendor/document-preview`. Documents are processed in the browser;
the runtime does not send them to an external document conversion service.
No CDN or external viewer is required.

## Rebuild

Use Node.js 24 or newer, then run from `tools/document-preview`:

```sh
npm ci
npm run build
npm test
npm audit
```

Only this private tool package uses npm. Its build does not change the
application's existing asset pipeline. Commit `package-lock.json` and the
generated public assets; do not commit `node_modules`.

## Runtime API

```js
import { pdfjs, docx, JSZip, extractLegacyDoc }
    from '/assets/vendor/document-preview/runtime.js';

pdfjs.GlobalWorkerOptions.workerSrc =
    '/assets/vendor/document-preview/pdf.worker.min.js';

const pdf = await pdfjs.getDocument({
    data: bytes,
    cMapUrl: '/assets/vendor/document-preview/cmaps/',
    cMapPacked: true,
    standardFontDataUrl: '/assets/vendor/document-preview/standard_fonts/',
    wasmUrl: '/assets/vendor/document-preview/wasm/',
    iccUrl: '/assets/vendor/document-preview/iccs/',
    isEvalSupported: false,
}).promise;
```

- `pdfjs` is the PDF.js namespace. The worker is an ES module with a `.js`
  extension so standard web server JavaScript MIME mappings work.
- `docx` exposes the DOCX preview namespace, including `renderAsync`.
- `JSZip` supports local inspection of DOCX packages before rendering.
- `extractLegacyDoc(ArrayBuffer)` returns plain text fields `body`, `headers`,
  `footers`, `footnotes`, `endnotes`, `textboxes`, and `annotations` from Word
  97–2003 `.doc` files. This is a text preview, not Word page layout. The adapter
  rejects unreadable, encrypted, unsupported old formats and files over 25 MB.
  Render these fields with `textContent`; do not interpret them as HTML.

The application renderer must authorize document downloads, validate archive
limits, sanitize DOCX package markup and external relationships, and show useful
loading/error states. The libraries alone do not establish those boundaries.

## Pinned versions and licenses

- PDF.js (`pdfjs-dist`) 6.3.289 — Apache-2.0
- DOCX preview (`docx-preview`) 0.4.0 — Apache-2.0
- JSZip 3.10.1 — used under MIT
- Word extractor (`word-extractor`) 1.0.4 — MIT; only its binary Word parser is bundled
- esbuild 0.28.2 — MIT; build tool, not a browser dependency
- Buffer 6.0.3, process 0.11.10, stream-browserify 3.0.0 and events 3.3.0 — MIT browser compatibility dependencies

The exact dependency tree is locked in `package-lock.json`. The generated
`manifest.json` identifies each bundled dependency and version. Original
license notices are retained in `licenses/`, `runtime.js.LEGAL.txt`, and the
PDF.js support directories. Rebuild and audit when updating versions.
