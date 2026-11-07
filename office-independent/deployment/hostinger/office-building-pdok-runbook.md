# Office-test: guarded BAG migration and storage activation

Status: prepared; **not executed**. No deploy or web request invokes the migration.
The save feature defaults off (`OFFICE_BUILDING_PDOK_SAVE_ENABLED` unset).

## Scope and prerequisites

- Only `/office-test`, private root `$HOME/domains/brebobv.nl/office_calc_test`, database `u213420663_office_test`.
- Reviewed source commit, passing **Office building PDOK safety** jobs (including isolated MySQL tests), PHP 8.3 and PDO MySQL.
- An authorized operator with private SSH access; never publish config, credentials, dumps or row contents as Actions artifacts.
- A separate, empty restore database `u213420663_office_restore`, explicitly provisioned by the database administrator. Its credentials go in a separate mode-0600 PHP array under `office_calc_test/config/`, with the same `OFFICE_INTAKE_*` keys. No application connects to it. Use a SELECT-only account for the migration tool's verification of this database.
- A maintenance window with **all writers to Office-test paused**, including background jobs and administrators; preserve read access. Do not claim a CLI advisory lock pauses application writes. Keep storage disabled throughout migration.
- Record building list and details, current addresses, project links and all four active zones for TEST - Wooncomplex A. Save HTTP status checks for `/office-test/gebouwen` and its detail page before/after.

## Backup and recovery rehearsal: mandatory before apply

1. Confirm the active mount, config and `SELECT DATABASE()` identify the exact test database, not SB Office or production. Record the active release path and source commit without recording secrets.
2. While writers are paused, create a complete schema-and-data dump to a mode-0700 directory `office_calc_test/backups/pdok/`, with `umask 077`. Use a private temporary MySQL defaults file (0600), never passwords in command arguments or logs. Use `set -euo pipefail` so a failed dump cannot masquerade as a successful gzip. Include all tables; do not restrict the backup to the three new columns.
3. Keep database selection out of the dump (no `--databases`, no `CREATE DATABASE` or `USE` targeting the original). Include schema/data, retain original routines/triggers/events in the full recovery backup as applicable, and have the DBA review the restore script for qualified database references. No restore is allowed to execute against the running database. Events/jobs must be disabled in the restore environment. A separate MySQL instance with no access to live data is preferred for a full recovery rehearsal.
4. Verify dump command exit code, nonzero length, `gzip -t`, SHA-256 and file permissions. Store the filename, byte size, UTC creation time and SHA-256 in the private change record. A successful file backup from the buildings deployment is **not** a database backup. The existing `office-inspection-test-backup.yml` alone is insufficient: it does not prove restoreability or compare restored data.
5. The DBA restores the backup in the isolated environment and verifies table schemas, row counts and data. Prepare the separate local `u213420663_office_restore` verification copy with the original building, zone and project-link schemas/data. Record the exact source backup SHA and successful restore evidence. The migration tool only SELECTs from this copy; it never creates or restores databases. A checkbox or a checksum alone is insufficient evidence of a successful restore.
6. Record recovery owner, verified restore procedure, expected downtime and where the original config/release and backup are held. Obtain explicit authorization for the exact database, backup checksum and reviewed source commit. If the backup is older than one hour or writers have resumed, take and verify a fresh backup and refresh the restore copy.

## Read-only plan

Run from the reviewed checkout's `office-independent` directory using the private server CLI (do not expose the script as a public route):

```sh
php deployment/hostinger/office-building-pdok-migrate.php \
  --config="$HOME/domains/brebobv.nl/office_calc_test/config/config.php"
```

Expected missing columns on the currently reported schema: `latitude`, `longitude`, `bag_id`. Stop if anything differs from the approved plan; investigate before proceeding. This command connects read-only and does not run DDL.

## Explicit apply (not yet authorized/executed)

Replace the two placeholders with the verified private backup path and its recorded SHA-256. The confirmation and paused-writers arguments are operator attestations, not automatic proof; the maintenance controls and restore evidence above remain mandatory.

```sh
php deployment/hostinger/office-building-pdok-migrate.php \
  --config="$HOME/domains/brebobv.nl/office_calc_test/config/config.php" \
  --apply --confirm=OFFICE-TEST-ADD-PDOK-COLUMNS --writers-paused=OFFICE-TEST \
  --backup="VERIFIED_ABSOLUTE_PRIVATE_BACKUP.sql.gz" \
  --sha256="RECORDED_64_HEX_SHA256" \
  --restore-config="$HOME/domains/brebobv.nl/office_calc_test/config/pdok-restore.php"
```

The tool checks mount, exact DSN/database, private backup location/age/checksum, separate restore identity, exclusive migration advisory lock, and original column sets plus deterministic row digests for **all rows** of buildings, zones and project links. Only then does it call the additive migration. Afterwards it compares original values and counts again. Success marker: `PDOK_MIGRATION_VERIFIED`. Keep the JSON report privately. Run the read-only plan again; no columns should remain missing.

MySQL DDL commits implicitly. A failure may leave a partial additive migration. **Stop**, retain the error and backup, keep storage off and inspect schema/data. Never automatically DROP the added columns or restore over the running database. Resume the idempotent migration only after renewed verification; a full restore requires a separate explicit recovery decision.

## Deploy and activate storage separately

1. Deploy the reviewed page **and all five** `src/Buildings/OfficeBuildingPdok*.php` files together, with per-file backups, syntax checks and byte-for-byte source/active checks. Existing targeted deployment copies only the page and repository; update it before using it with the new page. Keep the flag unset. No DDL belongs in deployment.
2. With flag off: verify building list/detail, project counts, four active zones, PDOK search and aerial image. A read-only user must retain read access. Check logs for dependency/schema errors. The read query remains compatible with the pre-migration schema.
3. Only after schema, deployment and backup verification, explicitly enable `OFFICE_BUILDING_PDOK_SAVE_ENABLED=enabled` in the trusted **Office-test** server configuration. Do not enable it for production or via a browser parameter.
4. In an authenticated manage account, search, inspect a BAG address and select **Deze BAG-locatie gebruiken**. This is a real data change: agree the exact test building/address first. Verify redirect/reload, address, 16-digit BAG ID, coordinate order, unchanged building name, project links and all four zone rows. Read-only users and revoked/inactive operators must be refused. Verify wrong/missing Origin and CSRF are refused. Do not log session cookies or CSRF values.
5. Disable the flag immediately if checks fail. Restore backed-up application files if necessary, leaving additive columns in place. Correcting or restoring building data requires a separately approved, backed-up action.

## Merge/deployment caution

The existing push-to-`develop` production workflow runs Drupal `updb` and writes production data. Do not trigger it as a side effect of this Office-test work. PR #1672 used a merge commit containing `[skip ci]` after its PR CI passed. Use an equally deliberate deployment decision for later merges; verify the actual resulting workflow runs. Merging code does not prove an Office-test deployment or migration took place.
