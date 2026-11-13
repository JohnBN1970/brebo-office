<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OfficeExternalHoursClaimSchema.php';
require_once dirname(__DIR__).'/src/Inzet/OfficeExternalHoursClaimRepository.php';
use Brebo\Office\Inzet\OfficeExternalHoursClaimSchema;
use Brebo\Office\Inzet\OfficeExternalHoursClaimRepository;
$dsn=getenv('OFFICE_INZET_TEST_DSN');
if (!$dsn || !str_starts_with($dsn,'mysql:') || !str_contains($dsn,'dbname=office_inzet_ci')) {
    throw new RuntimeException('Only disposable office_inzet_ci MySQL permitted');
}
$db=new PDO($dsn,(string)getenv('OFFICE_INZET_TEST_USER'),(string)getenv('OFFICE_INZET_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
]);
$db->exec('CREATE TABLE office_inzet_people (id BIGINT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB');
$db->exec('INSERT INTO office_inzet_people VALUES (7)');
foreach (OfficeExternalHoursClaimSchema::statements() as $sql) $db->exec($sql);
$repo=new OfficeExternalHoursClaimRepository($db);
$doc=hash('sha256','original-timesheet');
$line=hash('sha256','line-1');
$db->beginTransaction();
$id=$repo->importInsideTransaction(23,7,'2026-11-13',28800,'supplier-1',$doc,'row-1',$line,'operator-2');
$db->commit();
$db->beginTransaction();
$retry=$repo->importInsideTransaction(23,7,'2026-11-13',28800,'supplier-1',$doc,'row-1',$line,'operator-2');
$db->commit();
if ($id!==$retry || (int)$db->query('SELECT COUNT(*) FROM office_inzet_external_hours_claims')->fetchColumn()!==1) {
    throw new RuntimeException('Duplicate external claim imported');
}
$db->beginTransaction();
try {
    $repo->importInsideTransaction(23,7,'2026-11-13',21600,'supplier-1',$doc,'row-1',$line,'operator-2');
    throw new RuntimeException('Changed evidence incorrectly accepted');
} catch (DomainException $expected) {
    $db->rollBack();
}
echo "MYSQL_EXTERNAL_HOURS_CLAIM_IDEMPOTENT_OK\n";
