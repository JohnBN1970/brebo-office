<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(2);
$root=realpath(dirname(__DIR__,2));
if (!$root || $root!==realpath('/srv/brebo/office-test/current') || !str_starts_with($root,'/srv/brebo/office-test/releases/')) {
 echo "BLOCK_RELEASE_SCOPE\n"; exit(2);
}
$dsn=getenv('OFFICE_INTAKE_PDO_DSN')?:'';
if (getenv('OFFICE_BASE_PATH')!=='/office-test' || getenv('OFFICE_PUBLIC_HOST')!=='office-test.brebobv.nl' || !str_starts_with($dsn,'mysql:') || !preg_match('/(?:^|;)dbname=brebo_office_test(?:;|$)/',substr($dsn,6))) {
 echo "BLOCK_TEST_SCOPE\n"; exit(2);
}
require_once $root.'/src/Inzet/OfficeOnSiteRolloutReadiness.php';
try {
 $db=new PDO($dsn,getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 if ($db->query('SELECT DATABASE()')->fetchColumn()!=='brebo_office_test') {echo "BLOCK_DATABASE_SCOPE\n";exit(2);}
 $db->exec('START TRANSACTION READ ONLY');
 $report=OfficeOnSiteRolloutReadiness::inspect($db);
 $db->exec('ROLLBACK');
 echo json_encode($report,JSON_THROW_ON_ERROR)."\n";
 echo "ONSITE_READINESS_AUDIT=PASS_READ_ONLY\n";
} catch(Throwable) {echo "ONSITE_READINESS_AUDIT=BLOCKED\n";exit(3);}
