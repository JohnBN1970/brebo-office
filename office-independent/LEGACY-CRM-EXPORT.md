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

A CLI-only Drush export script is now available at
`web/modules/custom/brebo_data_intake/scripts/export-legacy-crm.php`.
From the trusted Drupal runtime, run:

```sh
drush scr web/modules/custom/brebo_data_intake/scripts/export-legacy-crm.php -- /secure/crm-export.json
php office-independent/bin/import-legacy-crm.php /secure/crm-export.json
```

The first command creates a new protected file outside the webroot and
refuses to overwrite an existing export. The second command defaults to
validation-only; it does not import until explicitly confirmed.
The export script has not been executed against a real Drupal runtime.

**Not yet implemented:** the complete mapping
of related CRM entity types, and production Office CRM UI reads from the
independent store. Do not switch production intake or retire Drupal CRM.
