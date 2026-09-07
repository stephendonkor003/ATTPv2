# Administrative assistant document previews

`assistant-document-preview.cjs` checks the running page over HTTP with its real
authentication middleware and Content Security Policy. It does not replace the
page with static HTML. It selects generated local documents and never submits
them; every mutating browser request is blocked. The application's unrelated
visit-tracker attempt is recorded separately from document requests.

Run against a local checkout with an active administrative assistant whose
existing credentials have not expired. The session helper does not change users,
passwords, procurement records, or approvals. It creates one temporary session
and destroys it when the test finishes, including failures.

On Windows with Microsoft Edge installed:

```powershell
# One-time test dependencies, outside the application directory.
npm install --prefix "$env:TEMP/aa-browser-check" playwright
python -m pip install pymupdf Pillow python-docx

$env:NODE_PATH = "$env:TEMP/aa-browser-check/node_modules"
node tests/Browser/assistant-document-preview.cjs
```

The default route is the current local evidence page. To use another existing
record or local application port:

```powershell
$env:ASSISTANT_PREVIEW_BASE_URL = 'http://127.0.0.1:8000'
$env:ASSISTANT_PREVIEW_PATH = '/administrative-assistant/purchase-orders/ORDER_ID/items/ITEM_ID?year=2026&month=3'
node tests/Browser/assistant-document-preview.cjs
```

The runner accepts only loopback hosts; its PHP helper accepts only Laravel
`local` or `testing` environments. `PHP_BINARY` and `PYTHON_BINARY` can select
installed executables. Results, harmless fixtures, and desktop/mobile screenshots
are written to the temporary output directory printed by the test. Temporary
session credentials are removed before the process exits.

Coverage includes PDF canvas contents, pagination, zoom, password handling,
formatted DOCX text/tables/embedded images, external Word image relationships,
JPEGs, real legacy Word text with headers and footers, malformed-file recovery,
focus restoration, Escape inside Word,
asynchronous close/reopen, mobile fitting, CSP violations, and network activity.
The generated legacy `.doc` tests error recovery; the separately licensed
`tools/document-preview/tests/fixtures/test15.doc` tests actual legacy extraction.
