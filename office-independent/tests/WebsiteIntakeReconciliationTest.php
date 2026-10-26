<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteIntakeReconciliation;
use Brebo\Office\Intake\WebsiteIntakeStore;

require_once __DIR__ . '/../src/Intake/WebsiteLeadRepositoryInterface.php';
require_once __DIR__ . '/../src/Intake/WebsiteOpportunityMapper.php';
require_once __DIR__ . '/../src/Intake/WebsiteIntakeStore.php';
require_once __DIR__ . '/../src/Intake/WebsiteIntakeReconciliation.php';

$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
if (!$dsn) {
  throw new RuntimeException('OFFICE_INTAKE_TEST_DSN is required.');
}
$db = new PDO($dsn, getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
foreach (array_reverse(WebsiteIntakeStore::schema()) as $statement) {
  if (preg_match('/CREATE TABLE IF NOT EXISTS ([a-z_]+)/', $statement, $matches)) {
    $db->exec('DROP TABLE IF EXISTS ' . $matches[1]);
  }
}
foreach (WebsiteIntakeStore::schema() as $statement) {
  $db->exec($statement);
}
$reconciliation = new WebsiteIntakeReconciliation($db);
$empty = $reconciliation->summary();
if ($empty['intake_count'] !== 0 || $empty['staging_lead_count'] !== 0) {
  throw new RuntimeException('Expected empty reconciliation baseline.');
}
$id = 'bbbbbbbb-2222-4222-8222-222222222222';
(new WebsiteIntakeStore($db))->acceptWebsiteLead($id, 'website', [
  'request_id' => $id, 'source' => 'website', 'schema_version' => '1',
  'observed' => ['building' => [], 'rooms' => [['id' => 'r1']], 'frames' => [['id' => 'f1']]],
  'detected' => [], 'calculated' => [], 'selected' => [],
]);
$summary = $reconciliation->summary();
if ($summary['intake_count'] !== 1 || $summary['staging_lead_count'] !== 1
  || $summary['missing_staging_leads'] !== 0 || $summary['orphan_staging_leads'] !== 0
  || $summary['unexpected_review_state'] !== 0) {
  throw new RuntimeException('Reconciliation summary mismatch.');
}
$leads = $reconciliation->stagedLeads();
if (count($leads) !== 1 || $leads[0]['request_id'] !== $id || $leads[0]['staging_id'] < 1) {
  throw new RuntimeException('Reconciliation lead identity mismatch.');
}
$db->exec("DELETE FROM office_website_opportunity WHERE request_id = " . $db->quote($id));
if ($reconciliation->summary()['missing_staging_leads'] !== 1) {
  throw new RuntimeException('Missing staging lead was not detected.');
}
echo "Independent staging CRM reconciliation checks passed.\n";
