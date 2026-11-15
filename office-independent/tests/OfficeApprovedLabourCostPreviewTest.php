<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OfficeApprovedLabourCostPreview.php';
use Brebo\Office\Inzet\OfficeApprovedLabourCostPreview as P;
if(!in_array('sqlite',PDO::getAvailableDrivers(),true)) {
    echo "APPROVED_LABOUR_COST_PREVIEW_SKIPPED_SQLITE_MISSING\n";
    exit(0);
}
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_inzet_approved_hours (id INTEGER PRIMARY KEY,project_id INTEGER,person_id INTEGER,work_date TEXT,approved_seconds INTEGER,source_sha256 TEXT)');
$db->exec("INSERT INTO office_inzet_approved_hours VALUES (7,42,5,'2026-11-15',21600,'".str_repeat('a',64)."')");
$rates=[['person_id'=>5,'project_id'=>42,'valid_from'=>'2026-01-01','valid_until'=>null,'hourly_rate_cents'=>4250,'authorized'=>true]];
$preview=new P($db);
$cost=$preview->forApprovedBooking(7,$rates);
if($cost['actual_cost_cents']!==25500 || $cost['approved_hours_id']!==7)throw new RuntimeException('Incorrect approved labour preview');
if((int)$db->query('SELECT COUNT(*) FROM office_inzet_approved_hours')->fetchColumn()!==1)throw new RuntimeException('Preview mutated ledger');
foreach([[7,[]],[8,$rates]] as [$id,$input]) {
    try {$preview->forApprovedBooking($id,$input);throw new RuntimeException('Expected missing evidence rejection');}
    catch(DomainException $expected) {}
}
echo "APPROVED_LABOUR_COST_PREVIEW_OK\n";
