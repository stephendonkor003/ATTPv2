# FY2026 think-tank procurement Excel migration

This runbook covers the audited migration of the four FY2026 Excel workbooks into the procurement plans of the think tanks named in each activity description. It is intentionally separate from `DatabaseSeeder` and from the older generic workbook import command.

The authoritative routing field is the think-tank token in **Activity Reference No. / Description**. A workbook directory or filename is only source context. For example, `/ Bride -PCNS` resolves to the Bridge Africa member **Policy Center for the New South (PCNS)**. Records found in a CACEPS workbook may therefore correctly belong to Bridge Africa or RAISED Africa.

## Audited source files

The checked-in copies under `database/data/procurement` are the deployment inputs. Their hashes are pinned in `database/data/think_tank_procurement_workbooks.php`; the migration must stop before database writes if a file is missing or its SHA-256 differs.

| Declared source band | Deployment source | Bytes | SHA-256 |
| --- | --- | ---: | --- |
| Above USD 10,000 | `database/data/procurement/above_10k/bridge edit.xlsx` | 29,894 | `8df0633d4f541bbba5c20076e0f7e57aedd30ab4f4de754575f8caa9ed2f54ed` |
| Above USD 10,000 | `database/data/procurement/above_10k/CACEPS Edit.xlsx` | 31,746 | `fb832c1d7cd9ea8bcb569e6db342056189f1e0cc8cb9a03659b1e845ab0f46d9` |
| Below USD 10,000 | `database/data/procurement/below_10k/Bridge Less than 10 K revised sent to bank .xlsx` | 53,964 | `94d4af0303b25a00077aa663252fb29240be0d5c3ba60f1ffae3d53fc20b437f` |
| Below USD 10,000 | `database/data/procurement/below_10k/CACEPS-Procurement-Plan-below10k-revised 01 september 2026.xlsx` | 100,806 | `b017879bf9e79fcf691d1d7678f762b1f26aa633021105ca35a55a6033c456a3` |

The original files under `public/procurement_data` remain untouched and are ignored by Git. Apache access to that legacy directory is denied in `public/.htaccess`; on Nginx or another web server, keep the ignored directory out of the deployed public document root or add the equivalent deny rule. The deployment copies are byte-for-byte equivalents of the four valid sources. The temporary Excel lock file `~$bridge edit.xlsx` is not a source and must never be imported.

Do not edit either copy in place to correct spelling, references, dates, categories, or reviewer comments. Corrections belong in the audited manifest so the original cell value remains available in the raw source payload.

## Reconciled population

The audit found 123 physical activity rows worth USD 1,964,271.38. Three repeated physical rows worth USD 44,100.00 reduce this to 120 owner/reference identities. Three unique activities worth USD 115,213.00 are hard exclusions, leaving **117 mapped procurement items worth USD 1,804,958.38**.

The operational threshold is based on the audited amount, not the source folder:

| Seeder band | Rule | Items | Amount (USD) |
| --- | --- | ---: | ---: |
| Below USD 10,000 | amount `< 10,000.00` | 66 | 238,573.38 |
| At or above USD 10,000 | amount `>= 10,000.00` | 51 | 1,566,385.00 |
| **Total** |  | **117** | **1,804,958.38** |

One AFIDEP row, `ET-AUC-027-CS-INDV`, is physically in the below-10K workbook but is exactly USD 10,000.00. It belongs to the at-or-above band. The above-band total therefore comprises 50 mapped items from the two above workbooks plus this one routed item.

### Distribution by tenant space

| Consortium | Think tank | Below 10K | At/above 10K | Total items | Total amount (USD) |
| --- | --- | ---: | ---: | ---: | ---: |
| Bridge Africa | ACET | 6 | 9 | 15 | 468,270.00 |
| Bridge Africa | AFIDEP | 5 | 10 | 15 | 232,538.19 |
| Bridge Africa | Nkafu Policy Institute | 9 | 2 | 11 | 62,374.19 |
| Bridge Africa | PCNS | 1 | 9 | 10 | 154,950.00 |
| Bridge Africa | SAIIA | 19 | 2 | 21 | 119,080.00 |
| CACEPS | APHRC | 13 | 6 | 19 | 309,872.00 |
| CACEPS | CIP | 4 | 7 | 11 | 314,632.00 |
| CACEPS | CPED | 2 | 0 | 2 | 11,998.00 |
| CACEPS | ECES | 4 | 0 | 4 | 11,794.00 |
| CACEPS | IPAR | 3 | 4 | 7 | 81,200.00 |
| RAISED Africa | REPRC | 0 | 2 | 2 | 38,250.00 |
| **Total** |  | **66** | **51** | **117** | **1,804,958.38** |

ERF and PEP have no mapped activity in these workbooks and must not receive a placeholder item. The two REPRC activities appear in a mixed CACEPS workbook but belong in REPRC's active RAISED Africa space.

## Hard exclusions

The following identities must remain in the source-row audit with their exclusion reason, but must not create a think-tank procurement-plan item:

| Reference | Amount (USD) | Reason |
| --- | ---: | --- |
| `ET-AUC-494922-GO-RFQ` | 50,000.00 | ATTP Secretariat procurement; it has no think-tank owner. |
| `ET-AUC-570606-CS-QCBS` | 55,213.00 | CACEPS-wide translation/design framework with no member owner; its category and method also require redesign. |
| `ET-AUC-570613-CS-CDS` | 10,000.00 | Central policy mentorship with no member owner; the reviewer explicitly says to exclude it from the procurement plan. |

`ET-AUC-570613-CS-CDS` occurs in both above-10K workbooks. Consequently the exclusion inventory is four physical rows but three unique exclusions.

## Identity, duplicate, and provenance rules

- Resolve the owner globally from the activity text, then resolve that exact active think-tank membership under the stable consortium code. Do not hard-code database UUIDs.
- Within FY2026, the business identity is `(think-tank owner, canonical reference)`. A reference alone is not globally unique.
- Deduplicate the same owner/reference by audited source priority and retain every alternate workbook/sheet/row as provenance. The mapped duplicates are `ET-AUC-557675-GO-RFQ` for AFIDEP and `ET-AUC-557631-GO-RFQ` for ACET; the Bridge workbook occurrence is preferred in each case.
- Keep a reference used by different owners as separate items. The intentional cross-owner collisions are `ET-AUC-007-CS-INDV` (SAIIA/CIP), `ET-AUC-009-CS-INDV` (SAIIA/IPAR), and `ET-AUC-024-CS-INDV` (AFIDEP/CIP).
- Normalize only for identity matching: uppercase, trim/collapse whitespace, remove whitespace inside the prefix, and normalize spacing around hyphens. Preserve the literal source reference and activity wording in the raw payload.
- Both seeders contribute to the same FY2026 plan for a member. They must not create separate “above” and “below” annual plans.
- Imported plans and items remain `draft`. Source statuses and Bank/AUC comments are evidence, not authorization to manufacture an approval, no-objection, procurement, contract, or workflow transition.

## Source conflicts and review-required flags

The audit deliberately preserves, rather than conceals, the following conditions:

- APHRC `ET-AUC-563626-CS-CDS` is normalized to LCS because the Bank reviewer explicitly directs Least Cost Selection.
- PCNS `ET-AUC-557701-CS-CQS` and `ET-AUC-557703-CS-CQS` are retained but flagged because the reviewer identifies translation as non-consulting rather than consultancy.
- AFIDEP `ET-AUC-563573-CS-INDV`, `ET-AUC-563571-CS-INDV`, `ET-AUC-561427-CS-INDV`, and `ET-AUC-561425-CS-INDV` remain mapped and flagged as possible HR/staffing engagements. Excluding them requires an authorized business decision.
- Nkafu `ET-AUC-029-NC-LCS` says non-consulting in the reference while the source category says Consultancy.
- The CACEPS goods/non-consulting RFQ section has references that say INDV and lacks a replacement milestone header. Its audited RFQ/category interpretation and missing milestone evidence must remain visible in the payload.
- The CIP activity `ET-AUC-024-CS-INDV` has a 337-character source title. Its display title may be shortened deterministically to fit 255 characters, but the complete text must remain in `description` and the raw payload.
- Malformed references such as `E T-AUC-026-CS-INDV`, `E T-AUC-027-CS-INDV`, `T-AUC-028-CS-INDV`, `ET-AUC-008 CS-INDV`, `ET-AUC-009 -CS-INDV`, and double-hyphen goods references are canonicalized only as far as the audited evidence supports. No missing method suffix is invented.
- The blank Review Type for the AFIDEP USD 10,000 row is explicitly normalized to `Post`, consistent with its audited section; the source blank remains preserved.
- Five source rows contain backward milestone dates: Bridge Consultancy rows 24 and 42, and Bridge Goods rows 24–26. They are imported as source evidence and flagged; dates are not silently rewritten.
- The Bridge Goods displayed total omits row 24 (USD 6,000.00). The authoritative audited sum of its activity rows is USD 32,893.00, not USD 26,893.00.
- The merged budget-reference value in `AE32:AE35` applies to all four ACET rows while each raw blank remains recorded.
- The CACEPS goods sheet contains thousands of formatted/merged phantom cells through column XDJ. Read only the manifest-bounded sparse cells; do not use the worksheet's nominal maximum column as the data boundary.
- The above-10K workbooks contain no reliable fiscal-year column. This migration pins them explicitly to FY2026.

## Safe execution

Do not add these seeders to `DatabaseSeeder`, do not run all seeders, and do not use the generic `think-tank-procurement:import-workbooks` command for this curated migration. Take a restorable database backup first and deploy the four checked-in workbooks, manifest, migration service, and both targeted seeders together.

Before a write, verify that the procurement migrations are applied, every listed consortium and member resolves to exactly one active canonical membership, and there is no conflicting progressed or manually owned FY2026 record. The migration must fail closed on a checksum mismatch, unresolved/ambiguous owner, amount/count mismatch, non-draft collision, or unexpected existing item.

Recommended order:

```powershell
php artisan migrate:status

php artisan db:seed --class='Database\Seeders\AboveTenThousandThinkTankProcurementSeeder' --force
php artisan db:seed --class='Database\Seeders\BelowTenThousandThinkTankProcurementSeeder' --force
```

Linux uses the same targeted class names:

```bash
php artisan migrate:status

php artisan db:seed --class='Database\Seeders\AboveTenThousandThinkTankProcurementSeeder' --force
php artisan db:seed --class='Database\Seeders\BelowTenThousandThinkTankProcurementSeeder' --force
```

Run production seeders as the normal application/PHP user and against the intended environment. Do not paste secrets into command output or logs. If a command fails, stop; do not run the second seeder or weaken a guard to force completion.

### Rerun rules

An unchanged rerun must be idempotent: it reuses the same FY2026 member plans and canonical item identities, updates only migration-owned draft data, preserves alternate source provenance, and creates no duplicate items. It must also recompute plan totals from canonical items rather than incrementing totals.

A rerun is not permission to overwrite human work. Abort if an importer-owned plan or item has progressed beyond draft, if a matching item is not demonstrably owned by this migration, if the source hash or audited inventory has changed, or if tenant ownership is ambiguous. Never delete/recreate an import batch merely to make a rerun pass. Review a changed workbook as a new controlled migration and update the manifest only after re-audit.

The seeders must not send email, queue notifications, assign reviewers, create procurement executions, or approve/submit plans. A successful run is data staging into the correct draft tenant plans only.

## Post-migration verification checklist

- [ ] All four deployment files match the byte counts and SHA-256 hashes above; the public originals are unchanged.
- [ ] All 123 physical rows are accounted for in the import audit: 117 canonical mapped items, two additional mapped duplicate occurrences, and four hard-excluded occurrences (including the duplicated exclusion).
- [ ] There are exactly 117 mapped FY2026 items worth USD 1,804,958.38 across the 11 tenant spaces listed above.
- [ ] The below band contains 66 items worth USD 238,573.38; the at-or-above band contains 51 items worth USD 1,566,385.00.
- [ ] `ET-AUC-027-CS-INDV` is in AFIDEP's at-or-above band, not the below band.
- [ ] The three hard-exclusion identities create no tenant plan item and retain explicit source-row exclusion reasons.
- [ ] The two same-owner mapped duplicates create one item each, while all three cross-owner reference collisions create two tenant-specific items each.
- [ ] REPRC receives its two activities under RAISED Africa; no record is routed by workbook filename alone.
- [ ] Each mapped member has one FY2026 plan receiving both bands, with no duplicate annual plan and no placeholder plan/item for ERF or PEP.
- [ ] Item and plan statuses remain draft; no approval, no-objection, execution, contract, email, or notification was generated.
- [ ] Full source descriptions, literal references, worksheet coordinates, source statuses, reviewer comments, normalizations, exclusions, and alternate provenance remain inspectable.
- [ ] The tenant UI/API shows each distribution and total above without leaking another think tank's records.
- [ ] A controlled unchanged rerun leaves canonical plan/item counts, amounts, and identities unchanged.
- [ ] Command output, source hashes, database backup reference, deployed commit, operator, timestamp, and all unresolved review flags are recorded in the deployment/change ticket.

There is no general destructive rollback seeder. The preferred rollback is restoration of the coordinated pre-run database backup. If targeted cleanup is ever required, it must use the migration ownership markers and remove only untouched migration-owned draft records after a fresh backup and review.
