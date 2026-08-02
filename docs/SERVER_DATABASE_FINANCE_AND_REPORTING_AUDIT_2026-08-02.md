# ATTP Server Database Finance and Reporting Audit

Audit date: 2 August 2026  
Source: `C:\Users\user\Downloads\server db\attpuat.backup`  
Audit database: `attp_server_audit_20260802`  
Method: PostgreSQL custom-format backup restored into a new isolated local database. The pre-existing local database was not overwritten.

## Executive conclusion

Three separate conditions caused the reported symptoms:

1. A historical USD 109,400 receipt has no live purchase order, procurement, fund allocation or disbursement request. It must remain available as an audit record but must not be counted as an actual or pending payment.
2. Funding to Think Tanks was accidentally saved at every displayed yearly maximum. This changed its total from USD 24,500,000 to USD 25,507,000—an overstatement of USD 1,007,000.
3. The unified MEL database structure is deployed, but no reporting cycle has been commissioned. There is no active M&E Matrix, report-ready indicator, published form, active period, collection or think-tank assignment. In addition, all 14 directly linked think-tank accounts are disabled.

## 1. Procurement disbursement forensic result

| Control | Audited result |
|---|---:|
| Disbursement records | 148 |
| Gross amount stored | USD 3,767,890.48 |
| Recognized actual payments | 147 records / USD 3,658,490.48 |
| Pending/other under the corrected rule | 0 records / USD 0.00 |
| Unsupported historical paid record | 1 record / USD 109,400.00 |
| Duplicate receipt references | 0 |
| Duplicate payment signatures | 0 |

Unsupported record retained for audit:

- Record ID: `019ec557-ec63-737a-a0af-6bf6f83a480c`
- Reference: `RCPT-2026-1GSA3D`
- Status: completed
- Paid date: 14 June 2026
- Missing live source links: purchase order, procurement, fund allocation and consortium disbursement request

Correction:

- The recognized-payment scope excludes the unsupported record from Actual Paid.
- The pending rule excludes it from Pending/Other.
- The Pending / Other Amount card has been removed from the disbursement page as requested.
- The underlying record was not deleted or rewritten because it is part of the historical audit trail.

## 2. Funding to Think Tanks allocation forensic result

Target sub-activity: `019ea974-4bc0-73d9-a6b9-4693201bbc24`

### Downloaded server state

| Year | Target sub-activity | Sibling | Parent activity |
|---:|---:|---:|---:|
| 2024 | 0.00 | 0.00 | 0.00 |
| 2025 | 0.00 | 24,800.00 | 24,800.00 |
| 2026 | 10,258,500.00 | 0.00 | 10,258,500.00 |
| 2027 | 10,248,500.00 | 0.00 | 10,248,500.00 |
| 2028 | 5,000,000.00 | 0.00 | 5,000,000.00 |
| **Total** | **25,507,000.00** | **24,800.00** | **25,531,800.00** |

The target total is USD 1,007,000 above its approved USD 24,500,000 envelope. This was not a parent-budget overrun: it was a data-entry inflation created by filling all displayed annual maximums.

### Protected one-click correction

| Year | Corrected target | Corrected sibling | Corrected parent |
|---:|---:|---:|---:|
| 2024 | 0.00 | 0.00 | 0.00 |
| 2025 | 0.00 | 0.00 | 24,800.00 |
| 2026 | 9,678,500.00 | 0.00 | 10,090,700.00 |
| 2027 | 9,678,500.00 | 0.00 | 10,248,500.00 |
| 2028 | 5,143,000.00 | 24,800.00 | 5,167,800.00 |
| **Total** | **24,500,000.00** | **24,800.00** | **25,531,800.00** |

The correction:

- recognizes only the two audited defect fingerprints;
- locks all affected project, activity and sub-activity rows;
- preserves unrelated child allocations;
- preserves the existing parent total;
- checks every project year before writing;
- updates the target, sibling and parent in one transaction;
- verifies the result before commit and rolls back on any mismatch;
- creates a system audit log containing before, plan and after snapshots; and
- is idempotent, so a repeat click makes no changes.

## 3. MEL reporting forensic result

| Item | Downloaded server count |
|---|---:|
| Indicators | 3 |
| Report-ready indicators | 0 |
| Data-entry forms | 0 |
| Reporting periods | 0 |
| Collections | 0 |
| Think-tank assignments | 0 |
| Performance reports | 0 |
| Indicator results | 0 |
| M&E Matrix versions | 0 |
| Active think tanks | 13 |
| Focal contacts | 18 |
| Focal organizations mapped | 13 |

Indicator configuration gaps:

- 1 indicator has no reporting frequency.
- 1 has no unit.
- 1 has no baseline.
- 2 have no annual target.
- 2 have no life-of-programme target.
- All 3 have no means of verification.
- 2 have no responsible user.
- None is linked to a project component and complete enough for a published reporting form.

Account access finding:

- 15 users are classified as Think Tank Admin.
- 14 have a direct organization link.
- All 14 directly linked accounts are disabled.
- Recorded reason for all 14: `Login disabled by bulk user management.`
- None is blacklisted.

The platform now shows a live six-control reporting-readiness audit covering:

1. enabled organization reporting accounts;
2. an active controlled M&E Matrix;
3. report-ready indicators;
4. a published reporting form;
5. an active reporting period; and
6. an open collection assigned to all 13 active think tanks.

The Focal Unit page now treats disabled accounts as not ready. Users with `users.manage` permission can enable a confirmed focal account from that page; no account is enabled automatically.

## 4. Production deployment and operation

1. Back up the production database and uploaded-file storage.
2. Deploy the application files.
3. Run `php artisan migrate --force`. The downloaded database already contains the two unified MEL migrations, so they should report as already run.
4. Run `php artisan optimize:clear` and rebuild production caches under the normal deployment procedure.
5. Open `/budget/subactivities/019ea974-4bc0-73d9-a6b9-4693201bbc24/edit`.
6. Confirm the page shows current target USD 25,507,000.00 and the protected corrected schedule.
7. Select **Automatically Spread USD 24,500,000** once. Confirm the success message and audit reference.
8. Reload the page and confirm target total USD 24,500,000.00 and reconciliation complete.
9. Open `/procurement/disbursements`. Confirm Actual Paid excludes USD 109,400 and no Pending / Other card is present.
10. Open M&E Data Entry and Performance Tracking. Work through every Action required readiness card.
11. In Focal Units, confirm each approved account owner before enabling login or assigning M&E Officer access.
12. Upload and activate the approved M&E Matrix; do not treat the workbook’s completed example as official performance data.
13. Configure approved indicators, publish the form, activate the reporting period, and open one collection assigned to all 13 think tanks.
14. Run a controlled draft demonstration before requesting real submissions.

No manual SQL update is required for these corrections.

## 5. Verification performed

- 15 focused unit tests passed with 44 assertions.
- Production-allocation fingerprint smoke test passed inside a rollback transaction.
- Production-MEL-readiness smoke test passed.
- Project financial-position reconciliation smoke test passed.
- All Blade templates compiled successfully.
- Modified PHP files passed syntax validation and Laravel formatting.
- The updated Microsoft Word MEL manual was regenerated and its DOCX package structure was validated.
