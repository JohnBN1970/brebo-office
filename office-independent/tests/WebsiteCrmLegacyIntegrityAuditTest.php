<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmLegacyIntegrityAudit;

require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyIntegrityAudit.php';

$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
if (!$dsn) {
  throw new RuntimeException('Missing MySQL test DSN.');
}
$db = new PDO($dsn, getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sourceId = 987659;
$targetId = 987654;
try {
  $db->exec("INSERT INTO office_crm_legacy_opportunity (legacy_node_id,title,stage,owner_uid,website_request_id,source_json) VALUES (987659,'Audit test','lead',1,NULL,'{}')");
  $db->exec("INSERT INTO office_crm_legacy_relation (legacy_opportunity_id,source_field,legacy_target_type,legacy_target_id,office_target_id) VALUES (987659,'test_missing_contact','brebo_contact',987654,'office-test')");
  $audit = new WebsiteCrmLegacyIntegrityAudit($db);
  $missing = $audit->report();
  if ($missing['missing_contact_targets'] < 1 || $missing['blocking_issues'] < 1) {
    throw new RuntimeException('Missing CRM contact target did not block cutover.');
  }
  $db->exec("INSERT INTO office_crm_legacy_party (legacy_node_id,party_type,title,source_json) VALUES (987654,'brebo_contact','Audit contact','{}')");
  $resolved = $audit->report();
  if ($resolved['missing_contact_targets'] !== 0 || $resolved['blocking_issues'] !== 0) {
    throw new RuntimeException('Resolved CRM contact target still blocks cutover.');
  }
  $db->exec("INSERT INTO office_crm_legacy_relation (legacy_opportunity_id,source_field,legacy_target_type,legacy_target_id,office_target_id) VALUES (987659,'test_missing_org','brebo_organization',987655,'office-org')");
  $missingOrg = $audit->report();
  if ($missingOrg['missing_organization_targets'] !== 1 || $missingOrg['blocking_issues'] !== 1) {
    throw new RuntimeException('Missing CRM organization target did not block cutover.');
  }
  $db->exec("INSERT INTO office_crm_legacy_party (legacy_node_id,party_type,title,source_json) VALUES (987655,'brebo_organization','Audit organization','{}')");
  $resolvedOrg = $audit->report();
  if ($resolvedOrg['missing_organization_targets'] !== 0 || $resolvedOrg['blocking_issues'] !== 0) {
    throw new RuntimeException('Resolved CRM organization target still blocks cutover.');
  }
}
finally {
  $db->exec("DELETE FROM office_crm_legacy_relation WHERE legacy_opportunity_id=987659 AND source_field IN ('test_missing_contact','test_missing_org')");
  $db->exec("DELETE FROM office_crm_legacy_party WHERE legacy_node_id IN (987654,987655) AND party_type IN ('brebo_contact','brebo_organization')");
  $db->exec("DELETE FROM office_crm_legacy_opportunity WHERE legacy_node_id=987659");
}
echo "CRM staging integrity audit checks passed.\n";
