# Evaluation management reports

Evaluation report pages and PDF exports use a shared, read-only management dataset. The individual evaluator email attachment uses the same PDF layout. Existing report permissions, EOI qualification rules and operational approval workflows remain authoritative.

## Five sections

1. **Summary overview:** applicants, evaluated applicants, evaluator names and assignments, submitted records, calendar turnaround and coverage charts.
2. **Evaluation results and rankings:** complete-panel rankings within the same form, stage and proposal round; grouped bar charts, evaluator profile lines and completion pies. EOI qualification positions come from the qualification service.
3. **Detailed evaluator and section scores:** section comparisons and rankings, criterion scores or categorical decisions, rationale, strengths and weaknesses. Parent sections include their descendants once.
4. **Panel consistency and management insights:** score range, percentage-point spread, population standard deviation, incomplete panels and data validation findings. Differences prompt review; they do not establish bias.
5. **Governance, audit trail and next steps:** calculation rules, limitations, decision steps, source submission IDs, revisions and submission timestamps.

## Interpretation

- Only finalized evaluations are reported. The latest finalized revision is counted per applicant, evaluator, form, stage and proposal round.
- Technical proposal targets use the existing eligibility resolver. Procurement-wide applicant totals and the eligible roster for a particular stage can differ.
- Numeric panel means give equal weight to submitted evaluators. A final rank requires every assigned evaluator and complete criterion values that reconcile with the saved total. Actual zero is a valid score; missing values remain missing. Equal means share competition ranks (1, 1, 3).
- Forms with different criteria, technical/financial phases and proposal rounds retain separate rankings. EOI categorical qualification precedence and shortlist progression remain separate from numeric merit scoring. Goods Yes/No decisions do not acquire invented numeric merit rankings.
- Charts place full applicant names on the horizontal axis and scores on the vertical axis. Long names wrap without truncation. Colours identify evaluators. Lines compare applicants and do not imply time trends. Pies represent counts, not award probabilities.
- Existing records have assignment and submission timestamps, but do not record active working time. Reports label calendar turnaround explicitly and show active time as **Not recorded**. Historical effort cannot be reconstructed from these timestamps.
- Individual reports describe one evaluator record and do not assign procurement-wide ranks. Anonymised PDFs replace applicant labels before chart generation and withhold narrative comments, strengths and weaknesses.
- Consolidated reports retain procurement references on every result group, section and source record. Overlapping procurement durations are not added together as working time.

The structure supports transparent management review and traceable evidence. It is not a certification against a universal international standard. The applicable solicitation and approved procurement procedures govern the actual decision. The rationale and panel-variation presentation is informed by the [World Bank consultant selection guidelines, section 2.22](https://documents1.worldbank.org/curated/en/796061468126898713/pdf/956640PUB0Box3010Revised0July102014.pdf).

## Exports and verification

Browser and PDF charts are generated on the server as SVG, so they do not depend on Chart.js loading or JavaScript execution. Large applicant groups are split into charts of at most eight applicants with a common scale. PDF tables repeat their headings across pages and retain all criterion-level records.

On the web, hover, keyboard focus or a tap opens chart details with the applicant, evaluator and recorded score. Pie details show the category, count and percentage. The chart data disclosure remains available without JavaScript; PDF and Excel retain the same chart values and applicant names.

## Evaluator rework from a report

Users with the existing evaluation management and panel-view permissions can request rework from the panel at the top of a web report. Expand an evaluator, choose one or more available submitted evaluations (up to 50 per request), and enter correction instructions of 10 to 5,000 characters. A single email lists the selected records and reason. Each record retains its own original-score snapshot and rework audit history. Pending requests and workflow restrictions are visible beside the affected evaluator.

Requests use the existing portfolio, assignment, revision and downstream-decision safeguards. A batch is atomic: if any selected record is no longer eligible, none are reopened. Where an administrator is allowed to override an existing technical-proposal-round lock, the form requires explicit acknowledgement and the override is audited. Returned records leave finalized reporting until the evaluator submits a new revision. The request controls are omitted from print and PDF exports.

Grouped notifications use `EVALUATION_REWORK_MAILER` when set, otherwise the application's default mailer. Set it to `smtp` to use the configured SMTP connection when the rest of a local application uses the log mailer. Notification delivery runs after the batch commits, and any failure is recorded against every affected rework request.

Large Services, procurement and consolidated PDFs render section comparisons and batches of six evaluator records separately, then combine them into one document with continuous page numbering and five section bookmarks. This avoids repeatedly laying out thousands of narrative blocks in a single DOM. Chart height is bounded to fit landscape pages. `EvaluationReportPdf` allows up to 512 MB and 180 seconds for the export when the host limits are lower; higher or unlimited host settings are preserved. Install the committed Composer lockfile on other environments to include FPDF and FPDI. No command-line PDF program or external rendering service is required.

Excel and CSV use the same five-section report values; EOI exports retain their detailed qualification, proposal and communication registers as appendices. Excel also includes editable native bar, line and pie charts with visible source tables. Missing observations stay blank and actual zeroes stay numeric zeroes. CSV contains the underlying values and cannot contain graphical objects.

Read-only smoke checks:

```powershell
php tests/Smoke/evaluation_report_charts_smoke.php
php tests/Smoke/evaluation_chart_interaction_smoke.php
php tests/Smoke/evaluation_management_report_smoke.php
php tests/Smoke/evaluation_workbook_charts_smoke.php
php tests/Smoke/evaluation_management_pdf_smoke.php
php artisan test --filter="EvaluationReportReliabilityTest|EvaluationReportIndexPresentationTest"
```

The management smoke uses in-memory fixtures for ties, zero versus missing values, panel completion, revisions, section hierarchy, stage/round isolation and anonymisation, then compares existing real rankings and renders report views. It does not submit evaluations or send email.

`php tests/Smoke/evaluation_report_batch_rework_smoke.php` exercises batch authorization, reason validation, current-record selection, atomic rollback, audit snapshots, grouped notification and pending-result exclusion using temporary database fixtures inside a rolled-back transaction and fake mail. The live browser interaction check is documented in `tests/Browser/README.md`; it blocks rework submissions and sends no real evaluator email.
