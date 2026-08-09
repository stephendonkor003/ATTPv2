# ATTP Monitoring, Evaluation and Learning Implementation

**Implementation date:** 9 August 2026  
**Application:** Africa Think Tank Platform PMIS  
**Runtime:** Laravel 12 / PostgreSQL  
**Companion audit:** `docs/ATTP_MEL_AUDIT.md`

## 1. Implementation outcome

The existing M&E module was extended into a controlled ATTP Monitoring, Evaluation and Learning implementation. The work retained the current authentication, Think Tank membership, portfolio, form builder, performance reporting, evidence repository, exports, and audit infrastructure. It added the World Bank Africa Think Tank Platform Project P179804 Results Framework from PAD5316, Indicator Reference Sheets (IRS), controlled targets, planned reporting periods, seven standardized Think Tank reporting instruments, versioned submissions, a Secretariat review workbench, data-quality assurance, approved-only aggregation, PDO calculations, official dashboards, and eleven report classifications.

The principal data-governance rule is enforced in the query layer and not only in the user interface:

> A result is official only when `me_indicator_results.review_status = 'approved'` and `approved_at` is populated. Submitted, returned, rejected, under-review, validated/verified, and draft information cannot contribute to an official dashboard, PDO calculation, or official export.

Legacy performance-report aggregation was also corrected. Only reports in `approved` or `archived` state may contribute to stored aggregate snapshots. The legacy `reviewed` state is no longer treated as approval.

## 2. Architecture

The implementation is additive and uses the existing application boundaries:

- `myb_projects` remains the source of the ATTP programme and three project components.
- `myb_indicators` remains the indicator master and now carries framework, source, value-type, calculation, activation, and effective-date metadata.
- Existing M&E forms, collections, assignments, submissions, answers, performance reports, achievements, disaggregations, and evidence are retained.
- New controlled records hold the framework version, IRS versions, calculation rules, performance thresholds, submission snapshots, review decisions, materialized evidence metadata, and DQA findings.
- `AttpMelFrameworkInstaller` owns the idempotent official-framework installation.
- `AttpMelResultsService` is the single approved-only Results Framework/PDO read model used by the Secretariat and Think Tank dashboards and official exports.
- `MeDataQualityService` performs repeatable DQA checks and persists findings.
- `MeReportingNotificationService` delivers lifecycle and reminder notifications through the application's database notification channel.
- Existing `SystemAuditLog` capture is retained, with the new MEL entities classified under the `me` audit module.

No existing table was dropped or renamed, and no legacy indicator or historical record was deleted.

## 3. Tables, models, and relationships

### Controlled framework

| Table | Model | Purpose |
| --- | --- | --- |
| `me_frameworks` | `MeFramework` | Version, effective dates, approval metadata, current-version marker, PDO statement |
| `myb_indicators` | `Indicator` | Existing indicator master, extended with framework and calculation metadata |
| `me_indicator_reference_sheets` | `MeIndicatorReferenceSheet` | Immutable numbered IRS versions and approval metadata |
| `me_indicator_targets` | `IndicatorTarget` | Project or Think Tank targets, project/reporting year, revision, effective date and approval |
| `me_indicator_calculation_rules` | `MeIndicatorCalculationRule` | Versioned source indicators, qualification filters, calculation keys and deduplication rules |
| `me_performance_thresholds` | `MePerformanceThreshold` | Configurable achievement classifications and display colours |

A framework has indicators and thresholds. Each indicator belongs to a framework, optionally belongs to an ATTP component, and has IRS versions, target revisions, calculation-rule versions, results, achievements, and form-field links. The latest approved IRS and latest applicable approved target revision are selected deterministically.

### Submission governance

| Table | Model | Purpose |
| --- | --- | --- |
| `me_reporting_periods` | `MeReportingPeriod` | Monthly, quarterly, semi-annual, annual or custom periods, open/close lifecycle and deadlines |
| `me_data_submissions` | `MeDataSubmission` | Current submission state and reviewer/verification/approval metadata |
| `me_data_submission_versions` | `MeDataSubmissionVersion` | Immutable schema, answer, evidence and submitter-note snapshot per submission version |
| `me_data_submission_reviews` | `MeDataSubmissionReview` | Immutable workflow transition, comments, reviewer, timestamp and submission version |
| `me_submission_evidence` | `MeSubmissionEvidence` | Evidence context, protected file metadata, checksum and verification state |
| `me_data_quality_findings` | `MeDataQualityFinding` | Persisted warning/error, rule, field context and resolution audit data |
| `me_indicator_results` | `IndicatorResult` | Numeric, percentage, boolean or qualitative/milestone actual and official approval state |

A Think Tank assignment belongs to a collection, form, reporting period, and Think Tank. Its submission has answers, versions, review decisions, indicator results, evidence, and DQA findings. Each materialized result carries its source submission/record identity and optional deduplication key.

### Existing performance-report path

`MePerformanceReport`, `MePerformanceReportIndicatorResult`, `MeIndicatorAchievement`, disaggregation breakdowns, documents, and transitions remain supported. Qualitative/milestone values are stored in `actual_text`; percentages support roll-up numerator and denominator. Report approval synchronizes approved results, while draft editing clears rather than publishes stale official snapshots.

## 4. Official indicator framework

The installer creates or updates framework code `ATTP-RF`, version `World Bank PAD5316`, and uses stable indicator codes. Installation is repeatable and does not create duplicate versions, indicators, targets, rules, thresholds, reporting instruments, periods, collections, or Think Tank assignments. The authoritative references are the [World Bank P179804 project page](https://projects.worldbank.org/en/projects-operations/project-detail/P179804) and [Project Appraisal Document PAD5316](https://documents.worldbank.org/curated/en/099101623140041645/pdf/BOSIB0c74a63d907e081120e77d1999b450.pdf).

The installed official framework contains 18 indicators:

- PDO: `PDO 1`, `PDO 2`, `PDO 3`, `PDO 3-CE`, `PDO 4`.
- Component 1: `INTC1`.
- Component 2: `INTC2.1`, `INTC2.2`, `INTC2.3`, `INTC2.4`, `INTC2.5`, `INTC2.7`, `INTC2.8`, `INTC2.9`, `INTC2.10`, `INTC2.11`.
- Component 3: `INTC3.1`, `INTC3.2`.

`INTC2.6` is intentionally absent because it is not present in the approved PAD results framework. The numbering gap is recorded in framework notes rather than filled with invented content.

The installer creates 72 approved project target records: four Y1/Y2/Y3/end-target records for each indicator, including the qualitative PDO 1 milestones. This includes the PAD's unusual `PDO 3-CE` sequence `20, 10, 15, 20`; it is preserved exactly and can only be changed through a controlled target revision. Supported result values are number, percentage, boolean, and milestone/qualitative. Supported reporting sources are Secretariat, Think Tank, both, and system-calculated.

The World Bank PAD version is installed as the current active framework, with the project approval date (2 November 2023) and closing date (30 August 2028). Any local amendment must be created as a controlled new framework/IRS/target revision rather than overwriting the PAD source.

### Standard Think Tank instruments

The comprehensive seeder publishes seven reusable, evidence-controlled forms for `INTC2.3`, `INTC2.4`, `INTC2.5`, `INTC2.7`, `INTC2.8`, `INTC2.9`, and `INTC2.10`. Each instrument has a result/register section, required classifications/disaggregations, and a narrative/means-of-verification section. Only the principal result field materializes an indicator result, preventing duplicate results from supporting numeric fields.

`INTC2.11` is deliberately not assigned as a Think Tank manual-return form: PAD5316 identifies the annual policy community survey administered by the AUC as its source. Percentage form `INTC2.8` captures and validates numerator and denominator and stores both for weighted consolidation.

The seeder creates planned (not automatically open) semi-annual cycles from the current half-year through the last complete half-year before project close. Every published form is assigned to every active Think Tank. Secretariat staff retain control of review dates and opening each cycle; the seeder never creates historical submissions or invents Think Tank-level target allocations.

## 5. Framework, IRS, target, and period administration

The Secretariat framework workbench provides:

- controlled framework status, effective dates, PDO statement, notes, and current-version selection;
- indicator result area, type, reporting source, aggregation method, evidence requirement, display order, active state, and effective dates;
- numbered IRS versions covering definition, rationale, inclusion/exclusion rules, unit, sources, collection/calculation methods, frequency, disaggregation, means of verification, responsibilities, guidance, approval and effective dates;
- project-level and Think-Tank-level target allocations with baseline, numeric/text target, project/reporting year, revision reason, effective date and approval state;
- versioned system calculation rules and qualifying-record/deduplication configuration;
- reporting periods with reporting year, dates, submission opening, submission deadline, review deadline, instructions, and planned/open/under-review/closed/completed lifecycle.

Think Tank data entry accepts only an eligible Secretariat-configured period. A collection and period must be open, the submission opening date must have passed, and the deadline must not have expired. The server performs these checks independently of the page state.

## 6. Submission and approval workflow

### Think Tank workflow

1. Secretariat publishes a form, opens a collection/reporting period, and assigns a Think Tank.
2. The Think Tank administrator or M&E officer enters indicator-specific values and disaggregation and uploads supporting evidence.
3. Draft changes remain private to that Think Tank and never affect official results.
4. Submission creates an immutable version containing the schema, answers, evidence metadata, notes, author and timestamp.
5. If a submission was returned, the Think Tank corrects the current draft and resubmits it as a new immutable version.

The effective lifecycle is:

`draft -> submitted -> under_review -> verified -> approved`

Correction and terminal branches are:

- `submitted|under_review|verified -> returned -> resubmitted`
- `submitted|under_review|verified -> rejected`

### Secretariat workflow

The review queue can be filtered by search, reporting year, reporting period, component, indicator, Think Tank, country, workflow status, and reviewer. The review page presents the submitted answers, indicator/IRS context, target, previous approved results, evidence, DQA findings, versions, reviewer comments, and decision history together.

Legal transitions are enforced server-side. Return and rejection require comments. A non-administrator submitter cannot review their own submission. Final approval is blocked while an open DQA error exists. Approval synchronizes the child indicator results and is the only transition that sets their `approved_at` value. Every decision creates an immutable `me_data_submission_reviews` row.

Evidence files are served from private storage after portfolio and submission ownership checks. Paths must remain inside the submission's protected directory. Verification metadata records who verified a file, when, its status, notes, checksum, MIME type, and original name.

## 7. Data-quality assurance

DQA is evaluated when a submission is submitted/resubmitted and again during a review decision. Checks include:

- negative values where counts cannot be negative;
- percentage results outside 0–100;
- percentage results without a usable denominator warning;
- duplicate source/deduplication identities;
- missing required evidence;
- dates outside the reporting context;
- IRS-driven gender, country, thematic-area, and other expected disaggregation warnings.

Earlier open findings are marked `superseded` before a new evaluation, preserving history without leaving stale issues active. Reviewers may resolve a finding only with resolution notes; resolver and timestamp are retained. A regenerated blocking error must be corrected in the source data rather than bypassed by a previous resolution.

## 8. Aggregation and official-results rules

`AttpMelResultsService` begins every calculation with `IndicatorResult::approved()`. That scope requires both approved review state and a non-null approval timestamp. The Think Tank dashboard forces the authenticated membership ID into the query; it never trusts a Think Tank ID from the request.

Aggregation behavior is:

- **Number:** sum by default, or average/minimum/maximum/latest according to the controlled indicator method.
- **Percentage:** `sum(numerator) / sum(denominator) * 100` when roll-up values exist; otherwise the approved percentage values are averaged as a compatibility fallback.
- **Boolean:** true when at least one approved qualifying result is true.
- **Milestone/qualitative:** the latest approved `actual_text` value.
- **Period value:** only the selected period/year.
- **Cumulative value:** approved values up to the selected period/year for cumulative indicators.
- **Target:** the latest approved applicable revision, preferring a Think Tank allocation on the Think Tank dashboard and otherwise using the project target.

The service reports baseline, target, period actual, cumulative actual, approved period-over-period trend, achievement percentage, variance, performance classification, approved-record count, evidence count, verified evidence count, female/male beneficiaries, organizations reporting, expected organizations, and reporting completeness.

Deduplication first uses an explicit deduplication key. If absent, it uses the source record type/ID. The last fallback permits one aggregate result per indicator, organization, and period. Where duplicates remain, the latest approved record wins. This prevents a common record from being counted once at Secretariat level and again through a Think Tank report.

## 9. PDO system calculations

System PDOs use versioned `me_indicator_calculation_rules`; calculations are configuration-driven rather than embedded in a dashboard view:

| PDO | Approved source | Qualification |
| --- | --- | --- |
| `PDO 2` | `INTC2.5` | Approved qualifying research-product records |
| `PDO 3` | `INTC2.3` | Approved qualifying policy-engagement records |
| `PDO 3-CE` | `INTC2.3` | Breakdown dimension `citizen_engagement = true` |
| `PDO 4` | `INTC2.5` | Breakdown dimension `lead_researcher_gender = female` |

`PDO 1` is a Secretariat milestone/qualitative indicator and is not reduced to an invented numeric formula. Calculation rule versions have effective dates and can be superseded without rewriting historical results.

## 10. Dashboards and reports

The Secretariat Results Framework dashboard uses the hierarchy `ATTP Project -> PDO/Component -> Indicator -> Think Tank contribution` and supports project year, reporting year, period, component, indicator, Think Tank, country, and thematic-area filters.

The Think Tank MEL dashboard combines its assignment/workflow overview, deadlines and action-required state with its own approved-only target-versus-actual results. Cross-organization access is blocked at the controller/service boundary.

The report catalog contains:

1. ATTP Results Framework Report
2. PDO Performance Report
3. Component Performance Report
4. Indicator Performance Report
5. Think Tank Performance Report
6. Reporting Compliance Report
7. Evidence Verification Report
8. Gender / Disaggregation Report
9. Semi-Annual M&E Report
10. Annual M&E Report
11. Target vs Actual Report

Official reports can be downloaded as Excel, CSV, or landscape PDF. The same filters and approved-only result service feed the screen and all export formats, preventing an export/dashboard discrepancy.

## 11. Permissions and organization isolation

The additive permissions are:

- `me.framework.manage`
- `me.targets.manage`
- `me.submissions.review`
- `me.results.view`
- `me.reports.export`
- `me.dqa.manage`
- existing `me.data_entry.view` and `me.data_entry.manage`

They are installed idempotently and assigned to System Admin and recognized M&E manager/officer role variants without resetting direct user grants. Controller middleware now distinguishes framework mutations, target changes, submission decisions, DQA resolution, official-results viewing, and export access. Existing `me.configuration.manage`/data-entry permissions remain compatibility fallbacks for already-authorized deployments.

Think Tank routes additionally require the Think Tank portal middleware and `think_tank.me.view` or `think_tank.me.submit`. Users must resolve to an active membership and may only read or mutate assignments, submissions, reports, evidence, and dashboard results belonging to that membership.

## 12. Primary routes and UI

| Area | Route |
| --- | --- |
| Framework, IRS, targets, calculations | `/budget/me/framework` |
| Secretariat approved-results dashboard | `/budget/me/results-dashboard` |
| Excel/CSV/PDF official exports | `/budget/me/results-dashboard/export/{format}` |
| Submission review queue | `/budget/me/submission-reviews` |
| Submission review detail | `/budget/me/submission-reviews/{submission}` |
| Existing form/period/collection administration | `/budget/me/data-entry` |
| Think Tank MEL dashboard | `/think-tank/me-dashboard` |
| Think Tank assigned data entry | `/think-tank/me-data` |
| Think Tank performance reports | `/think-tank/me-performance-reports` |

The new pages use the existing Secretariat shell and the separate Think Tank portal shell. Empty states, lifecycle labels, deadlines, target context, audit history, evidence status, and approved-only notices are included. The implementation avoids presenting draft totals as official performance.

## 13. Notifications and scheduled tasks

Database notifications are generated for:

- reporting period opened;
- submitted and resubmitted raw data;
- review started;
- returned, verified, approved, or rejected submission;
- existing performance-report and mission-report lifecycle events;
- upcoming/overdue assignment and report deadlines;
- outstanding corrective actions;
- pending Means of Verification validation.

The scheduler runs `php artisan me:send-reporting-reminders` daily at 07:00 with `withoutOverlapping()`. Reminder logs prevent the same user/subject/event from being notified more than once per day. The server still requires a normal Laravel scheduler worker/cron entry (`php artisan schedule:run` every minute) in the deployment environment.

Lifecycle notifications currently use the application's database notification channel. Email/SMS delivery is not enabled by this change; adding it requires an approved notification policy, recipient preferences, queued workers, and a correctly configured mail/SMS transport.

## 14. Migrations, seeders, and commands

Migrations added for this implementation:

1. `2026_08_08_000006_add_attp_mel_framework.php`
2. `2026_08_08_000007_complete_attp_mel_workflow.php`
3. `2026_08_08_000008_register_attp_mel_permissions.php`
4. `2026_08_08_000009_sync_attp_mel_role_permissions.php`
5. `2026_08_08_000010_add_qualitative_mel_results.php`
6. `2026_08_08_000011_expand_mel_reporting_period_types.php`
7. `2026_08_08_000012_expand_mel_result_period_types.php`
8. `2026_08_09_000002_expand_attp_mel_qualitative_targets.php`

The final two migrations safely expand legacy PostgreSQL/MySQL period constraints so monthly, quarterly, semi-annual, annual, and custom reporting records can be stored.

Framework installation is available through:

```text
php artisan mel:install-attp-framework
```

`AttpMelFrameworkSeeder` installs the official framework and invokes `AttpMelThinkTankReportingSeeder` for dimensions, standardized forms, planned periods, collections, and active Think Tank assignments. It is also included in `DatabaseSeeder` after the ATTP programme and Think Tank membership seeders. Production deployment order is:

```text
php artisan migrate --force
php artisan db:seed --class=AttpMelFrameworkSeeder --force
php artisan optimize:clear
php artisan schedule:list
```

Back up the production database and private evidence storage before deployment. Do not run a destructive refresh/migrate-fresh command.

## 15. Verification and automated tests

The implementation adds `tests/Unit/AttpMelCompletionTest.php` and `tests/Smoke/attp_mel_completion_smoke.php`, and updates legacy MEL smoke fixtures for governed open periods. Coverage includes:

- exact official indicator codes, targets, intentional absence of `INTC2.6`, and PDO rule sources;
- approved-only source selection (submitted and verified records excluded);
- weighted percentage, boolean, milestone, period, cumulative, and latest-target-revision behavior;
- organization-forced dashboard isolation;
- immutable submission/review/evidence/DQA/audit wiring;
- lifecycle, notification, permission, export, and scheduler wiring;
- PostgreSQL reporting-period/result constraint compatibility.

Verification completed on 9 August 2026:

- framework/reporting seeders: repeatable, 1 current framework, 18 official indicators, 72 official target records, 4 PDO calculation rules, and 4 performance thresholds;
- operational reporting seed: 7 published forms, 4 safely planned periods, 28 draft collections, and 364 assignments across 13 active Think Tanks at the verification date;
- transaction-based PostgreSQL aggregation smoke: passed (`ATTP_MEL_COMPLETION_OK`);
- relevant framework, data-entry, performance-reporting and server-readiness smoke tests: passed;
- complete PHPUnit suite: **88 tests passed, 787 assertions**.

At audit time the live database contained no indicator results, raw data submissions, or performance reports, so no historical results required conversion. The three pre-existing/demo indicators and their target were retained.

## 16. Known limitations

- The installed framework is the active World Bank PAD5316 version. Local amendments still require a governed new revision.
- `INTC2.6` is not created because it is absent from the approved PAD results framework.
- `PDO 3-CE` targets are retained exactly as provided despite their non-monotonic sequence.
- Indicator-specific operational detail is captured through versioned dynamic forms, achievements, disaggregation dimensions, evidence, and controlled calculation rules. The implementation does not create one bespoke database table for every indicator. New source-system adapters should be introduced as controlled rule versions.
- Current lifecycle notifications are in-app/database only. Delivery by email or SMS is a separate infrastructure and policy decision.
- Geographic and thematic completeness depends on Think Tanks entering the corresponding governed disaggregation fields.
- Formal audit acceptance should include role-by-role user acceptance testing with representative Secretariat and Think Tank accounts and the final signed Results Framework source document.

## 17. Unresolved governance questions

The following require programme ownership decisions, not invented software defaults:

1. Approve any local operational refinements to the PAD-aligned IRS text without changing the official indicator meaning.
2. If a later World Bank restructuring formally adds `INTC2.6`, introduce it only from that authoritative amendment.
3. Seek World Bank clarification before revising the PAD's non-monotonic `PDO 3-CE` target sequence.
4. Approve the definition of a unique research product and policy engagement for cross-source deduplication.
5. Approve mandatory disaggregations and Means of Verification per indicator.
6. Define whether selected warnings should become blocking DQA errors.
7. Decide whether verified evidence can be invalidated after submission approval and, if so, which controlled reopening workflow applies.
8. Approve email/SMS notification channels, templates, user preferences, escalation recipients, and delivery SLAs.
9. Confirm the reporting calendar, project-year mapping, and reporting-period cut-off rules used for annual and semi-annual reports.
