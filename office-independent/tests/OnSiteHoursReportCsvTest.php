<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OnSiteHoursReportCsv.php';

use Brebo\Office\Inzet\OnSiteHoursReportCsv;

$csv = OnSiteHoursReportCsv::render([[
  'project_id'=>1,'person_id'=>2,'started_at'=>'2026-11-01 08:00:00',
  'ended_at'=>'2026-11-01 09:00:00','review_status'=>'approved',
  'original_seconds'=>3600,'accepted_seconds'=>1800,'difference_seconds'=>-1800,
  'review_reason'=>'=HYPERLINK("https://example.com")',
]]);
if (!str_starts_with($csv, "\xEF\xBB\xBF") || !str_contains($csv, '0,50')
  || !str_contains($csv, "'=HYPERLINK")) {
  throw new RuntimeException('CSV export regression.');
}
echo "OnSiteHoursReportCsvTest OK\n";
