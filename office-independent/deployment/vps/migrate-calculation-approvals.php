<?php
declare(strict_types=1);
// Explicit test-only migration. Never invoke this file through a web route.
if (PHP_SAPI!=='cli' || getenv('OFFICE_ENV')!=='test'
  || getenv('OFFICE_CALC_APPROVAL_MIGRATE')!=='yes') {
  fwrite(STDERR,"Approval schema migration disabled.\n");
  exit(2);
}
require_once dirname(__DIR__,2).'/src/Calculation/OfficeCalculationLineApprovalRepository.php';
$dsn=getenv('OFFICE_INTAKE_PDO_DSN');
if (!is_string($dsn)||$dsn==='') {
  fwrite(STDERR,"Missing Office test database DSN.\n");
  exit(2);
}
$db=new PDO($dsn,getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_EMULATE_PREPARES=>false,
]);
foreach (\Brebo\Office\Calculation\OfficeCalculationLineApprovalRepository::schema() as $statement) {
  $db->exec($statement);
}
echo "OFFICE_CALC_APPROVAL_SCHEMA=PRESENT\n";
