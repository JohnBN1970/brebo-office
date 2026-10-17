<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetImportReviewReadRepository.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetSourceReviewWorkbench.php';

use Brebo\Office\Inzet\OfficeInzetImportReviewReadRepository;
use Brebo\Office\Inzet\OfficeInzetSourceReviewWorkbench;

function checkReview(bool $ok, string $message): void {
  if (!$ok) throw new RuntimeException($message);
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE worksheets (id INTEGER PRIMARY KEY, sheet_name TEXT)');
$db->exec('CREATE TABLE inzet_source_workbench (source_row_id INTEGER PRIMARY KEY, row_number INTEGER, person_label TEXT, project_label TEXT)');
$db->exec('CREATE TABLE review_issues (id INTEGER PRIMARY KEY, sheet_id INTEGER, source_row_id INTEGER, details TEXT, issue_code TEXT)');
$db->exec("INSERT INTO worksheets VALUES (1, 'week 02'), (2, 'week 03')");
$db->exec("INSERT INTO inzet_source_workbench VALUES (1, 7, 'Anna & Co', 'Project <A>'), (2, 8, 'Bram', 'Project B')");
$db->exec("INSERT INTO review_issues VALUES (10, 1, 1, 'D7', 'missing_hours'), (11, 2, 2, 'D8', 'invalid_hours')");
$report = (new OfficeInzetImportReviewReadRepository($db))->summary();
checkReview($report['warning_count'] === 2, 'issue count');
checkReview(count($report['warnings']) === 2, 'no duplicate issues');
$html = OfficeInzetSourceReviewWorkbench::render($report, ['person'=>'Anna']);
checkReview(str_contains($html, '1 afwijkingen binnen de gekozen filters'), 'filtered count');
checkReview(str_contains($html, 'Anna &amp; Co'), 'escaped employee');
checkReview(str_contains($html, 'Project &lt;A&gt;'), 'escaped project');
checkReview(!str_contains($html, '<td>Bram</td>'), 'filtered employee absent');
checkReview(str_contains($html, '<td>10</td>'), 'issue id displayed');
$html = OfficeInzetSourceReviewWorkbench::render($report, ['sheet'=>'week 03']);
checkReview(str_contains($html, '<td>Bram</td>'), 'week filter');
checkReview(!str_contains($html, '<td>Anna &amp; Co</td>'), 'other week excluded');
$html = OfficeInzetSourceReviewWorkbench::render($report, [], [10=>['action'=>'propose_person']]);
checkReview(str_contains($html, '<td>propose_person</td>'), 'decision shown');
checkReview(str_contains($html, '<td>Open</td>'), 'unreviewed issue open');
$html = OfficeInzetSourceReviewWorkbench::render($report, [], [], 'test-csrf-token');
checkReview(substr_count($html, 'method="post"') === 2, 'one form per issue');
checkReview(str_contains($html, 'name="csrf" value="test-csrf-token"'), 'CSRF included');
checkReview(str_contains($html, 'name="issue_id" value="10"'), 'issue id included');
checkReview(str_contains($html, 'name="revision" value="0"'), 'new decision revision');
checkReview(str_contains($html, 'value="propose_person"'), 'propose person option');
checkReview(str_contains($html, 'value="propose_project"'), 'propose project option');
checkReview(str_contains($html, 'value="exclude"'), 'exclude option');
checkReview(!str_contains($html, 'value="link_person"'), 'no unverified person linking');
$html = OfficeInzetSourceReviewWorkbench::render($report, [], [10=>['action'=>'exclude','revision'=>3]], 'test-csrf-token');
checkReview(str_contains($html, 'name="revision" value="3"'), 'existing decision revision');
$html = OfficeInzetSourceReviewWorkbench::render($report, [], [], null);
checkReview(!str_contains($html, 'method="post"'), 'no forms without configured review store');
echo "Inzet review filters smoke: OK\n";
