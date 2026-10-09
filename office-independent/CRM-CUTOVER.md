# CRM cutover contract — website intake

## Verified current state

The public website submits to the Cloudflare Worker. The Worker signs and forwards
requests to the Drupal Office route `/brebo-internal/intake/europakozijn`.
The Drupal destination writes a `brebo_opportunity` **node** via
`DrupalWebsiteOpportunityGateway`. Its identity lookup is a title containing
the first eight characters of the request UUID, **not** the full UUID.

The independent implementation currently writes
`office_website_intake` and `office_website_opportunity` in MySQL. These
tables are not yet connected to the existing Office CRM screens. Therefore
they are **staging persistence**, not the canonical CRM.

## Required cutover gates

1. Define the canonical independent CRM opportunity repository and connect
   existing Office CRM reads, edits and reporting to it. Do not claim parity
   merely because fields are mapped into a staging table.
2. Inventory all existing Drupal `brebo_opportunity` records, owners,
   attachments, relationships, statuses and edit history. Export and reconcile
   before moving any live data. Preserve original Drupal node IDs as external
   legacy identifiers; never assume standalone auto-increment IDs match.
3. Establish a stable full-request-UUID identity in the canonical CRM and
   enforce uniqueness. The Drupal title-prefix lookup is not collision-proof.
4. Migrate existing opportunities **before** routing new website requests to
   the independent endpoint. Reconcile record counts, field parity and
   attachments, and verify the Office UI and reports read migrated records.
5. Configure and deploy the standalone endpoint behind HTTPS with the shared
   secret and database credentials supplied by runtime secrets. Run schema
   migration explicitly ahead of deployment, not on requests.
6. Test signed requests, invalid requests, duplicate replay, concurrency,
   rollback, Office visibility and rollback to the original route in staging.
7. Only then switch the Worker's Office target. Preserve a tested rollback
   and avoid dual-writing unless a reconciliation protocol is implemented.

## Release safety

This draft PR must not be merged/deployed as a production CRM replacement
until all gates above have objective evidence. No production Worker route or
Drupal configuration is changed by this document.
