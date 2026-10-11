<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmLegacyIntegrityAudit;

require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyIntegrityAudit.php';

$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
if (!$dsn) {
  throw new RuntimeException('Missing MySQL test DSN.');
}
$db = new PDO($dsn, getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec("INSERT INTO office_crm_legacy_relation (legacy_opportunity_id,source_field,legacy_target_type,legacy_target_id,office_target_id) VALUES (987659,'test_missing_contact','brebo_contact',987654,'office-test')");
try {
  $report = (new WebsiteCrmLegacyIntegrityAudit($db))->report();
  if ($report['missing_contact_targets'] < 1 || $report['blocking_issues'] < 1) {
    throw new RuntimeException('Missing CRM target did not block cutover.');
  }
}
finally {
  $db->exec("DELETE FROM office_crm_legacy_relation WHERE source_field='test_missing_contact' AND legacy_opportunity_id=987659");
}
echo "CRM staging integrity audit checks passed.\n";
