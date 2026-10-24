<?php
declare(strict_types=1);

// Isolated integration test. Requires a disposable MySQL database explicitly
// supplied via OFFICE_CRM_TEST_DSN; never uses Office runtime credentials.
$dsn = getenv('OFFICE_CRM_TEST_DSN');
$user = getenv('OFFICE_CRM_TEST_USER');
$pass = getenv('OFFICE_CRM_TEST_PASSWORD');
if (!is_string($dsn) || $dsn === '' || !is_string($user) || !is_string($pass)) {
  fwrite(STDERR, "SKIP: isolated test database credentials missing\n");
  exit(2);
}
if (getenv('OFFICE_CRM_TEST_DISPOSABLE') !== 'YES') {
  fwrite(STDERR, "SKIP: explicit disposable database acknowledgement missing\n");
  exit(2);
}
require_once dirname(__DIR__) . '/src/Workflow/OfficeOpportunityWorkflow.php';
require_once dirname(__DIR__) . '/src/Workflow/OfficeOpportunityStageMap.php';
require_once dirname(__DIR__) . '/src/Workflow/OfficeOpportunityTransitionGuard.php';
require_once dirname(__DIR__) . '/src/Crm/OfficeCrmOpportunityTransitionWriter.php';

$db = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$schema = (string)$db->query('SELECT DATABASE()')->fetchColumn();
if (!preg_match('/(^|_)crm_writer_test($|_)/', $schema)) {
  throw new RuntimeException('Refusing non-test database: use database name containing crm_writer_test');
}
$suffix = bin2hex(random_bytes(4));
$opps = 'office_crm_opportunities_' . $suffix;
$audit = 'office_crm_opportunity_stage_audit_' . $suffix;
// The writer uses fixed production table names. Refuse to touch any existing
// tables, and use a dedicated disposable schema with exactly these names.
$existing = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('office_crm_opportunities','office_crm_opportunity_stage_audit')")->fetchAll(PDO::FETCH_COLUMN);
if ($existing !== []) throw new RuntimeException('Refusing database containing existing CRM tables');
$db->exec("CREATE TABLE office_crm_opportunities (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, stage VARCHAR(32) NOT NULL) ENGINE=InnoDB");
$db->exec("CREATE TABLE office_crm_opportunity_stage_audit (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, opportunity_id BIGINT UNSIGNED NOT NULL, previous_stage VARCHAR(32) NOT NULL, next_stage VARCHAR(32) NOT NULL, operator_id VARCHAR(128) NOT NULL, completed_checks_json TEXT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
try {
  $db->exec("INSERT INTO office_crm_opportunities (stage) VALUES ('prospect')");
  $writer = new \Brebo\Office\Crm\OfficeCrmOpportunityTransitionWriter($db);
  $writer->transition(1, 'prospect', 'in_behandeling', ['owner_assigned','scope_reviewed'], 'test-operator');
  $stage = $db->query('SELECT stage FROM office_crm_opportunities WHERE id = 1')->fetchColumn();
  $count = (int)$db->query('SELECT COUNT(*) FROM office_crm_opportunity_stage_audit')->fetchColumn();
  if ($stage !== 'in_behandeling' || $count !== 1) throw new RuntimeException('Successful transition/audit mismatch');
  try {
    $writer->transition(1, 'prospect', 'offerte', ['offer_recorded'], 'test-operator');
    throw new RuntimeException('Stale stage accepted');
  } catch (RuntimeException $e) {
    if ($e->getMessage() === 'Stale stage accepted') throw $e;
  }
  $db->exec('DROP TABLE office_crm_opportunity_stage_audit');
  try {
    $writer->transition(1, 'in_behandeling', 'calculatie', ['calculation_approved'], 'test-operator');
    throw new RuntimeException('Missing audit table accepted');
  } catch (PDOException $e) {
    // Expected: transaction must roll back the opportunity update.
  }
  if ($db->query('SELECT stage FROM office_crm_opportunities WHERE id = 1')->fetchColumn() !== 'in_behandeling') {
    throw new RuntimeException('Audit failure did not roll back stage');
  }
  echo "CRM opportunity transaction and rollback: OK\n";
} finally {
  $db->exec('DROP TABLE IF EXISTS office_crm_opportunity_stage_audit');
  $db->exec('DROP TABLE IF EXISTS office_crm_opportunities');
}
