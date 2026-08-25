# ATTP API Sync provider

ATTP exposes a read-only, versioned pull API that lets an authorized AU-PReMIS background job import portfolio and financial records from independently deployed ATTP projects. The provider never accepts writes to ATTP business data. New integrations use the AU-PReMIS-initiated v2 protocol below. The former ATTP-generated-code v1 protocol is disabled by default and may be enabled only for a controlled, time-boxed migration with `ATTP_API_SYNC_V1_ENABLED=true`.

## Primary connection flow (v2)

Every separately branded ATTP deployment has its own stable instance ID and public origin. An AU-PReMIS administrator registers that origin and initiates a synchronization request centrally. AU-PReMIS generates the seven-digit code; ATTP never generates, receives in an invitation, stores, logs, audits, flashes, or redisplays that code.

1. AU-PReMIS sends `POST /api/sync/v2/invitations` with a recursively key-sorted JSON body and an RSA-SHA256 signature. The configured RSA key must be at least 3072 bits and match both the pinned key ID and SHA-256 PEM fingerprint.
2. The invitation is accepted only when its central instance, central origin, target origin, callback URL, timestamp, one-time nonce, requested datasets, scopes, approval expiry, and credential expiry match the local allowlist and signed contract.
3. A local `API Sync Administrator` reviews the request under **System > API Sync**, enters the seven digits displayed in AU-PReMIS, and confirms their current password. Five attempts are allowed by default. Sensitive form fields are explicitly excluded from request and model audit payloads.
4. ATTP calls the exact signed `confirmation_url`. While that request is open, AU-PReMIS proves possession of its separately generated 32-byte bearer credential through signed `POST /api/sync/v2/invitations/{invitation}/activate`. ATTP compares only the bearer SHA-256 digest stored in the signed invitation.
5. Only successful activation creates and queues the immutable snapshot. The activation response always contains its stable snapshot ID and credential expiry, even while status is `pending`. The signed AU-PReMIS confirmation receipt is verified and stored using Laravel’s encrypted cast.
6. AU-PReMIS reads only the approved scope through `GET /api/sync/v2/manifest`, `GET /api/sync/v2/datasets/{dataset}`, the document endpoints below, and `POST /api/sync/v2/complete`, with the bearer and exact `X-Consumer-Instance`. An unapproved dataset or document scope returns `403`; a building snapshot returns typed `425` with `Retry-After`.
7. Snapshot materialization and central import use durable background queues, so users continue working. Completion or local revocation immediately destroys the credential and schedules snapshot purging.

Signed request bytes are exactly `METHOD\nPATH\nTIMESTAMP\nNONCE\nREQUEST_ID\nSHA256(canonical-json)`. Signed confirmation response bytes replace `METHOD` with the numeric HTTP status. All five `X-AUPReMIS-*` signature headers are mandatory. Keep server clocks synchronized.

The local code/password approval receives a signed activation authorization and returns immediately. AUPReMIS then performs credential activation from its durable worker/outbox and sends a signed finalization notice. There is no nested callback cycle, so the approval step also completes safely when both applications use a single-worker PHP built-in server; production still requires durable queue workers and scheduler recovery.

Outbound confirmation never follows redirects, accepts only the exact configured central origin and callback path, validates every resolved address, and pins DNS through cURL. Production fails closed when DNS pinning is unavailable. Private or reserved addresses are rejected unless private networking is explicitly enabled **and every resolved private address is individually listed** in `ATTP_API_SYNC_V2_ALLOWED_IPS`; enabling private networking is not a wildcard.

Core v2 scopes are `records.read`, `documents.metadata.read`, and `documents.content.read`. The central invitation sends both document scopes behind one operator choice. ATTP stages or exposes no document unless both were signed and locally approved. `ATTP_API_SYNC_V2_DOCUMENTS_ENABLED=false` disables the document phase for that deployment without widening any other scope.

### Immutable v2 document contract

The document allowlist is deliberately closed. It contains only validated attachments of approved/archived performance reports, attachments of reviewed/archived mission reports, and the latest validated, non-retired private repository version. A performance attachment promoted to the same repository item/version is transferred once under the repository-version identity while retaining approved project links. Applicants, identity/HR/CV, vendor, banking, bids/evaluations, procurement, contracts, invoices, purchase orders, disbursements, receipts, grants/funding agreements, purchase-request attachments, grievances, discussions, external URLs, and free-form submission evidence are never queried.

The provider first stores an immutable source manifest with opaque transfer IDs and revision hashes. It then copies approved bytes through the dedicated, non-servable `api_sync_documents` disk to `storage/app/private/api-sync/v2-document-snapshots/<snapshot UUID>/` under random UUID filenames. Source paths, uploader/validator identities, external URLs, and private notes never enter the API manifest or audit. Each file is stat-checked before/after copying, SHA-256 hashed, MIME-sniffed, and rechecks approval/version/project links before atomic promotion. Missing, changed, empty, oversized, checksum-mismatched, encrypted/active PDF, macro/embedded Office, executable, archive, unsafe-path, or otherwise uninspectable content becomes a human-readable `held` item; record snapshot preparation continues.

Inventory requires bearer authentication, `X-Consumer-Instance`, exact `X-AUPReMIS-Invitation-Id`, both approved scopes, and the session snapshot:

```http
GET /api/sync/v2/documents/inventory?snapshot_id=<uuid>&limit=100&cursor=<opaque>
```

It returns `data` rows with stable source `id`, nullable repository `source_version_id`, opaque `transfer_id`, one of the three fixed `source_type` values, category, restricted classification, safe title/filename, detected MIME, positive byte length, SHA-256/ETag, ready/held state, safe hold reason, portfolio/project relationships, and capture timestamps. `meta` contains snapshot status, ready/held/total counts, bytes, and the signed next cursor. It never returns a storage path.

Ready bytes are fetched only with one explicit RFC byte range and the inventory ETag:

```http
GET /api/sync/v2/documents/<transfer UUID>/content?snapshot_id=<uuid>
Range: bytes=0-4194303
If-Match: "<full-file-sha256>"
```

The response is exactly `206` with `Content-Range`, `Content-Length`, `Accept-Ranges`, `Content-Type`, safe attachment disposition, `ETag`, full `X-Content-SHA256`, per-chunk `X-Chunk-SHA256`, `X-Snapshot-Id`, `X-Document-Transfer-Id`, and `Cache-Control: no-store, private`. A range is capped at 4 MiB. Missing/mismatched `If-Match` returns `412`; missing, multiple, suffix, open-ended, out-of-bounds, or oversized ranges return `416`; a building inventory returns `425`; purged bytes return `410`.

The consumer must still quarantine each download, verify every range and full-file hash, detect MIME independently, run current malware scanning fail-closed, and atomically promote only a clean completed version. ATTP intentionally accepts no completion until the requested document snapshot is ready (held entries count as safely terminal).

## Deployment requirements

1. Copy the values from `.env.api-sync.example` into the deployment environment. Give every deployed project a permanent, unique `ATTP_API_SYNC_INSTANCE_ID` and its own random `ATTP_API_SYNC_PAIRING_PEPPER`.
2. Serve the API only over HTTPS. Production requests are rejected when `ATTP_API_SYNC_REQUIRE_HTTPS=true` and Laravel does not recognize the connection as secure, so configure trusted proxy headers correctly. Do not permit a reverse proxy or application logger to record `Authorization`, `Idempotency-Key`, `X-Claim-Recovery-Key`, pairing-code bodies, or response bodies from the claim endpoint.
3. Keep `ATTP_API_SYNC_V1_ENABLED=false`, run the additive migrations, and run the normal authorization seeder. With v1 disabled, the migration-only routes are not registered and its generate/revoke grants are removed from the API Sync Administrator role.
4. Run a dedicated supervised worker: `php artisan queue:work api_sync_database --queue=api-sync --timeout=1860 --tries=3`. Do not use the synchronous queue driver. The long timeout still accommodates the atomic record snapshot. Document snapshots use the same durable queue but persist the complete source manifest first, checkpoint every file, process at most `ATTP_API_SYNC_V2_DOCUMENTS_PER_JOB` and `ATTP_API_SYNC_V2_DOCUMENT_JOB_SECONDS` per continuation, and resume only unfinished rows without deleting completed bytes. Keep `ATTP_API_SYNC_SNAPSHOT_RETRY_AFTER` above the worker timeout/build mutex (secure default 2,100), and make the supervisor stop grace longer than the worker timeout.
5. Keep Laravel's scheduler running. `api-sync:expire` revokes expired codes and credentials every five minutes; `api-sync:snapshots:maintain` recovers missed record and document continuations and purges closed private storage every minute. Queue uniqueness and document build/purge mutexes require a shared atomic cache store on multi-node deployments.
6. Keep `storage/app/private` outside the public web root, disable web-server aliasing of that directory, grant only the application/worker account access, monitor capacity for the hard 2 GiB-per-session ceiling, and deploy current malware scanning on the AU-PReMIS quarantine consumer. Do not make the staging prefix a public or temporary-URL filesystem.
7. Keep the server clock synchronized. Code, capture and session expiry depend on accurate UTC time.

The `API Sync Administrator` role receives the v2 invitation permissions, `api_sync.audit.view`, and `api_sync.documents.view`. System administrators retain full access through the existing authorization policy. The document permission reveals progress and held counts only, never paths or hashes. Legacy generate/revoke permissions are granted only while the v1 migration flag is deliberately enabled.

## Legacy v1 pairing lifecycle (disabled by default)

An authorized ATTP administrator opens **System > API Sync**, confirms their password, and generates a seven-digit code. The code:

- expires after 10 minutes by default;
- is stored only as an HMAC digest;
- can be claimed once;
- is replaced when its creator generates another unused code.

AU-PReMIS exchanges the code for one random bearer credential. ATTP stores only its SHA-256 digest. The credential is bound to the supplied `consumer_instance` and a single `snapshot.id`, expires after six hours by default, has no refresh operation, and is revoked immediately when completion is confirmed. Configuration is constrained to 30-1,440 minutes, so no synchronization credential can remain usable for more than 24 hours. Claiming creates durable pending snapshot metadata and queues encrypted background materialization; it does not run a large export inside the HTTP request.

Before claiming, AU-PReMIS also creates an independent 32-byte random value, encodes it as 43-character unpadded base64url, and retains it only until the claim outcome is known. ATTP stores only the SHA-256 digest of this recovery key. It can revoke an ambiguously claimed session but can never recover or redisplay the bearer credential.

## Legacy HTTP contract (v1; available only when explicitly enabled)

All responses are JSON. Authenticated requests require both headers:

```http
Authorization: Bearer attp_sync_<random credential>
X-Consumer-Instance: aupremis-production-01
Accept: application/json
```

### 1. Claim a pairing code

```http
POST /api/sync/v1/pairings/claim
Content-Type: application/json
Accept: application/json
Idempotency-Key: 946f744d-29a3-4cd4-aa70-0730741033d5
X-Claim-Recovery-Key: aBcDeFgHiJkLmNoPqRsTuVwXyZ0123456789_-ABCDE

{
  "code": "1234567",
  "consumer_instance": "aupremis-production-01",
  "consumer_name": "AU-PReMIS Administration"
}
```

Successful response (`201 Created`, with `Cache-Control: no-store`):

```json
{
  "data": {
    "access_token": "attp_sync_<shown-once-random-value>",
    "token_type": "Bearer",
    "expires_at": "2026-08-25T17:00:00Z",
    "consumer_instance": "aupremis-production-01",
    "instance": {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "name": "ATTP Project Name",
      "code": "ATTP-WB-PROJECT",
      "api_version": "v1"
    },
    "snapshot": {
      "id": "d23a7c30-8bd9-4b89-88a1-30da20886010",
      "created_at": "2026-08-25T11:00:00Z",
      "requested_at": "2026-08-25T11:00:00Z",
      "captured_at": null,
      "expires_at": "2026-08-25T17:00:00Z",
      "status": "pending"
    },
    "datasets": [
      {"name": "portfolios", "count": null, "schema_version": 1, "status": "pending"},
      {"name": "programmes", "count": null, "schema_version": 1, "status": "pending"}
    ]
  }
}
```

`Idempotency-Key` is mandatory and must be a new UUID. `X-Claim-Recovery-Key` is also mandatory, must be unique per claim, and must contain at least 32 bytes of cryptographically secure entropy encoded as unpadded base64url. Because ATTP stores only the bearer digest, it cannot replay the original credential. An exact repeated claim returns deterministic `409 pairing_claim_already_processed`. AU-PReMIS must not automatically retry the claim. A successful response deliberately has null counts: the consumer must poll the manifest before requesting pages.

### 2. Abandon an ambiguous claim

If the claim response is lost, AU-PReMIS closes the possibly active credential with the recovery key from that exact claim. This route is deliberately outside bearer authentication because the bearer is the missing value. It remains HTTPS-only, consumer-bound, recovery-key authenticated, row-locked and independently rate-limited.

```http
POST /api/sync/v1/pairings/abandon
Accept: application/json
X-Claim-Recovery-Key: aBcDeFgHiJkLmNoPqRsTuVwXyZ0123456789_-ABCDE
X-Consumer-Instance: aupremis-production-01
Idempotency-Key: 43a03d58-b61c-41a6-8458-730af668bb35
```

`Idempotency-Key` is optional on this operation. A valid retry against an already abandoned session returns the same response:

```json
{"data":{"status":"abandoned","credential_revoked":true}}
```

The response never contains a bearer credential, snapshot identifier or dataset metadata. Invalid recovery credentials, a consumer mismatch, an ineligible state, or an abandonment racing a still-uncommitted claim return the same generic `409 pairing_abandonment_unavailable` error. A `409` does **not** prove that no session exists: AU-PReMIS must retain the recovery key and retry abandonment with bounded backoff. Only an explicit `200` abandonment response, or expiry of the provider's hard 24-hour maximum session lifetime measured from the claim attempt, confirms that the possible credential is no longer usable. After a successful abandonment, an ATTP administrator can generate a new pairing code and AU-PReMIS must use a new recovery key for the new claim.

### 3. Read the manifest

```http
GET /api/sync/v1/manifest
Authorization: Bearer <credential>
X-Consumer-Instance: aupremis-production-01
```

Response envelope:

```json
{
  "data": {
    "instance": {"id": "...", "name": "...", "code": "...", "api_version": "v1"},
    "snapshot": {
      "id": "...",
      "created_at": "2026-08-25T11:00:00Z",
      "requested_at": "2026-08-25T11:00:00Z",
      "captured_at": "2026-08-25T11:00:02Z",
      "materialized_at": "2026-08-25T11:00:08Z",
      "expires_at": "2026-08-25T17:00:00Z",
      "status": "ready",
      "record_count": 1842,
      "payload_bytes": 2948190
    },
    "datasets": [{"name": "portfolios", "count": 1, "schema_version": 1, "status": "ready"}]
  }
}
```

Until the immutable rows have committed, both manifest and dataset requests return a typed, retryable response and do not query live business tables:

```http
HTTP/1.1 425 Too Early
Retry-After: 5
Content-Type: application/json

{"error":{"code":"snapshot_building","message":"The immutable synchronization snapshot is still being prepared. Retry the manifest shortly."}}
```

The consumer should defer its background job for at least `Retry-After` seconds without treating this expected state as a failed synchronization attempt. A `409 snapshot_failed` is terminal for that claim; abandon it and generate a new pairing after the provider issue is corrected.

### 4. Pull a dataset page

```http
GET /api/sync/v1/datasets/projects?snapshot_id=d23a7c30-8bd9-4b89-88a1-30da20886010&limit=100&cursor=<opaque-cursor>
Authorization: Bearer <credential>
X-Consumer-Instance: aupremis-production-01
```

Omit `cursor` on the first request. Repeat with `meta.next_cursor` while `meta.has_more` is `true`. Cursors are signed and bound to the dataset, consumer and snapshot; clients must treat them as opaque.

```json
{
  "data": [
    {
      "id": "0191d9c2-a7c5-70ae-bbb7-8415ee06cd6c",
      "checksum": "f234...",
      "updated_at": "2026-08-20T09:13:00Z",
      "attributes": {
        "source_code": "PRJ-001",
        "name": "Regional trade facilitation",
        "description": "...",
        "currency": "USD",
        "start_year": 2025,
        "end_year": 2028,
        "duration_years": 4,
        "total_budget": "2500000.00",
        "expected_outcome_type": "outcome",
        "expected_outcome_value": "..."
      },
      "relationships": {
        "portfolio": "...",
        "programme": "...",
        "governance_unit": "..."
      }
    }
  ],
  "meta": {
    "dataset": "projects",
    "schema_version": 1,
    "snapshot_id": "d23a7c30-8bd9-4b89-88a1-30da20886010",
    "limit": 100,
    "returned": 1,
    "total": 1,
    "next_cursor": null,
    "has_more": false
  }
}
```

The ordered dataset names are:

1. `portfolios` (`myb_sectors`)
2. `programmes` (`myb_programs`)
3. `projects` (`myb_projects`)
4. `activities` (`myb_activities`)
5. `sub_activities` (`myb_sub_activities`)
6. `fiscal_years` (deterministic calendar-year records derived from ATTP budget years)
7. `budget_allocations` (normalized project, activity, and sub-activity allocation rows)
8. `commitments` (`myb_budget_commitments`)
9. `executions` (recognized paid procurement disbursements)

Money is represented as a decimal string. Relationships contain stable source identifiers. The checksum covers normalized attributes and relationships so an idempotent consumer can skip unchanged records. Private user credentials, approver identities, vendor/bank details, documents, internal notes, and rejected claim secrets are never exported.

### Allocation identity contract

Allocation identifiers are typed so independently numbered source tables cannot collide:

- A `budget_allocations` record ID is `<canonical-level>:<allocation-row-id>`, for example `project:<source-id>`, `activity:<source-id>`, or `sub_activity:<source-id>`.
- Every allocation record also exposes `relationships.allocation_target` as `<canonical-level>:<hierarchy-target-id>` and `relationships.fiscal_year` as `FY-<year>`.
- A commitment's source `allocation_id` points to the project, activity, or sub-activity target, not to a yearly allocation row. Consequently, `commitments.relationships.allocation_target` uses that typed target identity. Consumers resolve a budget allocation using both `allocation_target` and `fiscal_year`; they must not treat the target as the allocation record primary key.
- An execution linked through purchase order -> budget commitment exposes the same `allocation_target`, its `commitment`, and its paid-date `fiscal_year`. Executions without that chain remain in the dataset with the unresolved relationships omitted.

The provider normalizes the known ATTP vocabulary and safe historical aliases to `project`, `activity`, and `sub_activity`. An unrecognized level never becomes an identifier prefix: the record is retained, its normalized level is `null`, and the unsafe typed relationship is omitted for consumer review.

Example normalized allocation:

```json
{
  "id": "sub_activity:4f2064af-9dfa-49bc-af09-923f7f56b914",
  "attributes": {"level": "sub_activity", "year": 2026, "amount": "120000.00", "currency": null},
  "relationships": {
    "allocation_target": "sub_activity:aceaa61c-1d30-4d88-bb3b-c89e434d56ac",
    "sub_activity": "aceaa61c-1d30-4d88-bb3b-c89e434d56ac",
    "fiscal_year": "FY-2026"
  }
}
```

Commitment and execution relationship examples:

```json
{
  "commitment": {
    "relationships": {
      "allocation_target": "sub_activity:aceaa61c-1d30-4d88-bb3b-c89e434d56ac",
      "fiscal_year": "FY-2026"
    }
  },
  "execution": {
    "relationships": {
      "commitment": "70fe9961-8884-441f-972b-2f004cc31314",
      "allocation_target": "sub_activity:aceaa61c-1d30-4d88-bb3b-c89e434d56ac",
      "fiscal_year": "FY-2026"
    }
  }
}
```

### Snapshot boundary

The background builder reads every source chunk inside one PostgreSQL `REPEATABLE READ` transaction. A database clock timestamp taken as the repeatable-read snapshot is acquired becomes `snapshot.captured_at`; all source timestamp filters use this real capture point, not the earlier claim time. The stable `snapshot.created_at`/`requested_at` values identify when the snapshot was requested, while `captured_at` identifies its data boundary and `materialized_at` identifies the atomic ready commit.

Timestamped datasets admit records visible at the repeatable-read capture whose `created_at` and `updated_at` are not later than `captured_at` (legacy null creation times are retained). Paid executions must also have `paid_at <= captured_at`. Commitment snapshot membership prefers `created_at`; schemas without it fall back to `approved_at <= captured_at`, while null approval values remain included so intended draft/submitted records are not lost. Post-capture mutations are picked up only by a later synchronization.

Legacy project-allocation rows have no trustworthy event timestamp, so their values are those visible in the same repeatable-read transaction. Once built, no manifest or page endpoint queries a business table: normalized JSON, source checksum, exact-payload hash, stable sequence and counts come only from provider-owned materialized rows. All datasets become ready in one commit or none do. A failed attempt rolls back completely and a retry restarts the capture rather than resuming partial rows; this intentionally prevents mixed database versions.

Materialization is memory-bounded by chunk size and storage/time-bounded by configuration. It does not lock source business rows. Only the final pairing transition takes a short row lock. Abandonment, completion, revocation and expiry revoke access immediately and mark storage for deadlock-safe asynchronous deletion.

### 5. Confirm completion

```http
POST /api/sync/v1/pairings/complete
Authorization: Bearer <credential>
X-Consumer-Instance: aupremis-production-01
Content-Type: application/json

{"snapshot_id":"d23a7c30-8bd9-4b89-88a1-30da20886010"}
```

Response:

```json
{"data":{"status":"completed","snapshot_id":"d23a7c30-8bd9-4b89-88a1-30da20886010","credential_revoked":true}}
```

## Error envelope

Domain and authentication failures use a stable machine code:

```json
{"error":{"code":"snapshot_mismatch","message":"The snapshot does not belong to this synchronization session."}}
```

Relevant status codes are `401` (credential or instance mismatch), `404` (unknown dataset), `409` (claim replay, snapshot failure/capacity, abandonment or session conflict), `422` (validation, code, recovery-key reuse or cursor failure), `425` (snapshot still building), `429` (rate limit), and `503` (provider disabled or queue unavailable). Dataset GET requests are safe to retry with the same signed cursor after transient transport or server failures. Abandonment is also safe to retry with the same recovery credentials; the claim itself is not. A capacity response includes `Retry-After: 60` and does not consume the still-valid pairing code.

## Audit and operational limits

- Claim and abandonment attempts are independently rate-limited to five per minute per source; authenticated pulls are limited to 180 requests per minute.
- Page size defaults to 100 and is capped at 250, irrespective of a larger client value.
- The secure defaults allow at most two unpurged snapshot reservations. Claims serialize on a provider-owned database row, so concurrent codes cannot bypass this quota. The hard cap is 10 sessions; each session is separately capped at 250,000 records, 512 MiB total JSON, 256 KiB per record and 900 seconds. Absolute code caps are 1,000,000 records, 2 GiB, 1 MiB, 1,800 seconds and 10 active reservations.
- Snapshot rows are deleted after completion, abandonment, administrator revocation or expiry. `api-sync:snapshots:maintain --limit=100` is safe to run repeatedly and also redispatches a pending build if initial queue publication was interrupted.
- Immutable document bytes are purged after completion, abandonment, administrator revocation, or credential expiry through a compare-and-set transition protected by the same per-session build mutex. Safe manifest metadata and append-only held/audit issues remain for accountability; remote omission never deletes source business documents.
- Every pairing generation, claim, abandonment, rejection, expiry, revocation, completion, materialization/failure/purge, manifest export and dataset page export is recorded atomically in `api_sync_events` and the human-readable system audit.
- Pairing and audit models are excluded from generic model-diff logging so hashes cannot leak into broad audit payloads.
- The snapshot job payload is encrypted, duplicate workers are stopped by both queue uniqueness and a shared-cache build mutex, and queue retry timing is validated before any code is consumed.
- Text fields are capped per record and every endpoint returns a bounded number of integrity-verified frozen records.
- Document limits are hard-capped at 1,000 manifest items, 20 MiB per non-empty file, 2 GiB per session, 250 inventory rows, and 4 MiB per byte range. Work is manifest-first and resumable; completed files are not recopied after a worker restart.
