<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetReviewDecisionRepository.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetImportReviewReadRepository.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetSourceReviewWorkbench.php';

use Brebo\Office\Inzet\OfficeInzetReviewDecisionRepository;
use Brebo\Office\Inzet\OfficeInzetImportReviewReadRepository;
use Brebo\Office\Inzet\OfficeInzetSourceReviewWorkbench;

function assertReviewCycle(bool $ok, string $why): void {
  if (!$ok) throw new RuntimeException($why);
}
$source = new PDO('sqlite::memory:');
$source->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$source->exec('CREATE TABLE worksheets (id INTEGER PRIMARY KEY, sheet_name TEXT)');
$source->exec('CREATE TABLE inzet_source_workbench (source_row_id INTEGER PRIMARY KEY, row_number INTEGER, person_label TEXT, project_label TEXT)');
$source->exec('CREATE TABLE review_issues (id INTEGER PRIMARY KEY, sheet_id INTEGER, source_row_id INTEGER, details TEXT, issue_code TEXT)');
$source->exec("INSERT INTO worksheets VALUES (1, 'week 02')");
$source->exec("INSERT INTO inzet_source_workbench VALUES (1, 5, 'Anna', 'Project A')");
$source->exec("INSERT INTO review_issues VALUES (7, 1, 1, 'B5', 'missing_hours')");
$store = new PDO('sqlite::memory:');
$repo = new OfficeInzetReviewDecisionRepository($store, $source);
$repo->install();
$before = (new OfficeInzetImportReviewReadRepository($source))->summary();
assertReviewCycle($before['warning_count'] === 1, 'source issue count');
assertReviewCycle(str_contains(OfficeInzetSourceReviewWorkbench::render($before), '<td>Open</td>'), 'initially open');
$first = $repo->save(7, 'propose_person', null, 'Naam verifiëren', 42, 0);
assertReviewCycle($first['revision'] === 1, 'revision one');
$index = [7=>$repo->find(7)];
$html = OfficeInzetSourceReviewWorkbench::render($before, [], $index, 'csrf-test');
assertReviewCycle(str_contains($html, '<td>propose_person</td>'), 'saved decision visible');
assertReviewCycle(str_contains($html, 'name="revision" value="1"'), 'revision visible');
$second = $repo->save(7, 'exclude', null, 'Geen urenregel', 42, 1);
assertReviewCycle($second['revision'] === 2, 'revision two');
assertReviewCycle(count($repo->audit(7)) === 2, 'audit persisted');
try {
  $repo->save(7, 'exclude', null, 'Stale', 42, 1);
  throw new RuntimeException('Stale update accepted');
} catch (RuntimeException $expected) {
  assertReviewCycle(str_contains($expected->getMessage(), 'changed'), 'stale revision rejected');
}
assertReviewCycle($repo->counts(1) === ['open'=>0, 'reviewed'=>1], 'review counts');
assertReviewCycle((int)$source->query('SELECT COUNT(*) FROM review_issues')->fetchColumn() === 1, 'source untouched');
assertReviewCycle((string)$source->query('SELECT person_label FROM inzet_source_workbench')->fetchColumn() === 'Anna', 'source labels untouched');
echo "Inzet review cycle smoke: OK\n";
