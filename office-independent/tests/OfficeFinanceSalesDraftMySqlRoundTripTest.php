<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Finance/OfficeFinanceAccess.php';
require_once dirname(__DIR__) . '/src/Finance/OfficeOutgoingInvoiceDraft.php';
require_once dirname(__DIR__) . '/src/Finance/OfficeVatRegimeCatalog.php';
require_once dirname(__DIR__) . '/src/Finance/OfficeOutgoingInvoiceRepository.php';

use Brebo\Office\Finance\OfficeOutgoingInvoiceRepository;

if (getenv('OFFICE_BASE_PATH') !== '/office-test') throw new RuntimeException('Office-test required');
$dsn = getenv('OFFICE_FINANCE_INTEGRATION_DSN');
if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:')) throw new RuntimeException('MySQL required');
$db = new PDO($dsn, getenv('OFFICE_FINANCE_INTEGRATION_USER') ?: '', getenv('OFFICE_FINANCE_INTEGRATION_PASSWORD') ?: '', [
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_EMULATE_PREPARES=>false,
]);
foreach (['OFFICE_FINANCE_SALES_DRAFTS_SCHEMA_VERIFIED','OFFICE_FINANCE_SALES_DRAFTS_CREATE_ENABLED','OFFICE_VAT_REGIMES_SCHEMA_VERIFIED'] as $flag) {
  if (getenv($flag) !== 'enabled') throw new RuntimeException($flag . ' required');
}
// This test inserts fixed fixture IDs and must NEVER run against a persistent database.
$database = (string)$db->query('SELECT DATABASE()')->fetchColumn();
if ($database !== 'office_finance_ci' || getenv('GITHUB_ACTIONS') !== 'true') {
  throw new RuntimeException('Round-trip writes allowed only in disposable GitHub Actions office_finance_ci database');
}
$db->beginTransaction();
try {
  $db->exec("INSERT INTO office_operator_accounts (id,active) VALUES ('finance_ci_writer',1),('finance_ci_reader',1)");
  $db->exec("INSERT INTO office_projects (id,title) VALUES (990001,'Finance CI Project'),(990002,'Finance CI Restricted')");
  $db->exec("INSERT INTO office_module_permissions (operator_id,module,permission) VALUES ('finance_ci_writer','finance','read'),('finance_ci_reader','finance','read')");
  $db->exec("INSERT INTO office_project_memberships (operator_id,project_id,permission) VALUES ('finance_ci_writer',990001,'manage'),('finance_ci_reader',990001,'read')");
  $db->exec("INSERT INTO office_vat_regimes (code,label,rate,treatment,active,sort_order) VALUES ('CI_21','CI tax',21.0000,'normal',1,1)");
  // Repository opens its own transaction, so fixtures must be committed before create().
  $db->commit();
  $repo = new OfficeOutgoingInvoiceRepository($db);
  $input = ['customer_name'=>'CI Customer','office_project_id'=>990001,'lines'=>[
    ['description'=>'CI service','quantity'=>'2','unit_price'=>'10.00','vat_regime_id'=>'CI_21']
  ]];
  try {
    $repo->create('finance_ci_reader', $input);
    throw new RuntimeException('Read-only project user created draft');
  } catch (DomainException $expected) {
    if ($expected->getMessage() !== 'Project write access required') throw $expected;
  }
  // Feature gate must reject writes even when project ACL would allow them.
  putenv('OFFICE_FINANCE_SALES_DRAFTS_CREATE_ENABLED=disabled');
  try {
    $repo->create('finance_ci_writer', $input);
    throw new RuntimeException('Disabled sales draft feature accepted a write');
  } catch (DomainException $expected) {
    if ($expected->getMessage() !== 'Sales draft creation not enabled') throw $expected;
  } finally {
    putenv('OFFICE_FINANCE_SALES_DRAFTS_CREATE_ENABLED=enabled');
  }
  // An inactive operator must not gain access from retained permission rows.
  $db->exec("UPDATE office_operator_accounts SET active=0 WHERE id='finance_ci_reader'");
  try {
    $repo->listForOperator('finance_ci_reader', 990001);
    throw new RuntimeException('Inactive operator could read sales drafts');
  } catch (DomainException $expected) {
    if ($expected->getMessage() !== 'Finance access required') throw $expected;
  }
  $db->exec("UPDATE office_operator_accounts SET active=1 WHERE id='finance_ci_reader'");
  // Finance module access alone must never grant access to an unrelated project.
  $otherProject = $input;
  $otherProject['office_project_id'] = 990002;
  try {
    $repo->create('finance_ci_writer', $otherProject);
    throw new RuntimeException('Writer created draft for unrelated project');
  } catch (DomainException $expected) {
    if ($expected->getMessage() !== 'Project unavailable') throw $expected;
  }
  try {
    $repo->listForOperator('finance_ci_writer', 990002);
    throw new RuntimeException('Writer read unrelated project');
  } catch (OutOfBoundsException $expected) {
    if ($expected->getMessage() !== 'Project unavailable') throw $expected;
  }
  // VAT is selected exclusively from active native Office settings.
  $invalidVat = $input;
  $invalidVat['lines'][0]['vat_regime_id'] = 'NOT_CONFIGURED';
  try {
    $repo->create('finance_ci_writer', $invalidVat);
    throw new RuntimeException('Unconfigured VAT regime accepted');
  } catch (InvalidArgumentException $expected) {
    if ($expected->getMessage() !== 'Invalid invoice line') throw $expected;
  }
  $db->exec("UPDATE office_vat_regimes SET active=0 WHERE code='CI_21'");
  try {
    $repo->create('finance_ci_writer', $input);
    throw new RuntimeException('Inactive VAT regime accepted');
  } catch (DomainException $expected) {
    if ($expected->getMessage() !== 'No active Office VAT regimes') throw $expected;
  } finally {
    $db->exec("UPDATE office_vat_regimes SET active=1 WHERE code='CI_21'");
  }
  // A database write failure must roll back without leaving a partial draft.
  $before = (int)$db->query('SELECT COUNT(*) FROM office_finance_sales_drafts')->fetchColumn();
  $db->exec("ALTER TABLE office_finance_sales_drafts ADD CONSTRAINT chk_finance_ci_customer CHECK (customer_name <> 'CI_BLOCKED')");
  $blocked = $input;
  $blocked['customer_name'] = 'CI_BLOCKED';
  try {
    $repo->create('finance_ci_writer', $blocked);
    throw new RuntimeException('Database constraint did not reject blocked draft');
  } catch (PDOException $expected) {
    if ($db->inTransaction()) throw new RuntimeException('Failed draft transaction remains open');
  }
  $after = (int)$db->query('SELECT COUNT(*) FROM office_finance_sales_drafts')->fetchColumn();
  if ($before !== $after) throw new RuntimeException('Failed draft left a partial record');
  $id = $repo->create('finance_ci_writer', $input);
  if ($id < 1) throw new RuntimeException('No draft ID');
  $list = $repo->listForOperator('finance_ci_writer', 990001);
  if (count($list) !== 1 || (int)$list[0]['id'] !== $id || $list[0]['customer_name'] !== 'CI Customer') {
    throw new RuntimeException('Sales draft round-trip failed');
  }
  $readList = $repo->listForOperator('finance_ci_reader', 990001);
  if (count($readList) !== 1) throw new RuntimeException('Read-only project visibility failed');
  // A second project must never appear in a first-project filtered register.
  $db->exec("INSERT INTO office_project_memberships (operator_id,project_id,permission) VALUES ('finance_ci_writer',990002,'manage')");
  $second = $input;
  $second['office_project_id'] = 990002;
  $second['customer_name'] = 'CI Other Project';
  $secondId = $repo->create('finance_ci_writer', $second);
  if ($secondId < 1 || $secondId === $id) throw new RuntimeException('Second project draft ID invalid');
  $firstProject = $repo->listForOperator('finance_ci_writer', 990001);
  $secondProject = $repo->listForOperator('finance_ci_writer', 990002);
  if (count($firstProject) !== 1 || (int)$firstProject[0]['id'] !== $id
    || count($secondProject) !== 1 || (int)$secondProject[0]['id'] !== $secondId) {
    throw new RuntimeException('Sales draft project register isolation failed');
  }
  $readerList = $repo->listForOperator('finance_ci_reader');
  if (count($readerList) !== 1 || (int)$readerList[0]['id'] !== $id) {
    throw new RuntimeException('Reader can see draft outside authorized projects');
  }
  echo "Finance sales draft MySQL round-trip OK\n";
} finally {
  if ($db->inTransaction()) $db->rollBack();
  // Dedicated ephemeral CI database only.
}
