# Office-test hours acceptance — 2026-10-27

The user explicitly approved adding the new hours ledger, a targeted installation in Office-test, and synthetic browser acceptance. No production deployment, merge, financial booking, or app publication was performed.

## Installed and verified

- Created only `office_onsite_hours_reviews`; verified InnoDB, complete unique indexes and the person/event foreign keys. Event foreign-key columns retain the observed `utf8mb4_uca1400_ai_ci` collation.
- Installed nine review/projection classes and the review controller. Patched the presence link, bootstrap endpoint, active router and the Office-test-only web rewrite allowlist. Preserved the installed two-argument bootstrap service and native assignment/zone repository.
- Private backup directory: `office_calc_test/backups/hours-20261027-031747`. Its manifest records old/new SHA256 fingerprints and per-file recovery copies, including `.htaccess.before`. The private config backup remains server-only. Never publish it.
- Enabled only the two new review gates and generated a server-only signing secret.
- One persistent synthetic person, `TEST - Urenacceptatie 27-10-2026`, has six synthetic events and exactly three explicit audit decisions. Existing people and evidence were not edited or approved.

## Browser acceptance

| Synthetic session | Result |
|---|---|
| 24 October, 8 hours | Approved for 28,800 seconds |
| 25 October, 1 hour | Rejected, zero reviewed seconds, reason recorded |
| 26 October, 2 hours | Corrected to 5,400 seconds with reason |
| Correction without reason | Rejected; session stayed pending until a valid submission |

Opening the workbench again preserves these decisions. Reviewed evidence no longer offers an editable form. The original IN/OUT evidence remains unchanged.

## Live installed acceptance

[35 checks passed](https://github.com/JohnBN1970/brebo-office/actions/runs/40443847513), including installed hashes, schema, three outcomes, audit reasons, unauthorized read/write refusal, exact retry receipt reuse, conflicting retry refusal, and no duplicate ledger insert. The installed bootstrap returns only approved event pairs for the verified person, excludes rejected evidence, preserves project/zone output, rejects cross-person identity mismatch, and gives a separate synthetic employee an empty JSON object. Temporary device credentials and the extra identity fixture were rolled back.

Earlier installation attempts stopped before DDL because the filename guard did not allow hyphens. The guard was corrected. Browser acceptance then found the isolated web rewrite allowlist missing the new route; that single rule was added with a private backup. No parent/production rewrite file was changed.

[Installation](https://github.com/JohnBN1970/brebo-office/actions/runs/40443204315), [web route repair](https://github.com/JohnBN1970/brebo-office/actions/runs/40443658055), [iOS 22 unit + 2 UI tests](https://github.com/JohnBN1970/brebo-office/actions/runs/40441182948). The hours-chain CI verifies 36 SQLite and 39 real database assertions for both MySQL 8 and MariaDB 11.8. The iOS source is unchanged since the 24-test run.

The existing project map still loads and reports zone 2 as 110.00 metres, revision 6; all four existing zones remain listed. No geometry write was performed during this installation.

The automatic installation and mutable acceptance jobs were retired after use. Installer and acceptance scripts remain CLI-only audit/recovery sources; they must not be wired to ordinary deployments or HTTP routes.

## Recovery and remaining work

Disable the two new review gates and restore only changed files from the private manifest, including the test rewrite and config. Retain the new ledger and audit records; do not drop tables or delete evidence. No full release directory replacement.

This completes the tested Office-test review chain, not the entire Office roadmap. Physical iPhone/Android acceptance, app distribution, supplier statements and the remaining modules are separate work. No new native app build has been published to users.
