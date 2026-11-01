<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OnSiteHoursReviewReport.php';
use Brebo\Office\Inzet\OnSiteHoursReviewReport;

$db = new PDO('sqlite::memory:');
$report = new OnSiteHoursReviewReport($db);
foreach ([['from'=>'2026-02-30'],['from'=>'2026-11-02','to'=>'2026-11-01'],['status'=>'invalid']] as $filters) {
  try {
    $report->filter([], $filters);
    throw new RuntimeException('Invalid report filter accepted.');
  } catch (InvalidArgumentException $expected) {
    // Correct rejection.
  }
}
echo "OnSiteHoursReviewReportFiltersTest OK\n";
