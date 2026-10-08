<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteIntakeStore;

require_once __DIR__ . '/../src/Intake/WebsiteLeadRepositoryInterface.php';
require_once __DIR__ . '/../src/Intake/WebsiteIntakeStore.php';

$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
if (!$dsn) {
  fwrite(STDERR, "OFFICE_INTAKE_TEST_DSN is required for database integration tests.\n");
  exit(2);
}
$db = new PDO($dsn, getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
foreach (WebsiteIntakeStore::schema() as $statement) {
  $db->exec($statement);
}
$store = new WebsiteIntakeStore($db);
$id = 'bbbbbbbb-1111-4111-8111-111111111111';
$payload = ['request_id' => $id, 'source' => 'website', 'observed' => ['building' => []], 'calculated' => ['preliminary_scope' => ['summary' => 'Test kozijnen', 'items' => [['reference' => 'K1']]]]];
try {
  $first = $store->acceptWebsiteLead($id, 'website', $payload);
  $second = $store->acceptWebsiteLead($id, 'website', $payload);
  if ($first['duplicate'] || !$second['duplicate'] || $first['opportunity_id'] !== $second['opportunity_id']) {
    throw new RuntimeException('Idempotent replay failed.');
  }
  try {
    $store->acceptWebsiteLead($id, 'website', array_replace($payload, ['changed' => true]));
    throw new RuntimeException('Conflicting replay was accepted.');
  }
  catch (RuntimeException $e) {
    if ($e->getMessage() === 'Conflicting replay was accepted.') {
      throw $e;
    }
  }
  $count = $db->prepare('SELECT COUNT(*) FROM office_website_opportunity WHERE request_id = ?');
  $count->execute([$id]);
  if ((int) $count->fetchColumn() !== 1) {
    throw new RuntimeException('Expected exactly one opportunity.');
  }
  $scopeQuery = $db->prepare('SELECT preliminary_scope_json, lead_source, acquisition_channel FROM office_website_opportunity WHERE request_id = ?');
  $scopeQuery->execute([$id]);
  $stored = $scopeQuery->fetch(PDO::FETCH_ASSOC);
  $scope = json_decode($stored['preliminary_scope_json'], true, 512, JSON_THROW_ON_ERROR);
  if (($scope['items'][0]['reference'] ?? null) !== 'K1'
    || $stored['lead_source'] !== 'Website - Europakozijn'
    || $stored['acquisition_channel'] !== 'Portaal') {
    throw new RuntimeException('CRM context or preliminary scope was not retained.');
  }
  // Force a database rejection after the intake insert and verify atomic rollback.
  $rollbackId = 'cccccccc-2222-4222-8222-222222222222';
  $db->exec("CREATE TRIGGER reject_test_opportunity BEFORE INSERT ON office_website_opportunity
    FOR EACH ROW
    BEGIN
      IF NEW.request_id = 'cccccccc-2222-4222-8222-222222222222' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Simulated CRM write failure';
      END IF;
    END");
  try {
    $failed = false;
    try {
      $store->acceptWebsiteLead($rollbackId, 'website', array_replace($payload, ['request_id' => $rollbackId]));
    }
    catch (PDOException) {
      $failed = true;
    }
    if (!$failed) {
      throw new RuntimeException('Expected CRM write failure.');
    }
    $check = $db->prepare('SELECT COUNT(*) FROM office_website_intake WHERE request_id = ?');
    $check->execute([$rollbackId]);
    if ((int) $check->fetchColumn() !== 0) {
      throw new RuntimeException('Intake was not rolled back after CRM write failure.');
    }
  }
  finally {
    $db->exec('DROP TRIGGER IF EXISTS reject_test_opportunity');
    $db->prepare('DELETE FROM office_website_opportunity WHERE request_id = ?')->execute([$rollbackId]);
    $db->prepare('DELETE FROM office_website_intake WHERE request_id = ?')->execute([$rollbackId]);
  }
  echo "Database intake integration checks passed.\n";
}
finally {
  $db->prepare('DELETE FROM office_website_opportunity WHERE request_id = ?')->execute([$id]);
  $db->prepare('DELETE FROM office_website_intake WHERE request_id = ?')->execute([$id]);
}
