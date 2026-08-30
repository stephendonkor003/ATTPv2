# Endowment Fund technical-proposal document recovery

The four historical proposal records are stored in the database, while their
private PDF bytes live under `storage/app/private/eoi-technical-proposals`.
Database deployment alone does not copy those bytes. A seeder run as `root`
can also create directories that the PHP web-service account cannot traverse,
which intentionally appears to an evaluator as a non-leaking 404 response.

Do not rerun `EndowmentFundTechnicalProposalScenarioSeeder` after evaluation
assignments exist. Use the checksum-pinned audit and recovery command instead.

## Production procedure

Fetch the real Git LFS objects in the deployed release, then run the read-only
audit as the same operating-system account that serves PHP (normally
`www-data` on Ubuntu):

```bash
git lfs pull
sudo -u www-data php artisan eoi:endowment-proposals:audit
```

If the audit reports missing or invalid private copies, take a coordinated
database/private-storage backup and run the explicit repair mode:

```bash
sudo -u www-data php artisan eoi:endowment-proposals:audit --repair
sudo -u www-data php artisan eoi:endowment-proposals:audit
```

The successful result is exactly four verified documents. Repair mode can
write only the four manifest filenames whose procurement, round, applicant,
database metadata, expected private path, byte size, PDF header, and SHA-256
all match the immutable bundled manifest. It does not create or modify any
database record.

Do not recursively change ownership or permissions across all of `storage`.
If the command cannot traverse an existing scenario directory, inspect and
repair only the exact reported `eoi-technical-proposals/<round UUID>` path.
Keep `storage/app/private` persistent and shared across application releases
and web nodes.

After deployment, an already-authorized evaluator download may also recreate
a recognized missing copy from the same verified source. Authorization,
assignment, applicant, proposal revision, and document nesting are checked
before this narrow self-healing path runs. Unknown documents remain closed.
