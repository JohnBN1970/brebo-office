<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmLegacyStageEventStore;

require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyStageEventStore.php';

$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
if (!$dsn) {
  throw new RuntimeException('MySQL test DSN missing.');
}
$db = new PDO($dsn, getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('DROP TABLE IF EXISTS office_crm_legacy_stage_event');
$db->exec(WebsiteCrmLegacyStageEventStore::schema());
$store = new WebsiteCrmLegacyStageEventStore($db);
$event = ['legacy_event_node_id' => 81, 'legacy_opportunity_node_id' => 42, 'from_stage' => 'Lead', 'to_stage' => 'Kans', 'occurred_at' => '2026-10-01T10:00:00', 'note' => 'Qualified'];
if ($store->import([$event]) !== 1 || $store->import([$event]) !== 0) {
  throw new RuntimeException('Stage event migration must be idempotent.');
}
$rejected = false;
try {
  $store->import([array_replace($event, ['to_stage' => 'Gewonnen'])]);
}
catch (RuntimeException) {
  $rejected = true;
}
if (!$rejected || (int) $db->query('SELECT COUNT(*) FROM office_crm_legacy_stage_event')->fetchColumn() !== 1) {
  throw new RuntimeException('Conflicting CRM history must not overwrite an existing event.');
}
echo "Independent CRM stage history persistence checks passed.\n";
