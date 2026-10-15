# Independent CRM migration workbench (not production CRM)

The read-only PHP front controller is `public/crm-migration.php` and accepts **GET /brebo-internal/crm/migration**. It is **not routed or deployed automatically**. The public Drupal website and existing CRM remain authoritative until a separately approved cutover.

## Deployment safeguards

1. Restrict the route at the reverse proxy to trusted operator identities, ideally using existing Office SSO or private network access. **Do not publish the script or expose the token in a browser, query string, HTML or JavaScript.**
2. Set a dedicated randomly generated `OFFICE_CRM_WORKBENCH_TOKEN` (minimum 32 characters) as a server-side secret. A trusted reverse proxy must inject the `X-Office-Crm-Workbench-Token` header only **after** authenticating an authorized operator; strip any inbound header of that name before injection. Never accept this header directly from the internet.
3. Configure `OFFICE_INTAKE_PDO_DSN`, `OFFICE_INTAKE_DB_USER`, and `OFFICE_INTAKE_DB_PASSWORD`. Prefer a dedicated **read-only** SQL user with SELECT on the four `office_crm_legacy_*` staging tables.
4. Apply the reviewed SQL staging schemas in a separate migration step before enabling the route. Missing schema must produce 503, not automatic database changes.
5. Serve only over HTTPS. Verify 403 without trusted authentication/header, 404 on other paths, 405 for non-GET, and no-store responses.
6. Run an authorized smoke test with reviewed staging data. Reconcile counts, owners, relationships and stage history with the Drupal source before enabling any write path.

This workbench is a migration review tool. It currently displays only the first 50 rows per entity type; it is not a full CRM, not a reconciliation proof, and not a production cutover.

## Read-only opportunity details

The same authenticated front controller also handles `GET /brebo-internal/crm/migration/detail?id=<legacy_opportunity_id>`. The reverse proxy must restrict **both** paths and inject the token only after operator authentication. Unknown opportunities return 404 and invalid IDs return 400. The detail view shows source opportunity metadata, approved staged relations and staged stage history. This is still a migration review screen, not a production CRM editor.
