<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Finance/OfficeOutgoingInvoiceRepository.php';

use Brebo\Office\Finance\OfficeOutgoingInvoiceRepository;

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$repository = new OfficeOutgoingInvoiceRepository($db);
$input = ['customer_name'=>'Test','office_project_id'=>1,'lines'=>[]];
$original = [];
foreach (['OFFICE_BASE_PATH','OFFICE_FINANCE_SALES_DRAFTS_SCHEMA_VERIFIED','OFFICE_FINANCE_SALES_DRAFTS_CREATE_ENABLED'] as $key) {
  $original[$key] = getenv($key);
}
try {
  foreach ([
    ['/office','enabled','enabled'],
    ['/office-test','disabled','enabled'],
    ['/office-test','enabled','disabled'],
  ] as [$base,$schema,$create]) {
    putenv('OFFICE_BASE_PATH='.$base);
    putenv('OFFICE_FINANCE_SALES_DRAFTS_SCHEMA_VERIFIED='.$schema);
    putenv('OFFICE_FINANCE_SALES_DRAFTS_CREATE_ENABLED='.$create);
    try {
      $repository->create('1', $input);
      throw new RuntimeException('Disabled sales draft write accepted');
    } catch (DomainException $expected) {
      if ($expected->getMessage() !== 'Sales draft creation not enabled') throw $expected;
    }
  }
} finally {
  foreach ($original as $key=>$value) {
    if ($value === false) putenv($key);
    else putenv($key.'='.$value);
  }
}
echo "Sales draft repository gate OK\n";
