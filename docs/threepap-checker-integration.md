# 3PAP sanctions-screening integration

ATTP uses the official 3PAP REST API to screen procurement applicant names
against international sanctions and debarment datasets. The integration sends
only the applicant/entity name and, when available, its country.

The published 3PAP API does not currently expose the proposal-document checker,
plagiarism, similarity, originality, or AI-detection workflow. Do not send
proposal PDFs to an undocumented endpoint. This integration therefore covers
the documented sanctions-screening contract only.

## Runtime configuration

Store the credential in the deployment environment or secret manager. Never
commit it to Git or expose it in browser-side code.

```dotenv
THREEPAP_CHECKER_BASE_URL=https://checker.3pap.africa/api/v1
THREEPAP_CHECKER_API_TOKEN=replace-with-the-secret-token
THREEPAP_CHECKER_CONNECT_TIMEOUT=5
THREEPAP_CHECKER_TIMEOUT=20
# Optional: an absolute PEM path when the server uses a custom CA bundle.
THREEPAP_CHECKER_CA_BUNDLE=
```

TLS certificate verification is always enabled. When no custom path is set,
the application uses the host's trusted CA store or the packaged Mozilla CA
bundle as a secure fallback.

Refresh Laravel's cached configuration after changing the environment:

```bash
php artisan optimize:clear
php artisan config:cache
```

Verify authentication, the required `sanctions_search` scope, and the remaining
monthly quota without screening an applicant or consuming a sanctions credit:

```bash
php artisan threepap:verify
```

The token itself is never printed by the verification command.

## Application workflow

- New public and vendor procurement submissions are screened after the response
  has finished when the integration is configured.
- Authorised internal users can run or re-run one applicant from the screening
  report, or screen accessible applicants in batches of at most 50.
- Opening a report is read-only. Screening requests use CSRF-protected POST
  actions because every entity consumes one monthly sanctions-search credit.
- A re-run clears any earlier human fit/not-fit decision. The new result must be
  reviewed again.
- Network, authentication, scope, quota, and provider errors are recorded as a
  failed screening rather than crashing the applicant workspace.
- A risk match is evidence for human review, not an automatic exclusion.

## Provider contract

The implementation follows the published API base URL, Bearer authentication,
`POST /sanctions/screen`, `POST /sanctions/batch`, and `GET /usage` contracts.
The official batch maximum is 50 entities. Monthly quotas reset on the first day
of each month, and the provider returns HTTP 429 with `LIMIT_REACHED` when the
allowance is exhausted.

Official reference: <https://checker.3pap.africa/developers>
