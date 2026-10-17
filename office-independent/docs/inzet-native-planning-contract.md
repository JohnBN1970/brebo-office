# Native Office Inzet planning — contract

## Purpose
Native, Drupal-free project team and shift planning, using Office session,
Office project/building identities and shared supplier/person identities.

## Implemented on isolated branch
- Native dashboard, project portfolio and read-only planning entry.
- ISO-week projection and ACL-scoped SQL shift retrieval, gated behind
  OFFICE_TEST_INZET_SHIFTS_ENABLED and OFFICE_TEST_INZET_SHIFTS_SCHEMA_VERIFIED.
- OfficeInzetShiftAssignmentControl validates candidate assignment fields
  against server-provided manage-project and eligible-person allowlists,
  rejects invalid/overnight-longer-than-24h intervals and returns only
  status=planned. This is a pure domain component, NOT a write endpoint.

## Required before editing or deployment
1. Establish authoritative Office employee and supplier identity and
   supplier-to-project assignment. A worker is not the invoice counterparty.
2. Verify manager authorization for project, employee eligibility and any
   supplier assignment server-side on each request, not from posted fields.
3. Implement CSRF-protected Office session POST route, transaction, audit
   record, overlap/concurrency checks and cancellation/revision controls.
4. Confirm time zone and daylight-saving rules for project/site clock times.
5. Add synthetic tests for unauthorized project/person, overlapping shifts,
   invalid dates, multi-project supplier workbook visibility and concurrency.
6. Verify test database schema, migrate only with explicit authorization,
   then run end-to-end acceptance before activating flags.

No Drupal screen reuse, no OnSite API cutover, no production deployment.
