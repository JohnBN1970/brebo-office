<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcDealRevisionGuard.php';
use Brebo\Office\Calculation\OfficeCalcDealRevisionGuard;
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_calc_crm_drafts (id INTEGER PRIMARY KEY, opportunity_id INTEGER, crm_revision INTEGER, project_id INTEGER)');
$db->exec('CREATE TABLE office_crm_opportunities (id INTEGER PRIMARY KEY, revision INTEGER, stage TEXT, status TEXT)');
$db->exec('CREATE TABLE office_operator_accounts (id INTEGER PRIMARY KEY, active INTEGER)');
$db->exec('CREATE TABLE office_calc_deal_revision_reviews (draft_id INTEGER, deal_revision INTEGER, reviewed_by INTEGER, reviewed_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$db->exec("INSERT INTO office_calc_crm_drafts VALUES (1,1,1,NULL); INSERT INTO office_crm_opportunities VALUES (1,1,'proposal','open'); INSERT INTO office_operator_accounts VALUES (1,1)");
$guard=new OfficeCalcDealRevisionGuard($db);
$allow=static fn()=>true;
$deny=static fn()=>false;
function blocked(callable $f): void {
    try {$f();} catch (DomainException $e) {return;}
    throw new RuntimeException('Expected rejection');
}
$db->beginTransaction(); $guard->assertReviewed(1); $db->commit();
$db->exec('UPDATE office_crm_opportunities SET revision=2');
$db->beginTransaction(); blocked(fn()=>$guard->assertReviewed(1)); $db->commit();
blocked(fn()=>$guard->review(1,1,2,$deny));
blocked(fn()=>$guard->review(1,1,1,$allow));
$guard->review(1,1,2,$allow);
$db->beginTransaction(); $guard->assertReviewed(1); $db->commit();
$db->exec('UPDATE office_crm_opportunities SET revision=3');
$db->beginTransaction(); blocked(fn()=>$guard->assertReviewed(1)); $db->commit();
if ((int)$db->query('SELECT crm_revision FROM office_calc_crm_drafts')->fetchColumn()!==1) throw new RuntimeException('Original revision overwritten');
if ((int)$db->query('SELECT COUNT(*) FROM office_calc_deal_revision_reviews')->fetchColumn()!==1) throw new RuntimeException('Unexpected review history');
echo "DEAL_REVISION_REGRESSION=PASS\n";
