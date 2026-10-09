# Drupal CRM opportunity export — operator procedure

The new `LegacyCrmOpportunityExporter` is a read-only PHP service,
not an exposed HTTP endpoint or an automatically scheduled export.
It uses Drupal's node storage and returns batches ordered by node ID.

For an authorized operator, use the service from a trusted Drupal runtime
to create a **restricted-access** JSON inventory. It includes raw CRM field
values and may contain personal or commercially confidential information.
Do not commit exports to Git, put them under the public webroot or publish
them as CI artifacts.

Each exported record includes its Drupal node ID, title, stage, owner UID,
publication state, timestamps and raw fields. A website request UUID is
intentionally `null` until recovered from a trusted original intake record:
the Drupal title only carries an eight-character prefix and cannot safely
reconstruct a full UUID.

Review the inventory and resolve unknown stages, missing owners, references,
attachments and website request UUIDs before importing. The existing
`import-legacy-crm.php` command defaults to dry-run and requires
`OFFICE_CRM_IMPORT_CONFIRM=YES` for writes.

**Not yet implemented:** a privileged export command, the complete mapping
of related CRM entity types, and production Office CRM UI reads from the
independent store. Do not switch production intake or retire Drupal CRM.
