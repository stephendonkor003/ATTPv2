# Think Tank Portal API migration roadmap

## Objective

Move the Think Tank portal UI into the separate Next.js application while Laravel remains the authoritative API, database, workflow, authorization, file-storage, notification, and audit service.

The migration is additive. Existing Blade routes stay operational until the matching API module has passed authorization, tenant-isolation, workflow, data-parity, and cutover tests. A screen is not removed merely because an API endpoint exists.

## Non-negotiable boundaries

- Every tenant-owned query derives the Think Tank membership from the authenticated server-side identity. Client-supplied tenant identifiers are never trusted.
- Browser authentication uses encrypted, HttpOnly session cookies with CSRF protection. Browser bearer tokens are not stored in local or session storage.
- A password-authenticated session is not a fully authenticated session until forced password change and MFA requirements are complete.
- APIs return explicit DTOs/resources. Eloquent models are never serialized directly.
- Mutations are authorized at the endpoint and domain-service layers, audited, validated, rate-limited where appropriate, and transactionally consistent.
- Financial, procurement, approval, payment, and audit history is never hard-deleted through the portal.
- Files remain private by default and are downloaded only through authorized, bounded endpoints or short-lived signed links.
- The Next.js service worker must never cache authenticated HTML, API JSON, documents, or mutation responses.
- Production requires HTTPS, shared durable sessions and rate limits, queue workers, scheduler workers, centralized logs, backups, and monitoring.

## API conventions

- Namespace: `/api/v1/think-tank`
- Authentication bootstrap: `/sanctum/csrf-cookie`
- Response cache policy: `private, no-store`
- Authentication states: `UNAUTHENTICATED`, `PASSWORD_CHANGE_REQUIRED`, `MFA_REQUIRED`, `READY`
- Stable error codes accompany HTTP status codes; validation errors use field-keyed messages.
- Lists use bounded pagination, server-side filtering, deterministic ordering, and tenant-scoped totals.
- Cross-tenant resource identifiers return `404` so resource existence is not disclosed.
- Concurrency-sensitive writes use transactions, row locks or optimistic version checks, and idempotency keys where retries could duplicate money or workflow effects.

## Delivery phases

### Phase 1: authentication and user management

Deliver secure session authentication, password lifecycle, MFA, logout, password recovery, safe current-user/session DTOs, access-level metadata, tenant-scoped staff listing, invitation, editing, activation/suspension, last-administrator protection, and security audit events.

The initial access levels remain the existing server-owned values:

- `think_tank_admin`
- `procurement_officer`
- `me_officer`
- `finance_officer`

This deliberately preserves current authorization behavior while the tenant role model is designed.

### Phase 2: tenant roles and permissions

Introduce tenant-scoped roles, permission catalogues, role assignments, separation-of-duties constraints, protected system roles, permission-change audits, access reviews, and migration from the four fixed access levels.

Do not expose the current global `roles`, `permissions`, `role_permission`, or `user_permission` tables directly to Think Tank administrators. They govern the wider ATTP system and are not a safe tenant boundary.

### Phase 3: financial budgets and work plans

Map existing annual work plans, activities, budget envelopes, revisions, approvals, funding sources, periods, attachments, and variance calculations into tenant-owned read/write APIs. Establish immutable approval snapshots and enforce parent-envelope capacity on the server.

### Phase 4: procurement plans

Expose annual plans, plan items, documents, validation, submission, review feedback, approval and no-objection states. Preserve the existing workflow service and server-side ownership checks instead of reimplementing state transitions in Next.js.

### Phase 5: procurement execution

Cover publication, solicitation, submissions, evaluation assignments and templates, scoring, approvals, award, contract, purchase order, delivery, invoice, and close-out. Sensitive vendor and evaluator information must be purpose-limited and conflict-of-interest controls must be explicit.

### Phase 6: monitoring and evaluation

Expose assignments, indicators, reporting periods, forms, drafts, evidence, submissions, data-quality checks, reviews, performance reports, notifications, and approved result views. Evidence files remain private and all transitions retain their actor and timestamp.

### Phase 7: payments and disbursements

Expose allocations, requests, approvals, disbursement records, receipt confirmation, invoices, supporting documents, reconciliation, reversals and exception handling. Monetary mutations require idempotency, decimal-safe calculations, segregation of duties, and immutable ledger-style audit records.

### Phase 8: reporting

Provide module reports, consolidated reports, bounded exports, scheduled report generation and download status. Expensive exports run through durable queues and private storage rather than holding web requests open.

### Phase 9: audit management

Provide tenant-scoped audit search, event detail, export, retention classification, security-event review, exception flags and evidence links. Audit records are append-only and redact secrets, cookies, reset tokens, OTPs and document bytes.

### Phase 10: dashboard and cutover

Replace sample dashboard data with permission-aware, tenant-scoped summary endpoints. Use pre-aggregated or cached read models where necessary, with clear freshness timestamps. Complete module-by-module parity evidence, operational readiness, rollback procedures and legacy route retirement.

## Acceptance gate for every phase

Each phase is complete only when all of the following are true:

1. API contract and DTO fields are documented.
2. Positive and negative authorization tests cover every role/capability.
3. Cross-tenant read and mutation attempts are proven to fail.
4. State transitions and financial calculations are tested on the server.
5. Sensitive fields and files are absent from unauthorized responses and logs.
6. Rate limits, audit events, retries and concurrency behavior are tested.
7. Next.js loading, empty, validation, forbidden, expired-session and offline states work.
8. Existing Laravel workflows pass regression checks.
9. Production migrations, queues, scheduler, storage, backups and rollback steps are documented.
10. The legacy screen remains available until real data parity is verified and cutover is explicitly approved.

## Known production blockers discovered during Phase 1

- Runtime session files are present in Git state and must be removed from version control and invalidated before deployment.
- Local development uses file-backed sessions and cache. Production needs a durable shared store.
- Current password-era OTP records store recoverable codes; Phase 1 includes a hashed, single-use replacement.
- Existing temporary-password email flows must be retired in favor of one-time password-setting links.
- A case-insensitive duplicate email group exists outside the Think Tank account set. It must be repaired before a database-wide normalized email uniqueness constraint is applied.
- Dependency security advisories must be resolved and `composer audit` must pass before production cutover.
- Current live-like Think Tank accounts are blocked; testing must use isolated fixtures rather than silently reactivating them.

## Local integration checkpoint - 2026-09-11

The separate Next.js portal already contains the Phase 1 authentication and user-management screens. It now connects to Laravel at `http://127.0.0.1:8000` from `http://localhost:3000` through narrowly scoped runtime forwarding. The API origin is server-only; cookies, CSRF protection, password-change requirements, MFA, and tenant authorization remain owned by Laravel.

The existing PostgreSQL database was inspected before applying only these two pending migrations:

- `2026_09_01_212423_harden_user_login_otp_storage`: expanded OTP storage to 64-character digests and retired historical plaintext values. No active OTP challenges existed at migration time.
- `2026_09_01_220000_enforce_unique_think_tank_portal_user_assignment`: added the uniqueness constraint after verifying zero duplicate primary assignments.

All 15 existing Think Tank accounts were effectively login-blocked, and one primary membership link did not match its explicit account assignment. Those account settings and links were left for explicit administrator review. Local mail uses the log transport, so OTP/invitation/reset messages are not inbox deliveries.

Confirmed integration fixes include expired temporary login blocks being respected consistently during staff mutations, correct blacklisted-user invitation controls, visible invitation delivery outcomes after email changes, and runtime API forwarding using the same upstream as server-side authentication checks.

The real HTTP harness also exposed two authentication handoff gaps: the legacy password/OTP middleware redirected Sanctum's CSRF bootstrap after a page refresh, and password changes left Sanctum's current session bound to the previous password hash. The CSRF bootstrap is now exempt from those legacy page redirects; protected pages and API readiness checks still enforce password changes and MFA. Password updates refresh the authenticated session identity before the next MFA step.

Pending authentication forms retain validation messages and typed retry codes while the session is rechecked. Authenticated workspace content remains hidden during revalidation, including access-revocation checks.

Fresh invitation/reset pages keep their document-level `no-referrer` policy. Only API and CSRF fetches send an origin-only referrer, providing Sanctum's required first-party context without disclosing the reset-token path or email query.

The focused backend checks pass 32 tests / 251 assertions. The final frontend production build, TypeScript, lint, and all 17 mocked browser tests pass, including natural MFA retry, delayed session revocation, and fresh reset-link referrer privacy.

The complete real HTTP/browser harness also passes: real session/CSRF checks, forced password change, invalid-to-valid OTP retry, tenant isolation, invitation creation/edit/resend, fresh one-use password setup, restricted officer access, active-session revocation, logout, and responsive user management. It reports zero browser exceptions and zero HTTP 5xx responses. The normal running applications separately pass browser checks at ports 3000 and 8000.

Start both local applications from the backend directory with:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts\start-think-tank-local.ps1
```

The authenticated integration harness is documented in the sibling portal's `tests/live/README.md`. It uses a separate SQLite database, database sessions and rate limits, real CSRF/MFA, captured local-only test mail, and synthetic `example.test` accounts. It never loads the application's `.env` or alters the normal PostgreSQL accounts.

This checkpoint connects and validates authentication and user management. Tenant roles, budgets, procurement, M&E, payments, reports, audit screens, and dashboard business records remain subsequent migration phases; their sample screens are not API-complete. The existing Blade workflows remain available.
