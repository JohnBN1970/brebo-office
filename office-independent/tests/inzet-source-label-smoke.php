<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetImportReviewReadRepository.php';

use Brebo\Office\Inzet\OfficeInzetImportReviewReadRepository;

function assertReview(bool $ok, string $message): void {
  if (!$ok) throw new RuntimeException($message);
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE inzet_source_workbench (
  source_row_id INTEGER PRIMARY KEY, person_label TEXT, project_label TEXT, row_number INTEGER
)');
$db->exec('CREATE TABLE review_issues (id INTEGER PRIMARY KEY, sheet_id INTEGER, source_row_id INTEGER, details TEXT, issue_code TEXT)');
$db->exec('CREATE TABLE worksheets (id INTEGER PRIMARY KEY, sheet_name TEXT)');
$db->exec('CREATE TABLE inzet_person_candidates (label_id INTEGER, raw_label TEXT)');
$db->exec('CREATE TABLE inzet_project_candidates (label_id INTEGER, raw_label TEXT)');
$db->exec("INSERT INTO inzet_source_workbench VALUES
  (1, 'Anna', 'Project A', 4),
  (2, 'Anna ', 'Project A', 5),
  (3, 'Bram', NULL, 6),
  (4, ' ', 'Project B', 7)");
$db->exec("INSERT INTO worksheets VALUES (1, 'week 01')");
$db->exec("INSERT INTO review_issues VALUES (1, 1, 1, 'B4', 'missing_hours')");

$report = (new OfficeInzetImportReviewReadRepository($db))->summary();
assertReview($report['row_count'] === 4, 'source count');
assertReview($report['person_label_count'] === 2, 'person names deduplicated');
assertReview($report['project_label_count'] === 2, 'project labels deduplicated');
assertReview($report['person_labels'] === ['Anna', 'Bram'], 'original names preserved');
assertReview($report['project_labels'] === ['Project A', 'Project B'], 'original projects preserved');
assertReview($report['warning_count'] === 1, 'issue count');
assertReview(count($report['warnings']) === 1, 'one issue row');
assertReview((int)$report['warnings'][0]['issue_id'] === 1, 'stable issue id');
assertReview($report['warnings'][0]['person'] === 'Anna', 'source person context');
assertReview($report['warnings'][0]['project'] === 'Project A', 'source project context');
assertReview((int)$report['warnings'][0]['source_row'] === 4, 'source row context');
assertReview($report['approved'] === false && $report['invoice_eligible'] === false, 'not approved');
echo "Inzet source label smoke: OK\n";
