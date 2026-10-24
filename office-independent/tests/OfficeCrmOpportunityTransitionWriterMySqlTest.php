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
if (getenv('OFFICE_CRM_TEST_ISOLATED_TABLES') !== 'YES') {
  fwrite(STDERR, "SKIP: explicit isolated-table acknowledgement missing\n");
  exit(2);
}
require_once dirname(__DIR__) . '/src/Workflow/OfficeOpportunityWorkflow.php';
require_once dirname(__DIR__) . '/src/Workflow/OfficeOpportunityStageMap.php';
require_once dirname(__DIR__) . '/src/Workflow/OfficeOpportunityTransitionGuard.php';
require_once dirname(__DIR__) . '/src/Crm/OfficeCrmOpportunityTransitionWriter.php';

$db = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$suffix = bin2hex(random_bytes(6));
$opps = 'office_crm_test_opps_' . $suffix;
$audit = 'office_crm_test_audit_' . $suffix;
// Random names are used exclusively by this test. No existing tables are touched.
$db->exec("CREATE TABLE `$opps` (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, stage VARCHAR(32) NOT NULL) ENGINE=InnoDB");
try {
  $db->exec("CREATE TABLE `$audit` (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, opportunity_id BIGINT UNSIGNED NOT NULL, previous_stage VARCHAR(32) NOT NULL, next_stage VARCHAR(32) NOT NULL, operator_id VARCHAR(128) NOT NULL, completed_checks_json TEXT NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
} catch (Throwable $e) {
  $db->exec("DROP TABLE `$opps`");
  throw $e;
}
try {
  $db->exec("INSERT INTO `$opps` (stage) VALUES ('prospect')");
  $writer = new \Brebo\Office\Crm\OfficeCrmOpportunityTransitionWriter($db);
  $writer->transition(1, 'prospect', 'in_behandeling', ['owner_assigned','scope_reviewed'], 'test-operator');
  $stage = $db->query('SELECT stage FROM `$opps` WHERE id = 1')->fetchColumn();
  $count = (int)$db->query('SELECT COUNT(*) FROM `$audit`')->fetchColumn();
  if ($stage !== 'in_behandeling' || $count !== 1) throw new RuntimeException('Successful transition/audit mismatch');
  try {
    $writer->transition(1, 'prospect', 'offerte', ['offer_recorded'], 'test-operator');
    throw new RuntimeException('Stale stage accepted');
  } catch (RuntimeException $e) {
    if ($e->getMessage() === 'Stale stage accepted') throw $e;
  }
  $db->exec('DROP TABLE `$audit`');
  try {
    $writer->transition(1, 'in_behandeling', 'calculatie', ['calculation_approved'], 'test-operator');
    throw new RuntimeException('Missing audit table accepted');
  } catch (PDOException $e) {
    // Expected: transaction must roll back the opportunity update.
  }
  if ($db->query('SELECT stage FROM `$opps` WHERE id = 1')->fetchColumn() !== 'in_behandeling') {
    throw new RuntimeException('Audit failure did not roll back stage');
  }
  echo "CRM opportunity transaction and rollback: OK\n";
} finally {
  $db->exec('DROP TABLE IF EXISTS `$audit`');
  $db->exec('DROP TABLE IF EXISTS `$opps`');
}
