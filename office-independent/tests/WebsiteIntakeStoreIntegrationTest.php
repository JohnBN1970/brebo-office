<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteIntakeStore;

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
$payload = ['request_id' => $id, 'source' => 'website', 'observed' => ['building' => []]];
try {
  $first = $store->accept($id, 'website', $payload);
  $second = $store->accept($id, 'website', $payload);
  if ($first['duplicate'] || !$second['duplicate'] || $first['opportunity_id'] !== $second['opportunity_id']) {
    throw new RuntimeException('Idempotent replay failed.');
  }
  try {
    $store->accept($id, 'website', array_replace($payload, ['changed' => true]));
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
  echo "Database intake integration checks passed.\n";
}
finally {
  $db->prepare('DELETE FROM office_website_opportunity WHERE request_id = ?')->execute([$id]);
  $db->prepare('DELETE FROM office_website_intake WHERE request_id = ?')->execute([$id]);
}
