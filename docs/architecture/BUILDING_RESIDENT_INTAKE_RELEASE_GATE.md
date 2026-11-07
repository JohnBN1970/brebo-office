# Building / address / resident intake: release gate

Status: **draft PR only — NOT DEPLOYABLE** until every gate below is evidenced.

## Delivered in PR #1676
- Building detail: BAG/PDOK map beside building metadata; full-width expandable address section and dossier placeholders.
- Authorized-building list search and read-only address repository, gracefully handling absent address tables.
- CSV and first-worksheet XLSX extraction, column mapping, deduplication/quarantine, provenance-bearing review proposals.
- Explicit review/approval and resident materialization services with audit events; no automatic overwrite.
- Read-only HTML preview, upload request guard, PHP syntax and regression workflows.

## Outstanding integration blockers (must resolve before rollout)
1. **Schema compatibility:** `OfficeBuildingSchema` declares `building_name` and `street_address`, while the existing `office-buildings.php` query uses `name` and `address_line`. Confirm actual deployed schema with read-only inspection and reconcile without guessing.
2. **Upload controller absent:** connect to an authenticated Office session, CSRF lifecycle, building-scoped manage permission, MIME/content checks, malware scanning, private temporary storage and guaranteed cleanup. The upload guard alone is not an HTTP endpoint.
3. **Document identity:** persist source document ID, source row, actor and provenance using the Office Documents canonical identity. Current preview accepts a supplied document ID; it does not create one.
4. **Resident authorization:** define separate PII view/manage grants and audit resident reads. Project read/manage alone must never grant resident access.
5. **Address approval workflow:** expose scoped reviewer UI, reject ambiguous matches, confirm address ownership and handle duplicate resident occupancy; no bulk auto-approval.
6. **XLSX hardening:** validate actual OOXML workbook/relationships and worksheet selection; cap ZIP entry decompressed sizes before extraction, archive entry count and expansion ratio; add malicious/real-workbook fixtures. CSV encoding/delimiter edge cases need integration tests.
7. **DB migration:** confirm exact production-compatible DDL, foreign key types and indexes; back up isolated Office-test DB and rehearse restore. Apply only after explicit GO. No automatic migration from this PR.
8. **Privacy:** establish retention/deletion schedule for temporary files, proposals, resident PII and audit, plus a lawful basis and access review.
9. **End-to-end acceptance:** authorized/unauthorized HTTP tests, CSRF failure, malformed upload, missing tables, duplicate addresses, multiple addresses per building, multiple projects per building, and safe rollback. Verify BAG/PDOK and unrelated Office flows.
10. **Coordination:** separate Work session is editing Office-test. Do not merge/deploy/modify live data until its owner confirms an integration window and explicit GO.

## Release decision
- CI green means **code-level checks passed**, not that a live import workflow exists.
- This branch must remain a **draft PR** until the above blockers are resolved.
- No deploy, no DB writes, no production or Office-test modification as part of this PR.
