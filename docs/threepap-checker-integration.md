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

# Durable automatic screening. Never use the sync queue in production.
THREEPAP_CHECKER_AUTOMATIC_ENABLED=true
THREEPAP_CHECKER_QUEUE_CONNECTION=database
THREEPAP_CHECKER_QUEUE=threepap
THREEPAP_CHECKER_CACHE_STORE=database
THREEPAP_CHECKER_RECOVERY_LIMIT=25
THREEPAP_CHECKER_RECOVERY_LOOKBACK_DAYS=7
THREEPAP_CHECKER_STALE_AFTER_MINUTES=10
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

- New public and vendor procurement submissions dispatch a durable background
  screening job after their database transaction commits when automatic
  screening is enabled and the integration is configured. The applicant does
  not have to wait for the 3PAP request to finish.
- If automation or its credential is temporarily unavailable, a resubmission
  invalidates the old result and is retained in a visible **waiting for setup**
  state. It becomes recoverable when configuration is restored; the old result
  is never left looking current.
- Each submission has at most one automatic screening job active at a time.
  Temporary failures with a known-safe retry outcome use bounded backoff and
  at most five actual provider calls. An interrupted request that may already
  have reached 3PAP is stopped for manual verification instead of risking a
  duplicate sanctions-search credit.
- A scheduled catch-up scan runs every minute and dispatches a bounded set of
  eligible submissions that were missed because a web process, queue dispatch,
  or worker stopped unexpectedly. Operators can run the same recovery manually:

  ```bash
  php artisan threepap:screen-pending --limit=25
  ```

- Authorised internal users can queue or re-queue one applicant from the
  screening report, or queue all accessible applicants that are missing a
  current result. These actions return immediately and never call 3PAP in the
  browser request.
- Opening a report is read-only. Screening requests use CSRF-protected POST
  actions because every entity consumes one monthly sanctions-search credit.
- A re-run clears any earlier human fit/not-fit decision. The new result must be
  reviewed again.
- Network, authentication, scope, quota, and provider errors are recorded as a
  failed screening rather than crashing the applicant workspace.
- A risk match is evidence for human review, not an automatic exclusion.
- Automatic screening never marks an applicant eligible, ineligible, fit, or
  not fit. An authorised human reviewer must assess the result and record the
  decision.

## Production queue and scheduler

Automatic screening is genuinely asynchronous only when a durable queue worker
is running. Do not set `QUEUE_CONNECTION=sync` or
`THREEPAP_CHECKER_QUEUE_CONNECTION=sync` in production. The synchronous driver
runs the 3PAP request inside the web process and provides no durable retry or
crash recovery.

Run all outstanding migrations before starting the worker. These include the
Laravel queue and failed-job tables as well as the automatic-screening state
used for retry and catch-up decisions:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
```

Run a dedicated worker for the `threepap` queue:

```bash
php artisan queue:work database --queue=threepap --sleep=3 --timeout=60 --tries=25 --max-time=3600
```

The connection and queue arguments must match
`THREEPAP_CHECKER_QUEUE_CONNECTION` and `THREEPAP_CHECKER_QUEUE`. Keep the
queue connection's `retry_after` value greater than the 60-second worker
timeout. Start the worker as the same non-root application account that owns
the Laravel writable directories and can read the deployment environment and
configured CA bundle.

The worker command is long-running and must be managed by Supervisor, systemd,
or an equivalent process manager. Configure it to start on boot, restart after
an unexpected exit, keep at least one process running, and allow more than 60
seconds for a graceful stop. A single 3PAP worker is a safe initial deployment
because it bounds provider concurrency. Supervisor should restart the worker
when its `--max-time=3600` lifetime ends.

Example `/etc/supervisor/conf.d/thinkthankafrica-threepap.conf`:

```ini
[program:thinkthankafrica-threepap]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/html/thinkthankafrica/artisan queue:work database --queue=threepap --sleep=3 --timeout=60 --tries=25 --max-time=3600
directory=/var/www/html/thinkthankafrica
user=www-data
numprocs=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=90
redirect_stderr=true
stdout_logfile=/var/www/html/thinkthankafrica/storage/logs/threepap-worker.log
```

Load or refresh that process after saving the file:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart thinkthankafrica-threepap:*
```

Laravel's scheduler must also run every minute. For example, install this cron
entry for the same application account, adjusting the PHP binary and project
path when necessary:

```cron
* * * * * cd /var/www/html/thinkthankafrica && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

The application schedule invokes the bounded catch-up command every minute.
Only one scheduler should lead that task at a time. The unique-job and
per-applicant locks also need a shared atomic cache. Keep
`THREEPAP_CHECKER_CACHE_STORE=database` (the default) or use a shared Redis
store; do not use a node-local file/array store on a multi-node deployment.

After every deployment or change to the 3PAP token, CA path, queue connection,
or queue name, rebuild cached configuration and restart long-running workers so
they load the new values:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan queue:restart
```

Supervisor or the selected process manager must be running so the gracefully
stopped worker starts again. Verify the deployment with:

```bash
php artisan threepap:verify
php artisan schedule:list
php artisan queue:failed
```

`threepap:verify` does not screen an applicant or consume a sanctions-search
credit. Review failed jobs and application logs during operations. A failed
screening job is diagnostic and its original request may have an ambiguous
outcome; do not blindly run `queue:retry`. After checking 3PAP usage and fixing
the underlying problem, use **Queue 3PAP Re-screening** on the applicant report
to create a fresh, auditable run. The old failed-job record can then be removed
by its numeric ID shown by `php artisan queue:failed`, using
`php artisan queue:forget <failed-job-id>`.

The job performs bounded automatic retries for safe temporary failures and
records exhausted screening state on the applicant. Infrastructure-level jobs
that exhaust the queue delivery limit also appear in `failed_jobs`.
Authentication, missing scope, invalid applicant data, ambiguous timeouts, and
exhausted quota require review or correction; they are not retried in a tight
loop. The scheduled catch-up process remains the safety net for eligible
submissions whose original dispatch was missed.

## Provider contract

The implementation follows the published API base URL, Bearer authentication,
`POST /sanctions/screen`, `POST /sanctions/batch`, and `GET /usage` contracts.
The official batch maximum is 50 entities. Monthly quotas reset on the first day
of each month, and the provider returns HTTP 429 with `LIMIT_REACHED` when the
allowance is exhausted.

Official reference: <https://checker.3pap.africa/developers>
