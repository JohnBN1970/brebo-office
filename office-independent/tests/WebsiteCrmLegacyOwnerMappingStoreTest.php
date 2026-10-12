<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmLegacyOwnerMappingStore;

require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyOwnerMappingStore.php';

$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
if (!$dsn) {
  throw new RuntimeException('Missing MySQL test DSN.');
}
$db = new PDO($dsn, getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec(WebsiteCrmLegacyOwnerMappingStore::schema());
$store = new WebsiteCrmLegacyOwnerMappingStore($db);
$first = ['legacy_uid' => 987661, 'office_user_id' => 'audit-owner-987661', 'approved_by' => 'audit-reviewer'];
$second = ['legacy_uid' => 987662, 'office_user_id' => 'audit-owner-987662', 'approved_by' => 'audit-reviewer'];
try {
  if ($store->persist([$first]) !== 1 || $store->persist([$first]) !== 0) {
    throw new RuntimeException('Approved owner mapping must be idempotent.');
  }
  foreach ([
    [array_replace($first, ['office_user_id' => 'wrong-owner'])],
    [array_replace($first, ['approved_by' => 'other-reviewer'])],
    [array_replace($second, ['office_user_id' => $first['office_user_id']])],
    [$second, $second],
    [array_replace($second, ['legacy_uid' => 0])],
  ] as $invalidBatch) {
    $rejected = false;
    try {
      $store->persist($invalidBatch);
    }
    catch (PDOException|InvalidArgumentException|RuntimeException) {
      $rejected = true;
    }
    if (!$rejected) {
      throw new RuntimeException('Invalid or conflicting owner mapping was accepted.');
    }
  }
  if ((int) $db->query('SELECT COUNT(*) FROM office_crm_legacy_owner_mapping WHERE legacy_uid IN (987661,987662)')->fetchColumn() !== 1) {
    throw new RuntimeException('Rejected mapping batch was not rolled back.');
  }
}
finally {
  $db->exec('DELETE FROM office_crm_legacy_owner_mapping WHERE legacy_uid IN (987661,987662)');
}
echo "CRM owner mapping conflict and idempotency checks passed.\n";
