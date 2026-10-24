<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetReviewAccess.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetReviewDecisionRepository.php';
use Brebo\Office\Inzet\OfficeInzetReviewAccess as Access;
use Brebo\Office\Inzet\OfficeInzetReviewDecisionRepository as Decisions;
function ensure(bool $value, string $message): void {
  if (!$value) throw new RuntimeException($message);
}
foreach (['1','01','1-other','operator-a','operator-b'] as $actor) {
  foreach (['1','01','1-other','operator-a','operator-b',false,''] as $reviewer) {
    ensure(Access::allows($actor, $reviewer) === ($actor === $reviewer), 'Exact reviewer identity required');
  }
}
ensure(Access::sessionBinding('operator-a','session-a') !== Access::sessionBinding('operator-a','session-b'), 'Login change must invalidate review form');
ensure(Access::sessionBinding('a','bc') !== Access::sessionBinding('ab','c'), 'Unambiguous binding');
$source = new PDO('sqlite::memory:');
$source->exec('CREATE TABLE review_issues(id INTEGER PRIMARY KEY)');
$source->exec('INSERT INTO review_issues VALUES(1)');
$db = new PDO('sqlite::memory:');
$repo = new Decisions($db,$source);
$repo->install();
$repo->save(1,'propose_person',null,'Verify identity','operator-a',0);
ensure($repo->find(1)['reviewer_id'] === 'operator-a', 'Exact actor retained');
ensure($repo->audit(1)[0]['reviewer_id'] === 'operator-a', 'Exact audit actor retained');
$db->exec("CREATE TRIGGER reject_audit BEFORE INSERT ON inzet_review_decision_audit BEGIN SELECT RAISE(ABORT,'test audit failure'); END");
try {
  $repo->save(1,'exclude',null,'Rollback check','operator-b',1);
  throw new RuntimeException('Expected audit failure');
} catch (PDOException $expected) {}
ensure((int)$repo->find(1)['revision'] === 1 && $repo->find(1)['reviewer_id'] === 'operator-a', 'Decision rollback with failed audit');
ensure(count($repo->audit(1)) === 1, 'Audit rollback');
// Old INTEGER-affinity stores must fail closed before coercing an actor ID.
$old = new PDO('sqlite::memory:');
$old->exec('CREATE TABLE inzet_review_decisions(issue_id INTEGER PRIMARY KEY, action TEXT, target_id INTEGER, note TEXT, reviewer_id INTEGER NOT NULL, revision INTEGER, updated_at TEXT)');
$old->exec('CREATE TABLE inzet_review_decision_audit(id INTEGER PRIMARY KEY, issue_id INTEGER, action TEXT, target_id INTEGER, note TEXT, reviewer_id INTEGER NOT NULL, revision INTEGER, recorded_at TEXT)');
$legacy = new Decisions($old,$source);
try {
  $legacy->save(1,'propose_person',null,'Exact actor','01',0);
  throw new RuntimeException('Old INTEGER schema accepted');
} catch (LogicException $expected) {}
ensure($legacy->allDecisions() === [], 'Incompatible schema has no writes');
$db->exec('DROP TRIGGER reject_audit');
$repo->save(1,'propose_person',null,'Leading zero','01',1);
ensure($repo->find(1)['reviewer_id'] === '01', 'Leading zeros preserved');
$repo->save(1,'propose_person',null,'Numeric looking text','1e3',2);
ensure($repo->audit(1)[0]['reviewer_id'] === '1e3', 'Numeric-looking text is not coerced');
echo "Inzet exact reviewer, session binding and audit rollback: OK\n";
