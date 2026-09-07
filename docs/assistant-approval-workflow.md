# Assistant purchase request and disbursement approval

The assistant prepares the same financial forms through `/administrative-assistant/requests/create` and `/administrative-assistant/disbursements/create`. The old assistant create-PR bookmark redirects to the full form. Historical intake records remain available for reading and back-office completion.

Submissions are kept in `assistant_submissions` and private storage. Pending or rejected submissions create no purchase requests, budget commitments, disbursements, line evidence, receipts, or financial report entries. Approval locks the submission and its allocation, funding, or purchase order, repeats normal financial validation, and posts once. PRs and their commitments are then approved. Creator attribution remains with the assistant; the review record identifies the approving admin. A changed balance or invalid source prevents posting and leaves the submission pending.

Assistants see their own submissions at `/administrative-assistant/submissions`. Active System Admin and Super Admin users review at `/assistant-approvals`. Self-approval is prohibited. The main coordinator defaults to `chirwat@africanunion.org`; set `ASSISTANT_COORDINATOR_EMAIL` to change it. All eligible active admins receive their own notification, and any of them can decide.

## Deployment

1. Run `php artisan migrate --path=database/migrations/2026_09_07_000001_create_assistant_submissions_table.php --force`.
2. Configure an authenticated delivery mailer, set `ASSISTANT_APPROVAL_MAILER=smtp` (or the configured mailer name), and set the public `APP_URL` using the server's secret/configuration management. The log/array mailers do not deliver email; the submission explicitly shows that delivery is not configured.
3. Refresh configuration with `php artisan config:cache`.
4. Run a supervised worker: `php artisan queue:work database --queue=assistant-approvals --tries=4 --timeout=60`.
5. After enabling mail or resolving delivery failures, run `php artisan assistant-submissions:notify-pending` to queue undelivered pending alerts. This command sends real notifications through the worker.

The branded email includes the creator's actual name, an urgent review link requiring authentication, and a PDF containing the submitted details and document inventory. Successful recipients are recorded so retries skip them. SMTP acceptance is recorded as sent; inbox delivery is not guaranteed. Failed notifications do not discard or approve submissions.

## Verification

`php tests/Smoke/assistant_submission_approval_smoke.php` exercises the workflow with database rollback and fake storage/mail. `php tests/Smoke/assistant_submission_notifications_smoke.php` verifies notification handling without sending messages.
