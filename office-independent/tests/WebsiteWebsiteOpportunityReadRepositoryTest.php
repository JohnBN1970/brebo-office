<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteWebsiteOpportunityReadRepository;
use Brebo\Office\Intake\WebsiteIntakeStore;

require_once __DIR__ . '/../src/Intake/WebsiteWebsiteOpportunityReadRepository.php';
require_once __DIR__ . '/../src/Intake/WebsiteLeadRepositoryInterface.php';
require_once __DIR__ . '/../src/Intake/WebsiteOpportunityMapper.php';
require_once __DIR__ . '/../src/Intake/WebsiteIntakeStore.php';

$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
if (!$dsn) {
  throw new RuntimeException('Missing MySQL test DSN.');
}
$db = new PDO($dsn, getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (WebsiteIntakeStore::schema() as $schema) {
  $db->exec($schema);
}
$read = new WebsiteWebsiteOpportunityReadRepository($db);
foreach ([0, 101] as $invalidLimit) {
  try {
    $read->recent($invalidLimit);
    throw new RuntimeException('Invalid CRM limit was accepted.');
  }
  catch (InvalidArgumentException) {
    // Expected.
  }
}
foreach ([-1, 1000001] as $invalidOffset) {
  try {
    $read->recent(50, $invalidOffset);
    throw new RuntimeException('Invalid CRM offset was accepted.');
  }
  catch (InvalidArgumentException) {
    // Expected.
  }
}
$id = '8c3b5f21-71a1-4dcb-bc61-f8c1d00c9881';
try {
  $db->prepare("INSERT INTO office_website_intake (request_id,source,payload_json,status) VALUES (:id,'europakozijn','{}','review_required')")->execute(['id' => $id]);
  $db->prepare("INSERT INTO office_website_opportunity (request_id,title,stage,lead_source,acquisition_channel,requirement_text,preliminary_scope_json) VALUES (:id,'CRM intake test','lead','website','europakozijn','Test aanvraag','{}')")->execute(['id' => $id]);
  $created = $db->prepare('SELECT id FROM office_website_opportunity WHERE request_id=:id');
  $created->execute(['id' => $id]);
  $opportunityId = (int) $created->fetchColumn();
  $detail = $read->find($opportunityId);
  if ($detail === null || $detail['title'] !== 'CRM intake test' || $detail['source'] !== 'europakozijn') {
    throw new RuntimeException('Independent CRM detail did not return the requested opportunity.');
  }
  if ($read->find(2147483647) !== null) {
    throw new RuntimeException('Unknown CRM opportunity did not return null.');
  }
  $firstPage = $read->recent(1, 0);
  if (count($firstPage) !== 1) {
    throw new RuntimeException('CRM pagination limit was ignored.');
  }
  $rows = $read->recent(100);
  $matches = array_values(array_filter($rows, static fn(array $row): bool => $row['request_id'] === $id));
  if (count($matches) !== 1 || $matches[0]['title'] !== 'CRM intake test' || $matches[0]['source'] !== 'europakozijn') {
    throw new RuntimeException('Independent CRM list did not return the received website opportunity.');
  }
}
finally {
  $db->prepare('DELETE FROM office_website_opportunity WHERE request_id=:id')->execute(['id' => $id]);
  $db->prepare('DELETE FROM office_website_intake WHERE request_id=:id')->execute(['id' => $id]);
}
echo "Independent website CRM opportunity list checks passed.\n";
