# Native Office project and building cutover

Decision: BREBO Office becomes the sole operational project/building authority. Drupal is **migration source only**, never a runtime fallback.

## Preconditions
- Confirm `u213420663_office_test` is the isolated target; verify actual database connection identity from the running test environment. Do not run SQL on production or shared Drupal sandbox.
- Review `20261015_office_native_project_building_core.sql` against the real Office operator schema and MySQL/MariaDB version. The SQL is **proposed, not executed**.
- Inventory source project/building IDs, links, project status values and any ID collisions. Preserve source numeric IDs until all Calc references are reconciled.
- Export source records once; stage a dry-run import. No live Drupal API in the Office read path.
- Reconcile counts, missing links, duplicate codes, orphan buildings and every Calc project reference. Reject mismatches; do not auto-merge identities.
- Back up both sides before cutover. Freeze source writes for final delta import; agree rollback and release owner.
- Assign `office_project_memberships` explicitly; never infer general project access from `office_calc_project_access`.
- Confirm native repository and ACL checks work against test data. Only then wire a feature-gated Office project view.
- Keep production tiles disabled until explicit approval after staging end-to-end evidence.

## Current code boundary
`OfficeNativeProjectRepository` reads only `office_projects`. `OfficeNativeProjectAccess` independently authorizes the general project dossier. The old signed Drupal context client remains **legacy unused code** and must not be wired into native routes.

## Not yet implemented
Actual migration/import command, source snapshot verification, live staging acceptance, building workbench, full Calc project handoff and production deployment.
