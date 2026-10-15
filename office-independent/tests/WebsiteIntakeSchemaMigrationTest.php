<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteIntakeSchemaMigrator;

require_once __DIR__ . '/../src/Intake/WebsiteLeadRepositoryInterface.php';
require_once __DIR__ . '/../src/Intake/WebsiteOpportunityMapper.php';
require_once __DIR__ . '/../src/Intake/WebsiteIntakeStore.php';
require_once __DIR__ . '/../src/Intake/WebsiteIntakeSchemaMigrator.php';

$db = new PDO(
  getenv('OFFICE_INTAKE_TEST_DSN'),
  getenv('OFFICE_INTAKE_TEST_USER') ?: '',
  getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '',
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$db->exec('DROP TABLE IF EXISTS office_website_opportunity');
$db->exec('DROP TABLE IF EXISTS office_website_intake');
$db->exec("CREATE TABLE office_website_intake (
  request_id CHAR(36) PRIMARY KEY,
  source VARCHAR(80) NOT NULL,
  payload_json LONGTEXT NOT NULL,
  status VARCHAR(32) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("CREATE TABLE office_website_opportunity (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id CHAR(36) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  stage VARCHAR(40) NOT NULL,
  requires_review TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)");
$db->exec("INSERT INTO office_website_opportunity (request_id, title, stage) VALUES
  ('dddddddd-3333-4333-8333-333333333333', 'Legacy lead', 'Lead')");
$migrator = new WebsiteIntakeSchemaMigrator($db);
$migrator->migrate();
$migrator->migrate();
$row = $db->query("SELECT lead_source, acquisition_channel, requirement_text, preliminary_scope_json
  FROM office_website_opportunity WHERE title = 'Legacy lead'")->fetch(PDO::FETCH_ASSOC);
if (!$row || $row['lead_source'] !== 'Website - Europakozijn'
  || $row['acquisition_channel'] !== 'Portaal'
  || $row['requirement_text'] !== ''
  || $row['preliminary_scope_json'] !== '{}') {
  throw new RuntimeException('Legacy lead was not preserved and upgraded.');
}
$db->exec('DROP TABLE office_website_opportunity');
$db->exec('DROP TABLE office_website_intake');
echo "Legacy intake schema migration checks passed.\n";
