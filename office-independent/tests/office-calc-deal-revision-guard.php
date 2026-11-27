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
$db->exec('UPDATE office_operator_accounts SET active=0 WHERE id=1');
blocked(fn()=>$guard->review(1,1,2,$allow));
$db->exec('UPDATE office_operator_accounts SET active=1 WHERE id=1');
$db->exec("UPDATE office_crm_opportunities SET status='closed' WHERE id=1");
blocked(fn()=>$guard->review(1,1,2,$allow));
$db->exec("UPDATE office_crm_opportunities SET status='open' WHERE id=1");
$guard->review(1,1,2,$allow);
$review=$db->query('SELECT draft_id,deal_revision,reviewed_by,reviewed_at FROM office_calc_deal_revision_reviews')->fetch(PDO::FETCH_ASSOC);
if (!is_array($review) || (int)$review['draft_id']!==1 || (int)$review['deal_revision']!==2 || (int)$review['reviewed_by']!==1 || !is_string($review['reviewed_at']) || $review['reviewed_at']==='') throw new RuntimeException('Review audit incomplete');
$db->beginTransaction(); $guard->assertReviewed(1); $db->commit();
$db->exec('UPDATE office_crm_opportunities SET revision=3');
$db->beginTransaction(); blocked(fn()=>$guard->assertReviewed(1)); $db->commit();
$guard->review(1,1,3,$allow);
$db->beginTransaction(); $guard->assertReviewed(1); $db->commit();
$history=$db->query('SELECT deal_revision,reviewed_by FROM office_calc_deal_revision_reviews ORDER BY deal_revision')->fetchAll(PDO::FETCH_ASSOC);
if (count($history)!==2 || (int)$history[0]['deal_revision']!==2 || (int)$history[1]['deal_revision']!==3 || (int)$history[0]['reviewed_by']!==1 || (int)$history[1]['reviewed_by']!==1) throw new RuntimeException('Deal revision review history lost');
if ((int)$db->query('SELECT crm_revision FROM office_calc_crm_drafts')->fetchColumn()!==1) throw new RuntimeException('Original revision overwritten');
if ((int)$db->query('SELECT COUNT(*) FROM office_calc_deal_revision_reviews')->fetchColumn()!==2) throw new RuntimeException('Unexpected review history');
echo "DEAL_REVISION_REGRESSION=PASS\n";
