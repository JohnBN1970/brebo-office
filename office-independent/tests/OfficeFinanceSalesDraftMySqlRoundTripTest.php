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
  $id = $repo->create('finance_ci_writer', $input);
  if ($id < 1) throw new RuntimeException('No draft ID');
  $list = $repo->listForOperator('finance_ci_writer', 990001);
  if (count($list) !== 1 || (int)$list[0]['id'] !== $id || $list[0]['customer_name'] !== 'CI Customer') {
    throw new RuntimeException('Sales draft round-trip failed');
  }
  $readList = $repo->listForOperator('finance_ci_reader', 990001);
  if (count($readList) !== 1) throw new RuntimeException('Read-only project visibility failed');
  echo "Finance sales draft MySQL round-trip OK\n";
} finally {
  if ($db->inTransaction()) $db->rollBack();
  // Dedicated ephemeral CI database only.
}
