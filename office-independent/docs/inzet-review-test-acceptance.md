# Inzet import review – isolated test acceptance

Scope: `/office-test/inzet/importcontrole` only. No production rollout, no source import changes.

## Required private configuration

- `OFFICE_BASE_PATH=/office-test`
- `OFFICE_PUBLIC_HOST=brebobv.nl`
- `OFFICE_TEST_INZET_IMPORT_REVIEW_ENABLED=enabled`
- `OFFICE_TEST_INZET_IMPORT_REVIEWER_ID`: positive Office operator ID authorized for all imported projects
- `OFFICE_TEST_INZET_SQLITE_PATH`: existing, readable SQLite source **outside the webroot**
- `OFFICE_TEST_INZET_REVIEW_DECISIONS_SQLITE_PATH`: separate private SQLite path **outside the webroot**, with writable parent directory
- Valid `OFFICE_INTAKE_PDO_DSN`, `OFFICE_INTAKE_DB_USER`, and `OFFICE_INTAKE_DB_PASSWORD` for Office authentication

Do not place either database in `public_html`. Do not reuse the source path for decisions. Restrict the private parent directory to the application account. The test server must have PDO SQLite enabled.

## One-time initialization (server shell, after test release)

```sh
php office-independent/bin/init-inzet-review-store.php
```

Run with the private environment already configured and with the current release as working directory. The script is CLI-only, validates the source, creates the decision tables in a separate database and sets file permissions to `0600`. Confirm it reports `Private Inzet decision schema ready. No source changes.`

## Browser acceptance checklist

1. Open `https://brebobv.nl/office-test/inzet/importcontrole` with the authorized Office reviewer account. Confirm 1,940 source rows and 85 control points against the approved import snapshot.
2. Filter by employee, project and week; confirm only matching source issues are shown.
3. Select `Medewerker onderzoeken`, enter a note and save. Confirm a `303` redirect back to the same test route and the saved decision in the table.
4. Reload the page. Confirm the decision persists and the revision increases on the next edit.
5. Change the same decision to `Uitsluiten` with a required note. Confirm audit history contains both revisions in the private decision database.
6. Submit a stale revision from an older page. Confirm HTTP `409` and no extra audit record.
7. Try an invalid CSRF token and a different Office account. Confirm HTTP `403`; try the route on `/office` and confirm HTTP `404`.
8. Confirm the imported SQLite source checksum and row counts did not change. Confirm no invoice approval or payroll release was triggered.

## Release gates

GitHub CI validates source labels, filters/forms, decision persistence, and the isolated review cycle. It **does not** validate server environment variables, authentication cookies, live HTTP routing, or the real imported SQLite file. Those require explicit test-environment acceptance.

Do not merge the test feature or deploy it to production without separate authorization.
