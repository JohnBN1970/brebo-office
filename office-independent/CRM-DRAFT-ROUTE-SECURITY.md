# Independent CRM draft route: mandatory authentication contract

**Not deployed. Do not enable a write route using the existing workbench token alone.**

The current `public/crm-migration.php` intentionally permits **GET only** and sets CSP `form-action 'none'`. Keep this behavior until an authenticated operator identity and session are available. The `WebsiteCrmDraftSubmissionHandler` is a reusable domain service, **not an HTTP authorization boundary**.

## Required reverse-proxy / Office session integration

1. Authenticate a real Office operator, enforce CRM edit permission and terminate unauthorized requests **before** invoking PHP. Do not treat `X-Office-Crm-Workbench-Token` as user authentication.
2. Provide an opaque, server-validated session identifier to PHP from a trusted internal source (not an arbitrary request header or form field). Tie it to the authenticated operator and session lifetime. Rotate it on login and logout.
3. Generate `WebsiteCrmDraftCsrf::issue(sessionId, opportunityId, time())` only after successful operator authentication. Configure a dedicated random server-side secret of at least 32 bytes; do not reuse the workbench token or website HMAC secret.
4. Accept only `POST /brebo-internal/crm/migration/draft` over HTTPS with a valid authenticated session, CRM edit permission, same-origin browser request and matching CSRF token. Verify with `WebsiteCrmDraftSubmissionHandler::submit()` and reject stale versions with HTTP 409. Reject malformed input with 400 and invalid CSRF with 403.
5. Do not trust client-provided user IDs or session IDs. Restrict request body size and use `application/x-www-form-urlencoded`. Set no-store and safe CSP response headers. Audit actor, opportunity ID, old/new version and timestamp without logging CSRF tokens or secrets.
6. Configure a **separate least-privilege database writer** limited to the draft table and read access to the legacy opportunity staging table. Keep the read-only workbench connection read-only.
7. Add HTTP integration tests proving anonymous, expired, forged, cross-session and unauthorized POST requests are rejected, and that valid POSTs update drafts but never legacy rows.
8. Reconcile imported opportunities before enabling editing. This staging draft model is not the canonical production CRM and cannot replace the Drupal CRM until a separate approved cutover.

## Current status

The draft renderer, validator, CSRF service, optimistic-locking store and submission handler are present and covered by unit/integration tests. **The authenticated session adapter and POST controller are not yet implemented.** The route remains disabled by design.
