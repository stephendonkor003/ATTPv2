# External procurement submissions import

This runbook imports the legacy PDF folders into the procurement whose title is exactly:

> Selection of a Consulting Firm to conduct a Feasibility study for the Endowment Fund and Designing a Resource Mobilization Strategy for the Africa Think Tank Platform Endowment Fund

The importer is intentionally not part of `DatabaseSeeder`. Run only the explicit seeder `Database\Seeders\ExternalProcurementSubmissionsSeeder` after taking a database and private-storage backup.

## Source layout

Without an override, the seeder resolves exactly one of these directories:

1. `storage/app/private/submissions`
2. `storage/app/private/Submissions`

If both directories exist, or neither exists, resolution fails; set `EXTERNAL_PROCUREMENT_SUBMISSIONS_PATH` explicitly. The source must have one immediate directory per external applicant and one or more PDF files directly inside it:

```text
<source-root>/
  <applicant-folder>/
    document-1.pdf
    document-2.pdf
```

Do not add loose files at the source root, ZIP files, or non-PDF documents. The applicant-folder label is part of the import identity; do not rename folders between runs.

## Imported accounts and documents

- The seeder generates `Str::slug(<folder name>)@africathinktank.africa`; for example, a folder named `Example Firm` produces `example-firm@africathinktank.africa`. A folder whose slug cannot fit a valid email address is rejected instead of being silently changed.
- There is deliberately no numeric suffix or silent fallback. An empty or duplicate folder slug or duplicate derived email aborts the complete import. An existing email is reusable only when its user, deterministic submission, exact values, package checksum, and import audit prove that it came from an earlier identical run; every other user or discussion-participant collision aborts before writes.
- Placeholder addresses are internal import identifiers, not verified contact addresses. Do not send credentials or operational email to them.
- Generated users are vendor accounts created disabled. The import does not activate them or give the legacy applicant login access.
- The placeholder domain is fixed by this migration and cannot be overridden on a live run.
- A folder containing one PDF is copied as a PDF. PDFs are sorted; when a folder contains multiple PDFs, they are packaged into one deterministic ZIP so the procurement form retains every source document in a single file value. ZIP entries preserve applicant-relative names, and PHP's `ZipArchive` extension must be available.
- Generated private artifacts use `procurement_submissions/external-imports/<procurement UUID>/<full package SHA-256>.pdf|zip`. Source names and checksums are recorded in import audit metadata, verified on rerun, and—for ZIP packages—also written to `_external-import-manifest.json`. Source files are never moved or modified.

The importer validates the complete source inventory and all database identity collisions before importing. Every document must be a readable, non-empty regular `.pdf` file no larger than 20 MB, with a PDF signature in its header. It rejects nested directories, symbolic links, non-PDF files, case-insensitive duplicate names, more than 200 MB per applicant, more than 500 applicants or 5,000 PDFs, and batches larger than 2 GB. Renaming a corrupt file to `.pdf` does not satisfy validation.

The target must resolve to exactly one procurement with the title above and exactly one active, approved submission form. That form must contain `official_name` (`text`), `official_email` (`email`), and `submit_eoi` (`file`). Any existing non-import submission on the target procurement blocks the import.

The selected target currently has reference `ET-AUC-526435-CS-LCS`, while some source filenames carry the earlier reference `ET-AUC-494958-CS-QCBS`. This import deliberately follows the exact procurement title mandated for this migration and records the target reference in each audit entry; it never chooses a procurement from a filename.

Approved exclusion: `Africa Corporate Advisors/19_Submission - EOI Africa Think Tank Resource Mobilisation ver 15 May 2026 Final.pdf` is only one byte and fails PDF validation. The applicant was explicitly excluded on 2026-08-16 by moving the entire folder, without deleting it, to `storage/app/private/external-procurement-submissions-excluded/Africa Corporate Advisors`. Repeat that recoverable quarantine step on the live server before its dry-run. The seeder does not silently skip invalid applicants and will fail if this folder remains under the configured source root.

## 1. Back up and dry-run

Confirm the exact procurement exists once, its active approved submission form is correct, the source is mounted read-only if practical, and a database plus `storage/app/private` backup is restorable.

Linux/macOS shell:

```bash
EXTERNAL_PROCUREMENT_IMPORT_DRY_RUN=true \
php artisan db:seed \
  --class='Database\Seeders\ExternalProcurementSubmissionsSeeder' \
  --force
```

PowerShell:

```powershell
$env:EXTERNAL_PROCUREMENT_IMPORT_DRY_RUN = 'true'
try {
    php artisan db:seed `
        --class='Database\Seeders\ExternalProcurementSubmissionsSeeder' `
        --force
} finally {
    Remove-Item Env:\EXTERNAL_PROCUREMENT_IMPORT_DRY_RUN -ErrorAction SilentlyContinue
}
```

Set `EXTERNAL_PROCUREMENT_SUBMISSIONS_PATH` in the same process when a path override is required. Review the reported target, folder/file counts, generated identities, packaging plan, and all validation errors. A dry run must not create database rows or stored import artifacts.

## 2. Run the import

Dry-run is the safe default. The write run requires an explicit `EXTERNAL_PROCUREMENT_IMPORT_DRY_RUN=false` in the same command process:

```bash
EXTERNAL_PROCUREMENT_IMPORT_DRY_RUN=false \
php artisan db:seed \
  --class='Database\Seeders\ExternalProcurementSubmissionsSeeder' \
  --force
```

PowerShell:

```powershell
$env:EXTERNAL_PROCUREMENT_IMPORT_DRY_RUN = 'false'
try {
    php artisan db:seed `
        --class='Database\Seeders\ExternalProcurementSubmissionsSeeder' `
        --force
} finally {
    Remove-Item Env:\EXTERNAL_PROCUREMENT_IMPORT_DRY_RUN -ErrorAction SilentlyContinue
}
```

Stop if the command reports a different target, an ambiguous target, a validation failure, or a non-zero exit code. Do not weaken PDF validation to make the run pass.

## Idempotent reruns

Each submission code is deterministic: `PROC-EXT-` followed by the first 12 uppercase characters of the SHA-256 hash of `<procurement UUID>|<placeholder email>`. Rerunning with the same target, folder names, files, and seeder version verifies the prior user, submission values, audit provenance, manifest, and target package, then reuses the exact records and files. It does not overwrite conflicting values or packages. Any source drift, unrelated/partial target data, or identity collision aborts instead of silently replacing data or creating a duplicate.

Always perform another dry run first. Renaming a folder changes the deterministic identity and will be treated as a different or conflicting import.

An idempotent rerun is not a rollback and should not be used to undo reviewed submissions or downstream evaluation work.

## Post-import verification

For the approved import scope, expect 20 applicant folders and 52 PDFs: 14 single-PDF submissions and six multi-file ZIP packages. Verify:

- all imported submissions belong to the exact procurement title above and its intended form;
- there is one submission per source applicant folder, with no duplicate submission identities;
- all generated vendor accounts are disabled and use `@africathinktank.africa`;
- every stored PDF downloads and opens, and each generated ZIP contains the expected applicant-relative source paths plus `_external-import-manifest.json`;
- the procurement UI shows the imported submissions without changing the target procurement's publication or evaluation state; and
- no applicant credential or notification email was queued for placeholder addresses.

Record the command output, source inventory checksum/counts, deployed commit, operator, and completion time with the change ticket.

## Rollback caveats

There is no general `down` operation for a successful seeder run. During an ordinary failed run, all database writes are enclosed in one transaction and the importer deletes only temporary/final artifacts created by that run; it never removes source or pre-existing target paths. A process kill, host failure, or later manual cleanup can still leave database/storage state that needs inspection, and deleting submissions after reviewers start work can cascade into screening, evaluation, or audit records.

The preferred rollback is restoration of the coordinated pre-import database and private-storage backups. If restoration is impossible, prepare and review a targeted cleanup using the import's deterministic markers: remove only its form-submission values, submissions, generated storage artifacts, and placeholder users that have no other references. Never delete the target procurement or the legacy source folders. Take another backup before any cleanup and repeat the post-import checks afterward.
