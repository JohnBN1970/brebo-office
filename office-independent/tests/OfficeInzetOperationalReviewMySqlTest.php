<?php
declare(strict_types=1);
require_once __DIR__.'/fixtures/operational-review.php';
$dsn=getenv('OFFICE_INTAKE_TEST_DSN')?:'';
if ($dsn==='') {echo "SKIP: disposable MySQL CI database required\n";exit(0);}
if (!str_starts_with($dsn,'mysql:') || !preg_match('/(?:^|;)dbname=office_inzet_ci(?:;|$)/',$dsn)) throw new RuntimeException('Only the disposable CI database is allowed');
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false];
$user=getenv('OFFICE_INTAKE_TEST_USER')?:'';$password=getenv('OFFICE_INTAKE_TEST_PASSWORD')?:'';
$admin=new PDO($dsn,$user,$password,$options);
$name='office_inzet_ci_operational_review';
$admin->exec('CREATE DATABASE '.$name.' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
try {
  $testDsn=preg_replace('/dbname=office_inzet_ci(?=;|$)/','dbname='.$name,$dsn);
  $office=new PDO($testDsn,$user,$password,$options);
  $other=new PDO($testDsn,$user,$password,$options);
  $other->exec('SET SESSION innodb_lock_wait_timeout=1');
  $f=operationalFixture($office);$locked=false;
  $f['db']->sqliteCreateFunction('check_authority_lock',function()use($other,&$locked):int {
    try {$other->exec("UPDATE office_operator_accounts SET active=0 WHERE id='manager'");}
    catch(PDOException $e) {
      if ((int)($e->errorInfo[1]??0)!==1205) throw $e;
      $locked=true;return 1;
    }
    throw new RuntimeException('Authority was mutable before the operational commit');
  });
  $f['db']->exec("CREATE TRIGGER assert_authority BEFORE UPDATE ON inzet_registration BEGIN SELECT check_authority_lock(); END");
  $row=operationalRow($f,'R1');
  $f['review']->decide($f['contexts']['manager'],'R1',1,'approved','',$row['snapshot']);
  operationalAssert($locked,'Current authority must stay locked through the decision update');
  operationalAssert(operationalRow($f,'R1')['state']==='approved','Approval must persist');
  $other->exec("UPDATE office_operator_accounts SET active=0 WHERE id='manager'");
  operationalDenied(fn()=>$f['review']->listForOperator($f['contexts']['manager']),DomainException::class);
  echo "Operational MySQL authority: concurrent revocation locked through save; locks released; revoked access denied OK\n";
} finally {
  unset($f,$office,$other);
  $admin->exec('DROP DATABASE '.$name);
}
