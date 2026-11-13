<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcPersistedAcceptanceVerifier.php';
use Brebo\Office\Calculation\OfficeCalcPersistedAcceptanceVerifier;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "SQLite PDO driver is required for acceptance verifier tests\n");
    exit(1);
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys=ON');
$db->exec("CREATE TABLE office_crm_opportunities (id INTEGER PRIMARY KEY, stage TEXT NOT NULL, status TEXT NOT NULL)");
$db->exec("CREATE TABLE office_crm_calc_acceptances (opportunity_id INTEGER PRIMARY KEY, calculation_id INTEGER NOT NULL, accepted_revision TEXT NOT NULL, status TEXT NOT NULL)");
$db->exec("CREATE TABLE office_crm_calc_accepted_lines (opportunity_id INTEGER NOT NULL, source_line_id TEXT NOT NULL, cost_type TEXT NOT NULL, quantity INTEGER NOT NULL, unit_cost_cents INTEGER NOT NULL, activity_ref TEXT, PRIMARY KEY(opportunity_id,source_line_id))");
$db->exec("INSERT INTO office_crm_opportunities VALUES (10,'won','closed'),(11,'proposal','open')");
$db->exec("INSERT INTO office_crm_calc_acceptances VALUES (10,500,'r2','accepted'),(11,501,'r1','accepted')");
$db->exec("INSERT INTO office_crm_calc_accepted_lines VALUES (10,'a','labor',2,123,'work-a'),(10,'b','material',1,456,NULL),(11,'c','other',1,50,NULL)");
$v = new OfficeCalcPersistedAcceptanceVerifier();
$a = ['crm_opportunity_id'=>'10','calculation_id'=>500,'accepted_revision'=>'r2','status'=>'accepted'];
$l = [
 ['line_id'=>'a','cost_type'=>'labor','quantity'=>2,'unit_cost_cents'=>123,'activity_ref'=>'work-a'],
 ['line_id'=>'b','cost_type'=>'material','quantity'=>1,'unit_cost_cents'=>456,'activity_ref'=>null],
];
$assert = static function(bool $condition,string $name):void {
    if (!$condition) throw new RuntimeException('Failed: '.$name);
};
$assert(!$v($db,$a,$l),'no transaction');
$db->beginTransaction();
try {
    $assert($v($db,$a,$l),'valid persisted acceptance');
    $assert($v($db,$a,array_reverse($l)),'line order independent');
    $changed=$a; $changed['accepted_revision']='r3';
    $assert(!$v($db,$changed,$l),'revision mismatch');
    $changed=$a; $changed['calculation_id']=501;
    $assert(!$v($db,$changed,$l),'calculation mismatch');
    $changed=$a; $changed['status']='draft';
    $assert(!$v($db,$changed,$l),'status mismatch');
    $changed=$l; $changed[0]['unit_cost_cents']=124;
    $assert(!$v($db,$a,$changed),'amount mismatch');
    $changed=$l; $changed[0]['quantity']=3;
    $assert(!$v($db,$a,$changed),'quantity mismatch');
    $changed=$l; $changed[0]['activity_ref']='different';
    $assert(!$v($db,$a,$changed),'activity mismatch');
    $changed=$l; array_pop($changed);
    $assert(!$v($db,$a,$changed),'missing line');
    $changed=$l; $changed[1]=$changed[0];
    $assert(!$v($db,$a,$changed),'duplicate line');
    $changed=$a; $changed['crm_opportunity_id']='11';
    $assert(!$v($db,$changed,[['line_id'=>'c','cost_type'=>'other','quantity'=>1,'unit_cost_cents'=>50,'activity_ref'=>null]]),'opportunity not won');
    $db->exec("UPDATE office_crm_calc_acceptances SET status='revoked' WHERE opportunity_id=10");
    $assert(!$v($db,$a,$l),'revoked acceptance');
    $db->rollBack();
} catch(Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    throw $e;
}
echo "CRM_CALC_PERSISTED_ACCEPTANCE_VERIFIER=PASS\n";
