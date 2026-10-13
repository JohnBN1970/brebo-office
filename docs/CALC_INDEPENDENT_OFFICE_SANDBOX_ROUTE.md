# Calc entry: independent Office sandbox rollout

## Verified boundaries

- Production public Office router lives in `public_html/office/index.php` and is separate from the `office-independent` source tree.
- Private Office handlers are under `office_private/public`, outside the document root.
- `office-independent/public/calc-workbench.php` is a fail-closed **entry placeholder**, not a Calc reverse proxy.
- Production `/calc-werkbank` must **not** be enabled until sandbox smoke tests pass.

## Sandbox-only integration sequence

1. Sync the independent Office release to a **separate sandbox** private directory; never overwrite the live `office_private` directory during testing.
2. Back up sandbox public router and config. Register `/calc-werkbank` in the sandbox PHP route allowlist and Apache rewrite rules; preserve existing login and CRM routes.
3. Configure `OFFICE_PUBLIC_HOST` to the actual sandbox hostname, enforce HTTPS, and set `OFFICE_CALC_ENABLED=enabled` only in sandbox. Never copy live session cookies, passwords, or database credentials into documentation.
4. Provision `office_calculation_permissions` through the explicit migration against the intended sandbox database, then grant `can_calculate=1` only to a designated test operator. The default must remain deny.
5. Verify: feature disabled = 503; anonymous = 302 to `/inloggen`; authenticated without Calc grant = 403; authenticated with grant = 200 placeholder; DB unavailable = 503; POST = 405; wrong host or HTTP = 403.
6. Verify existing `/inloggen`, `/uitloggen`, `/crm/aanvragen`, and `/crm/aanvragen/detail` still behave as before.
7. **Stop here:** a working Calc UI still requires a verified same-origin routing mechanism, signed launch integration, and runtime smoke tests. Do not substitute an unauthenticated redirect to the public Calc host.

## Release blockers

- Confirm whether Hostinger supports an authenticated same-origin reverse proxy for the Node Calc runtime, or choose an explicitly reviewed alternative.
- Add a safe, one-time signed Calc launch tied to the verified Office operator and scope.
- Test login redirect, permission revocation, expired session, CSRF/host controls, cookie path, and Calc static assets under the chosen prefix.
- Document rollback: disable `OFFICE_CALC_ENABLED`, remove sandbox route mapping, restore prior router, and leave production untouched.
