<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcCrmCanonicalLookup.php';
use Brebo\Office\Calculation\OfficeCalcCrmCanonicalLookup;

$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_crm_opportunities (id INTEGER PRIMARY KEY, revision INTEGER, status TEXT, stage TEXT)');
$db->exec('CREATE TABLE office_calc_crm_drafts (id INTEGER PRIMARY KEY, opportunity_id INTEGER, crm_revision INTEGER, created_by INTEGER, project_id INTEGER)');
$db->exec('CREATE TABLE calculations (id INTEGER PRIMARY KEY, office_calculation_id INTEGER, office_project_id INTEGER, status TEXT)');
$db->exec("INSERT INTO office_crm_opportunities VALUES (13,3,'open','opportunity')");
$db->exec('INSERT INTO office_calc_crm_drafts VALUES (7,13,3,2,NULL)');
$db->exec("INSERT INTO calculations VALUES (98,7,NULL,'draft')");
$gate=new OfficeCalcCrmCanonicalLookup($db);
$result=$gate->lookup(2,13,3);
if ($result!==['crm_draft_id'=>7,'calc_id'=>98,'opportunity_id'=>13,'crm_revision'=>3]) {
    throw new RuntimeException('Canonical Calc identity mismatch');
}
$denied=static function(callable $fn) : void {
    try {$fn();} catch(DomainException) {return;}
    throw new RuntimeException('Unsafe canonical lookup accepted');
};
$denied(static fn()=> $gate->lookup(3,13,3));
$denied(static fn()=> $gate->lookup(2,13,4));
$denied(static fn()=> $gate->lookup(2,0,3));
$db->exec("UPDATE office_crm_opportunities SET status='closed' WHERE id=13");
$denied(static fn()=> $gate->lookup(2,13,3));
$db->exec("UPDATE office_crm_opportunities SET status='open',revision=4 WHERE id=13");
$denied(static fn()=> $gate->lookup(2,13,3));
$db->exec("UPDATE office_crm_opportunities SET revision=3 WHERE id=13");
$db->exec("UPDATE calculations SET office_project_id=10 WHERE id=98");
$denied(static fn()=> $gate->lookup(2,13,3));
$db->exec("UPDATE calculations SET office_project_id=NULL,office_calculation_id=8 WHERE id=98");
$denied(static fn()=> $gate->lookup(2,13,3));
$db->exec("UPDATE calculations SET office_calculation_id=7 WHERE id=98");
$db->exec("INSERT INTO calculations VALUES (99,7,NULL,'draft')");
$denied(static fn()=> $gate->lookup(2,13,3));
$db->exec('DROP TABLE calculations');
$denied(static fn()=> $gate->lookup(2,13,3));
echo "CRM_CALC_PERSISTED_CANONICAL_LOOKUP=PASS\n";
