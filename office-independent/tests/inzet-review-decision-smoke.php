<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetReviewDecisionRepository.php';

use Brebo\Office\Inzet\OfficeInzetReviewDecisionRepository;

function check(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}

$source = new PDO('sqlite::memory:');
$source->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$source->exec('CREATE TABLE review_issues (id INTEGER PRIMARY KEY)');
$source->exec('INSERT INTO review_issues(id) VALUES (10)');
$decisions = new PDO('sqlite::memory:');
$repo = new OfficeInzetReviewDecisionRepository($decisions, $source);
$repo->install();
check($repo->counts(1) === ['open'=>1, 'reviewed'=>0], 'initial count');

$first = $repo->save(10, 'propose_person', null, 'Controleer naam', 1, 0);
check($first['revision'] === 1, 'first revision');
check($repo->counts(1) === ['open'=>0, 'reviewed'=>1], 'reviewed count');

$second = $repo->save(10, 'exclude', null, 'Geen urenregel', 1, 1);
check($second['revision'] === 2, 'second revision');
check((int)$decisions->query('SELECT COUNT(*) FROM inzet_review_decision_audit')->fetchColumn() === 2, 'audit count');
check(count($repo->audit(10)) === 2, 'audit history');
check((int)$repo->audit(10)[0]['revision'] === 2, 'latest audit first');
check(count($repo->allDecisions()) === 1, 'one current decision');

foreach ([
  fn()=>$repo->save(10, 'exclude', null, 'Conflict', 1, 1),
  fn()=>$repo->save(99, 'propose_person', null, '', 1, 0),
  fn()=>$repo->save(10, 'link_person', 1, '', 1, 2),
  fn()=>$repo->save(10, 'exclude', null, '', 1, 2),
] as $index=>$attempt) {
  try {
    $attempt();
    throw new RuntimeException('Expected rejection ' . $index);
  } catch (InvalidArgumentException|LogicException $expected) {
    // Expected invalid input, stale revision, or unverified Office target.
  } catch (RuntimeException $expected) {
    if ($index !== 0) throw $expected;
  }
}
check((int)$decisions->query('SELECT COUNT(*) FROM inzet_review_decision_audit')->fetchColumn() === 2, 'no rejected audit');
echo "Inzet decision repository smoke: OK\n";
