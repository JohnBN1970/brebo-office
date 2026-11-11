# Native E-mail Kanban: Office-test acceptance gate

No Drupal dependency. This is an **operator runbook**, not proof of deployment.

## Preflight (read-only)
1. Verify deployment target is **office-test**, not production. Record commit SHA and PHP runtime.
2. Confirm authenticated Office operator session works; verify no unauthenticated mail data is visible.
3. Check `OFFICE_INTAKE_PDO_DSN` targets the expected Office-test database; do not print secrets.
4. Check MySQL permissions and current table existence using information_schema; take a verified backup.
5. Confirm the server supports `imap_open` and TLS certificates. If unavailable, keep polling disabled.
6. Verify feature flags are **disabled** before database installation.

## Schema and config (controlled)
- Review `office-independent/database/office_native_mail.sql`; apply **once** after backup to Office-test.
- Verify all six tables exist: mailboxes, messages, attachments, mailbox_access, classification_proposals, board_routes, board_moves (seven tables).
- Apply `office_mail_remove_buildings_destination.sql` only when legacy routes exist, after backup, once.
- Create mailbox registry rows with `active=0`; grant explicit functional access only to intended operators.
- Configure private credential references, never actual passwords in SQL or GitHub.
- Set `OFFICE_NATIVE_MAIL_SCHEMA_VERIFIED=enabled` only after schema validation.
- Set `OFFICE_MAIL_BOARD_CSRF_SECRET` to a stable random secret (32+ characters) in server configuration.
- Set `OFFICE_MAIL_BOARD_SCHEMA_VERIFIED=enabled` only after route/audit tables are verified.
- Leave `OFFICE_FUNCTIONAL_MAIL_CRON_ENABLED` disabled until IMAP connectivity, ACL and ingestion checks pass.
- Do **not** enable the transitional Drupal-UID personal push receiver.

## Acceptance tests
1. Login: owner sees only own personal mailbox; another operator cannot see it.
2. Functional mailbox: only operators with an explicit `can_read=1` grant see messages.
3. Send a test message with unique subject to a configured functional mailbox; verify one stored message after two polls (dedup).
4. Verify conservative routing: unambiguous invoice to Finance, ambiguous message to Nieuw.
5. Drag Finance → CRM; refresh and confirm CRM remains owner and exactly one audit move exists.
6. Repeat same move; verify no duplicate audit row.
7. Try forged POST and another operator's message ID; both must be denied.
8. Verify personal mail cannot be dragged into a shared module; explicit personal handoff is not implemented.
9. Verify no Gebouwen column, suggestion or dossier link is created.
10. Verify no actual CRM/project/finance dossier is mutated by a board move.
11. Confirm mail HTML/body is escaped and no attachments are exposed via public storage.
12. Confirm PHP quality, navigation and native mail CI checks green on the exact deployment SHA.

## Rollback
Disable `OFFICE_FUNCTIONAL_MAIL_CRON_ENABLED` and `OFFICE_MAIL_BOARD_SCHEMA_VERIFIED` first.
Restore the previous code release. Preserve database records and audit; do not drop tables as an automatic rollback.

## Known blockers
- Native personal-mail push/handoff is not ready.
- IMAP support and live mailbox credentials have not been verified.
- No actual AI classification; current rules are conservative keyword matching.
- The latest-100 IMAP polling strategy needs gap-handling review before production use.
- No evidence of live Office-test deployment until the above checks have been executed and recorded.
