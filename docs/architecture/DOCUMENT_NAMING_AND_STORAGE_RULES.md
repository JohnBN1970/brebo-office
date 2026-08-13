# BREBO Office - Document naming, history and storage rules

## Status
Accepted architectural rule.

## Core principle
A visible dossier/folder name is a projection of business data, not a physical filesystem path.

Documents are linked to stable technical identities (`project_id`, `building_id`, organization/contact IDs and BREBO internal context). A rename of a project, building or CRM object must therefore never move, rename or duplicate the stored binary.

## Visible names
- The UI displays the current canonical name of the linked project/building/CRM object.
- When that canonical name changes, the visible virtual folder/dossier name changes immediately.
- Document relations remain attached to the stable technical ID.
- Physical `storage_key` values never contain mutable project/building/company names as identity.

## Name history
Every canonical business object that can act as a document context should retain name history.

Minimum history fields:
- context type
- context ID
- previous name
- new name
- effective/changed timestamp
- source of the change
- actor when known

Name history is audit data. It also allows old mail, filenames and extracted evidence containing an earlier project/building name to be resolved against the same stable object.

## Physical storage
Original binaries are immutable.

Preferred conceptual keys:
- `originals/<hash-prefix>/<sha256>`
- `previews/<document-id>/<derivative>`
- `thumbnails/<document-id>/<derivative>`

The exact provider may change (Drupal private storage, VPS filesystem, S3-compatible object storage, R2, etc.) without changing dossier/context semantics.

## Quality rule
Storage savings must come from deduplication, lifecycle policy and optimized derivatives, never destructive recompression of the original source document.

## Revision interaction
A rename is not a document revision. Document revisions are grouped by document family and ordered primarily by authoritative source date/time. Project/building name history is maintained independently from document revision history.
