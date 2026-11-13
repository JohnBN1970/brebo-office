<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OfficeExternalHoursClaimSchema.php';
require_once dirname(__DIR__).'/src/Inzet/OfficeExternalTimesheetReviewedImport.php';

use Brebo\Office\Inzet\OfficeExternalHoursClaimSchema;
use Brebo\Office\Inzet\OfficeExternalTimesheetReviewedImport;

$dsn=getenv('OFFICE_INZET_TEST_DSN');
if (!$dsn || !str_starts_with($dsn,'mysql:') || !str_contains($dsn,'dbname=office_inzet_ci')) {
    throw new RuntimeException('Disposable office_inzet_ci database required');
}
$db=new PDO($dsn,(string)getenv('OFFICE_INZET_TEST_USER'),(string)getenv('OFFICE_INZET_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
]);
$db->exec('CREATE TABLE office_inzet_people (id BIGINT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB');
$db->exec('INSERT INTO office_inzet_people VALUES (7)');
foreach (OfficeExternalHoursClaimSchema::statements() as $sql) $db->exec($sql);
$csv="project_id;person_id;work_date;claimed_hours;supplier_reference\n23;7;2026-11-13;8,00;sub-1\n23;7;2026-11-14;6,00;sub-1\n";
$importer=new OfficeExternalTimesheetReviewedImport($db);
$db->beginTransaction();
$ids=$importer->import($csv,['csv-row-2'],'reviewer-1');
$db->commit();
if (count($ids)!==1 || !isset($ids['csv-row-2'])) throw new RuntimeException('Selected row not imported');
if ((int)$db->query('SELECT COUNT(*) FROM office_inzet_external_hours_claims')->fetchColumn()!==1) {
    throw new RuntimeException('Unselected row imported');
}
$db->beginTransaction();
$retry=$importer->import($csv,['csv-row-2'],'reviewer-1');
$db->commit();
if ($retry!==$ids || (int)$db->query('SELECT COUNT(*) FROM office_inzet_external_hours_claims')->fetchColumn()!==1) {
    throw new RuntimeException('Idempotent retry failed');
}
$db->beginTransaction();
try {
    $importer->import($csv,['csv-row-3','missing'],'reviewer-1');
    throw new RuntimeException('Invalid selection accepted');
} catch (DomainException $expected) { $db->rollBack(); }
if ((int)$db->query('SELECT COUNT(*) FROM office_inzet_external_hours_claims')->fetchColumn()!==1) {
    throw new RuntimeException('Invalid selection wrote rows');
}
echo "MYSQL_REVIEWED_TIMESHEET_IMPORT_OK\n";
