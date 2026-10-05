# BREBO Mail Gateway deployment

This directory contains the pre-cutover runtime skeleton for a separate BREBO Mail Gateway host.

## Safety defaults

- Gateway API binds to localhost only.
- TLS terminates at the reverse proxy.
- Runtime secrets live outside the repository.
- DKIM private keys stay under the gateway data directory.
- Generated mailstack configuration is written separately.
- Mailstack reload is disabled by default.
- No MX record changes are part of this deployment layer.

## Pre-cutover sequence

1. Create the `brebomail` service account.
2. Install the repository under `/opt/brebo-office`.
3. Create `/var/lib/brebo-mail-gateway` and `/etc/brebo-mail-stack/generated` with restrictive ownership.
4. Copy the env example to `/etc/brebo-mail-gateway.env` and set unique secrets.
5. Run `php packages/brebo-mail-gateway/bin/readiness.php`.
6. Start the gateway service locally.
7. Configure TLS reverse proxy.
8. Provision only a test domain.
9. Render and validate Postfix/Dovecot/Rspamd configuration.
10. Keep `BREBO_MAILSTACK_RELOAD_ENABLED=0` until a controlled activation decision.

This phase must not change production MX records.
