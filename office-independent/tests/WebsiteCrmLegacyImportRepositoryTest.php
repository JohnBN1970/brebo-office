<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmLegacyExportValidator;
use Brebo\Office\Intake\WebsiteCrmLegacyImportRepository;

require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyExportValidator.php';
require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyImportRepository.php';

$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
if (!$dsn) {
  throw new RuntimeException('OFFICE_INTAKE_TEST_DSN required.');
}
$db = new PDO($dsn, getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('DROP TABLE IF EXISTS office_crm_legacy_opportunity');
$db->exec(WebsiteCrmLegacyImportRepository::schema());
$repo = new WebsiteCrmLegacyImportRepository($db);
$id = 'aaaaaaaa-1111-4111-8111-111111111111';
$rows = [
  ['legacy_node_id' => 101, 'title' => 'Website lead', 'stage' => 'Lead', 'owner_uid' => 1, 'website_request_id' => $id],
  ['legacy_node_id' => 102, 'title' => 'Other opportunity', 'stage' => 'Offerte', 'owner_uid' => 2, 'website_request_id' => null],
];
$first = $repo->import($rows);
$again = $repo->import($rows);
if ($first !== ['records' => 2, 'inserted' => 2] || $again !== ['records' => 2, 'inserted' => 0]) {
  throw new RuntimeException('Legacy CRM import not idempotent.');
}
try {
  $repo->import([
    $rows[0],
    array_replace($rows[1], ['title' => 'Changed without approval']),
  ]);
  throw new RuntimeException('Conflicting CRM import accepted.');
}
catch (RuntimeException $e) {
  if ($e->getMessage() === 'Conflicting CRM import accepted.') {
    throw $e;
  }
}
$count = (int) $db->query('SELECT COUNT(*) FROM office_crm_legacy_opportunity')->fetchColumn();
if ($count !== 2) {
  throw new RuntimeException('CRM import altered record count unexpectedly.');
}
echo "Legacy CRM import staging checks passed.\n";
