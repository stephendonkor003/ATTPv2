# ATTP Monitoring, Evaluation and Learning Module Audit

**Audit date:** 8 August 2026  
**Application:** Africa Think Tank Platform PMIS (Laravel 12 / PostgreSQL)  
**Scope:** Existing Secretariat PMIS and Think Tank Portal MEL/M&E implementation

## Executive summary

The application already contains a substantial M&E foundation and must be extended rather than replaced. It has configurable indicators and targets, reporting periods, dynamic data-entry forms, Think Tank assignments and submissions, performance reports, document/evidence versioning, disaggregation, review transitions, notifications, dashboards, exports, permissions, and system audit logs.

The current implementation is not yet a complete implementation of the official ATTP Results Framework. The live database contains three non-official/demo indicators and no official PDO or intermediate-result indicator set. More importantly, the current aggregation service recalculates cumulative values from performance-report rows without restricting the source reports/results to approved records. Draft data can therefore affect stored cumulative snapshots. Some reporting code also treats the legacy `reviewed` status as equivalent to `approved`. These are critical correctness defects because official ATTP results must contain only Secretariat-approved information.

The recommended approach is additive and production-safe: retain the existing tables and workflows, add framework and IRS metadata, enrich periods/targets/submission review history, install the official framework through an idempotent command/seeder, centralize approved-only calculations in services, and expose the resulting hierarchy through existing portals and export infrastructure.

## 1. Current architecture

- Laravel 12 application using Blade views, Eloquent models, route middleware, queued notifications/jobs, console scheduling, and PostgreSQL.
- A shared authentication system serves Secretariat users and Think Tank users. Think Tank users are linked to `attp_consortium_think_tanks` by `users.think_tank_member_id` and constrained by a Think Tank access level.
- Secretariat M&E routes are primarily under the existing `/budget` route group. Think Tank M&E routes are under `/think-tank` and use the existing Think Tank shell.
- M&E configuration is built around `myb_indicators`; existing ATTP project components are represented by `myb_projects` and should remain the component source of truth.
- Dynamic data collection is built around reporting periods, forms, sections, fields, collections, organization assignments, submissions, and answers.
- Narrative/indicator reporting is built around performance reports, report indicator results, achievements, documents, and immutable lifecycle transitions.
- Evidence uses the existing repository/folder/document/version/link architecture with private storage, validation, checksums, and polymorphic links.
- Authorization uses the application's custom roles, permissions, middleware, portfolio scoping, and Think Tank access-level boundary checks.
- Generic Eloquent changes are captured by `SystemAuditLog` from `AppServiceProvider`; workflow transitions provide additional domain history.

## 2. Existing M&E functionality

The repository currently implements:

- Indicator CRUD, hierarchy, definitions, methodologies, baseline, units, results level, aggregation method, reporting frequency, source, responsible user, and means-of-verification folder.
- Period targets and indicator result records with numerator/denominator support.
- Configurable reporting periods and data collections.
- Dynamic forms with sections, typed fields, validation rules, and indicator links.
- Think Tank assignments, draft saving, submission, correction after return, revision count, and organization isolation.
- Think Tank performance reports with narratives, indicator results, achievements, disaggregation, evidence, and lifecycle actions.
- Secretariat performance-report review, verification, approval, return for correction, archiving, dashboard filters, and consolidated exports.
- Versioned evidence repository, evidence validation, document links, and private downloads.
- Reporting reminders, corrective-action reminders, and MOV-validation reminders.
- Consolidated Excel/PDF reporting and an operational workflow dashboard.
- Unit/smoke coverage for data entry, performance reporting, framework basics, weighted percentage aggregation, and organization overlap suppression.

## 3. Existing database tables related to M&E

### Core results framework

- `myb_indicators`
- `me_indicator_targets`
- `me_indicator_results`
- `me_indicator_result_breakdowns`
- `me_indicator_definitions`
- `me_indicator_methodologies`
- `me_indicator_matrix_versions`

### Reporting configuration and submission

- `me_reporting_periods`
- `me_data_entry_forms`
- `me_data_entry_form_sections`
- `me_data_entry_fields`
- `me_data_entry_field_options`
- `me_data_entry_field_indicator_links`
- `me_data_collections`
- `me_data_collection_assignments`
- `me_data_submissions`
- `me_data_submission_answers`
- `me_data_submission_documents`

### Performance reporting and supporting detail

- `me_performance_reports`
- `me_performance_report_indicator_results`
- `me_performance_report_documents`
- `me_performance_report_transitions`
- `me_indicator_achievements`
- `me_indicator_achievement_disaggregations`
- `me_disaggregation_dimensions`
- `me_disaggregation_options`

### Evidence, governance, and related MEL records

- `me_repository_folders`
- `me_knowledge_evidence_items`
- `me_knowledge_evidence_versions`
- `me_knowledge_evidence_links`
- `me_focal_contacts`
- `me_mission_reports`
- `me_survey_integrations`
- `me_data_source_integrations`
- `system_audit_logs`

### Existing operational sources relevant to system calculations

- `attp_think_tank_research_outputs`
- `attp_activity_reports`
- `attp_report_evidence`
- `attp_consortium_think_tanks`
- existing workplan/activity/project/portfolio records

No table needs to be dropped or renamed. Existing tables provide most of the required structure.

## 4. Existing relationships

- An indicator may belong polymorphically to an existing program/project structure, may have a parent indicator, and may point to a project component.
- Indicators have targets, results, definitions/methodologies, form-field links, report indicator results, achievements, and MOV repository folders.
- Reporting periods belong to a portfolio and are used by collections.
- A published form has sections/fields and is opened through a data collection for a reporting period.
- Collections have Think Tank assignments; assignments have a single current submission and may have performance reports.
- Data submissions own answers and documents and link generated `me_indicator_results` records.
- Performance reports belong to an assignment/form/period context, a Think Tank or the Secretariat, and a project component. They own indicator result rows, achievements, documents, and transitions.
- Evidence items have immutable versions and polymorphic links to M&E owners.
- Think Tank users resolve their organization through the existing consortium membership relationship.

## 5. Current Think Tank submission workflow

### Raw data-entry workflow

1. Secretariat creates a form, period, collection, and Think Tank assignment.
2. A Think Tank M&E user opens an assigned collection.
3. Draft and returned submissions may be edited while the collection/period is open.
4. The user enters dynamic form answers, uploads supporting documents, saves a draft, and submits.
5. Submission increments its revision on resubmission and creates/updates linked indicator-result records in `submitted` review state.

Strengths include strict organization scoping, open-window enforcement, private file downloads, upload validation, and schema snapshots. Gaps are the lack of a complete Secretariat raw-submission review UI, incomplete lifecycle synchronization between raw submissions and their indicator results, no immutable answer-version history, and no submission-received notification for raw submissions.

### Performance-report workflow

Think Tanks can create organization-owned performance reports, enter narratives and indicator achievements, attach evidence, submit, receive a return decision, correct, and resubmit. Report creation currently allows a user-selected frequency/year/label and can create a period implicitly. This bypasses the requirement that the Secretariat explicitly configure and open the reporting period.

## 6. Current Secretariat workflow

- Secretariat users can configure indicators, targets, forms, periods, collections, assignments, evidence, and performance reports subject to permissions/portfolio scope.
- Performance-report review provides return, verify, approve, and archive actions and records lifecycle transitions/comments.
- Individual indicator results can be validated/approved, but this path is not a complete review experience for a Think Tank data submission and does not consistently synchronize the parent submission.
- Return currently maps a performance report back to `draft`; the transition preserves the decision, but the status itself cannot distinguish a new draft from a returned report without querying transitions.
- There is no explicit `start review` action, rejected state, dedicated raw-submission review history, or one-screen presentation of IRS, target, prior approvals, evidence, details, comments, and decisions.

## 7. Current permissions

Configured permission names include:

- `me.configuration.view`, `me.configuration.manage`
- `me.data_entry.view`, `me.data_entry.manage`
- `me.performance_reports.view`, `me.performance_reports.review`, `me.performance_reports.archive`
- M&E mission-report and reporting-notification permissions
- `think_tank.me.view`, `think_tank.me.submit`
- `think_tank.me.reports.view`, `think_tank.me.reports.manage`, `think_tank.me.reports.submit`
- `think_tank.me.notifications.view`

Think Tank access levels correctly restrict M&E pages to Think Tank administrators and M&E officers. System Administrator has broad access and M&E Manager has the expected configuration/review access. Some legacy roles (including evaluator-like roles) have overly broad M&E configuration grants in existing role data and should be normalized safely through the existing seeder without removing intentional direct-user overrides.

The live database did not contain the seeded `me.data_entry.*` permission rows even though the permission and role seeders define them. The implementation must use an idempotent permission backfill so deployed databases receive the missing permissions without resetting role data.

## 8. Current reporting and dashboard functionality

- The performance-report dashboard reports workflow stage, timeliness, completeness, reports by Think Tank/component/period, and supports geographic and demographic filters.
- Consolidated M&E reports support Excel and PDF exports.
- Indicator reports support Excel/PDF output.
- Think Tank pages show their own reports, lifecycle totals, deadlines, evidence counts, and notifications.

The dashboard is primarily a workflow/compliance dashboard. It does not yet present the official hierarchy `Project > PDO/Component > Indicator > Think Tank` with baseline, target, approved actual, cumulative actual, achievement, variance, trend, and reporting completeness. The eleven named ATTP report classifications are not all available as explicit views/exports.

## 9. Current compliance with the required ATTP MEL framework

- Existing authentication and Think Tank association can be reused; no second login is required.
- Existing project components map cleanly to the three ATTP components:
  - `PROG00001-01` — Component 1
  - `PROG00001-02` — Component 2
  - `PROG00001-03` — Component 3
- Indicators already support PDO vs intermediate-results classification and component linkage.
- Target/result records, numeric numerator/denominator fields, and aggregation methods exist.
- Dynamic forms already permit indicator-specific data capture rather than only `actual_value`.
- Achievements and flexible disaggregation cover many event/research/training fields.
- Evidence versioning, private storage, links, validation, and checksums exceed the basic file-upload requirement.
- Think Tank organization isolation and performance-report lifecycle are substantially in place.
- Existing consolidation already suppresses overlapping organization contributions and supports weighted percentages when numerator/denominator are supplied.
- Generic audit logs and report transitions provide a foundation for complete traceability.

## 10. Missing functionality

- A versioned official ATTP framework record and the official PDO/INTC indicator set.
- Normalized, approvable Indicator Reference Sheets.
- Configurable reporting source (`SECRETARIAT`, `THINK_TANK`, `BOTH`, `SYSTEM_CALCULATED`).
- Framework version/effective dates/display order/active state/calculation keys on indicators.
- Project-wide vs Think-Tank target allocations and immutable target revisions.
- Period opening, submission deadline, review deadline, semi-annual periods, and the requested lifecycle statuses.
- Explicit raw-submission review/history/version tables and a Secretariat review workbench.
- Explicit under-review, resubmitted, and rejected lifecycle support.
- Submission/evidence metadata that directly identifies indicator, period, Think Tank, evidence type, and verification state.
- Configurable PDO calculation rules and qualifying-record deduplication.
- Official target-vs-actual Results Framework dashboard and the complete named report catalog.
- DQA issue/warning persistence and reviewer resolution.
- Period-open and raw-submission lifecycle notifications.
- Scheduled execution of the existing M&E reporting reminder command.

## 11. Incorrectly implemented behavior

1. **Critical:** `IndicatorAggregationService::recalculate()` aggregates all performance-report indicator rows, including drafts and unapproved records. It is also called while drafts are edited.
2. `reviewed` is treated as an approved state by `MePerformanceReport::isApproved()`, the dashboard, and consolidated-report queries. Review is not approval.
3. Stored cumulative snapshots are not reliably recalculated after all review transitions, so approval/return/rejection can leave stale official figures.
4. Performance-report creation may implicitly create a reporting period and accepts arbitrary period attributes instead of requiring an eligible open Secretariat period.
5. Raw data-submission review state and linked indicator-result review state can diverge.
6. Raw submissions overwrite current answers; revision count/schema snapshot are present, but immutable answer history is absent.
7. Reviewer notes can be overwritten on current records even where transition history preserves only part of the context.
8. The richer reminder command exists but is not registered in the scheduler.
9. Generic MEL audit entries are classified under the `system` module because MEL model mappings are absent.

## 12. Functionality to retain

- Existing tables, primary keys, routes, controllers, and working UI paths.
- `myb_projects` for ATTP components and `myb_indicators` for indicators.
- Existing form/collection/assignment/submission workflow.
- Existing performance reports, achievements, breakdowns, and transitions.
- Existing repository/evidence architecture and private storage controls.
- Existing authentication, Think Tank membership resolution, portfolio scoping, middleware, and permission framework.
- Existing Excel/PDF export packages and visual conventions.
- Existing aggregation-method configuration, numerator/denominator support, and overlap suppression, after approval filtering is enforced.
- Existing generic system audit trail.

## 13. Required modifications

- Add framework/IRS/reporting-source/calculation metadata without changing existing indicator identifiers.
- Correct all official aggregation entry points to query approved records only and recalculate on approval-state changes.
- Preserve legacy statuses for display/history but stop treating `reviewed` as official approval.
- Enrich periods and enforce open-period selection in the Think Tank portal.
- Add immutable submission versions, review decisions, target revisions, DQA findings, and evidence verification metadata.
- Provide explicit review actions and synchronize submissions, indicator results, reports, notifications, and audit history transactionally.
- Add an idempotent official-framework installer that updates by stable framework/code keys and never truncates existing indicators.
- Add framework and target administration, official performance dashboard, report catalog, and exports using existing layouts/services.
- Schedule reminders and add lifecycle notifications.
- Extend audit module classification to MEL models.

## 14. Proposed migrations

All migrations will be additive and guarded with `Schema::hasTable`/`hasColumn` where appropriate.

1. Create `me_frameworks` for controlled framework versions and effective dates.
2. Extend `myb_indicators` with framework, result area, value/target type, reporting source, cumulative flag, calculation key, active/display fields, and effective dates.
3. Create `me_indicator_reference_sheets` with normalized guidance, ownership, version, and approval metadata.
4. Extend `me_indicator_targets` for framework, scope, Think Tank allocation, revision/reason/effective/approval and replace the overly restrictive legacy uniqueness rule safely.
5. Extend `me_reporting_periods` with reporting year, submission/review dates, semi-annual support, and controlled status metadata while retaining legacy compatibility.
6. Extend submissions with explicit lifecycle/review fields; create immutable `me_data_submission_versions` and `me_data_submission_reviews`.
7. Extend evidence links/records with evidence type and verification context where the existing polymorphic repository cannot express it directly.
8. Create `me_indicator_calculation_rules` for configurable PDO/derived calculations.
9. Create `me_data_quality_findings` for warnings/errors and resolution history.

No migration will seed volatile production content, delete old data, or invent `INTC2.6`.

## 15. Proposed implementation plan

1. Write and approve this audit boundary before schema changes.
2. Add production-safe schema extensions and Eloquent relationships.
3. Add an idempotent ATTP framework installer containing the exact supplied PDO/INTC definitions and targets.
4. Implement framework/IRS/target/period administration with existing permissions.
5. Enforce Secretariat-open periods and indicator reporting-source relevance for Think Tank entry.
6. Add immutable submission versions and a synchronized Secretariat review workflow.
7. Enrich evidence metadata and verification while retaining existing storage/versioning.
8. Correct approved-only aggregation and implement period/cumulative/boolean/weighted percentage behavior.
9. Add configurable PDO calculation services and deduplication keys.
10. Add official Secretariat and Think Tank dashboards plus named report/export classifications.
11. Add DQA validation/warnings, notifications, permission backfills, scheduling, and MEL-specific audit classification.
12. Add automated coverage for isolation, workflow, evidence, targets, aggregation, PDO calculations, and unauthorized access; run regression tests.
13. Produce `docs/ATTP_MEL_IMPLEMENTATION.md` and update this audit with a before/after status.

## Live-data observations and compatibility notes

- The live database contained 3 existing indicators, 1 target, 0 indicator results, 0 data submissions, and 0 performance reports at audit time. The existing indicator rows are preserved.
- Fourteen Think Tank membership rows exist, including one inactive duplicate ERF record. All organization aggregation must use stable membership IDs and active membership rules; the duplicate must not be deleted automatically.
- No `INTC2.6` indicator exists in the current database. The official installer will intentionally leave that code absent and flag the numbering gap in framework notes.
- The official draft contains an unusual PDO 3 citizen-engagement target sequence (20, 10, 15, 20). It will be stored exactly as supplied and may only be changed through a controlled framework/target revision.

## Post-Implementation Status

Implementation and regression verification were completed on 8 August 2026. Detailed architecture, deployment, workflow and testing information is recorded in `docs/ATTP_MEL_IMPLEMENTATION.md`.

### Before

- Partial configurable M&E implementation with no installed official ATTP Results Framework.
- Draft/unapproved performance data can affect cumulative snapshots.
- Operational workflow dashboard but no official approved-only Results Framework dashboard.
- Partial review history and period governance.

### After

- Installed an idempotent controlled ATTP Results Framework containing 18 supplied indicators, 68 supplied targets, 4 PDO calculation rules and 4 performance classifications. Existing indicators and targets were preserved, and `INTC2.6` was intentionally not invented.
- Added framework/version, IRS, reporting source, target revision/allocation, calculation rule and effective-date governance.
- Added controlled monthly, quarterly, semi-annual, annual and custom periods with open/close lifecycle and submission/review deadlines.
- Added immutable raw-submission versions, review transitions, protected evidence metadata, DQA findings and a filterable Secretariat review workbench.
- Enforced legal `draft -> submitted/resubmitted -> under review -> verified -> approved` transitions, mandatory correction/rejection comments, self-review prevention and blocking-error resolution before approval.
- Corrected all official aggregation paths: only results with final Secretariat approval and an approval timestamp contribute; legacy `reviewed` and current `verified` states are explicitly excluded.
- Added numeric, weighted percentage, boolean and milestone aggregation; period/cumulative views; deterministic target revisions; configurable PDO qualification/deduplication; and Think Tank isolation.
- Added approved-only Secretariat and Think Tank dashboards, eleven report classifications, and consistent Excel/CSV/PDF exports.
- Added granular MEL permissions, database lifecycle notifications, daily deduplicated reminders, and MEL-specific generic audit classification.
- Applied all additive migrations and ran the official installer against PostgreSQL. No legacy result conversion was necessary because the audited database had no existing result/submission/report rows.
- Passed the MEL framework/data-entry/performance/server-readiness smoke coverage and the complete application PHPUnit suite: **88 tests, 787 assertions**.
