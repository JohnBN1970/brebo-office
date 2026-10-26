# BREBO Mail — testhost acceptance (no production cutover)

This runbook is **not** evidence of a completed test. Execute it on the designated, isolated mail test host. Never run it against the production mail host without explicit authorization.

## Prerequisites

- Obtain approval for the specific **test domain**, test host and a test recipient. Confirm the domain is not a live production mail domain.
- Confirm `BREBO_MAIL_TEST_DOMAINS` contains **only** approved test domains and `BREBO_MAILSTACK_RELOAD_ENABLED=0`. Do not paste secrets into logs or tickets.
- Confirm `/etc/brebo-mail-stack/generated` was provisioned with appropriate ownership/traversal permissions for the rendering user and Postfix.
- Preserve existing MX records and the external mail provider. **Do not change DNS, main.cf, master.cf, transport maps, or reload services** during the read-only acceptance checks below.

## Read-only checks (on test host)

Run from `/opt/brebo-office`, with the configured environment available to the PHP scripts:

```sh
php packages/brebo-mail-gateway/bin/preflight-test-transport-activation.php
php packages/brebo-mail-gateway/bin/check-active-postfix-test-transport.php
postfix check
postconf -h transport_maps
postconf -M
```

The preflight checks generated artifacts and trusted test-domain entries; it **does not** prove activation. The second command checks that the generated map and pipe are present in the **on-disk Postfix configuration**. Even `BREBO_POSTFIX_TEST_CONFIG=PASS` explicitly reports `BREBO_POSTFIX_TEST_RUNTIME=NOT_VERIFIED`: `postconf` does not prove the running Postfix master has reloaded.

Record sanitized output, the host name, test domain, command exit codes and execution time. If a check fails, **stop**; do not attempt automatic activation.

## Runtime and end-to-end acceptance — separate approval required

1. Confirm with the operator that the intended test-only configuration has been loaded into the running Postfix process. A read-only `postconf` result is insufficient.
2. Arrange a single test message to an explicitly approved test recipient using a controlled test route. **Do not redirect production mail or change public MX records.**
3. Correlate the message ID across the testhost Postfix logs, `brebo-mail-deliver`, gateway processing, and the Office mailbox. Verify exactly one delivered message, correct recipient, subject and body.
4. Verify a deliberately invalid test message is handled as permanent failure, while a temporary gateway failure remains retryable. Perform these only in an isolated test environment.
5. Record evidence and a rollback plan before any later production cutover decision.

## Acceptance record

| Check | Evidence | Result |
| --- | --- | --- |
| Generated artifacts and trusted domain | Preflight output | NOT TESTED |
| Postfix on-disk configuration | Config check output | NOT TESTED |
| Postfix running master loaded test route | Operator/runtime evidence | NOT TESTED |
| Test message reaches Office mailbox once | Message ID and redacted trace | NOT TESTED |
| Failure/retry semantics | Isolated test results | NOT TESTED |
| Production MX/provider unchanged | Operator confirmation | NOT TESTED |

**Gate:** Do not claim BREBO Mail is operational, or authorize a production cutover, until runtime and end-to-end evidence is recorded and approved.
