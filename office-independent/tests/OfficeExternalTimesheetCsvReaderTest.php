<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OfficeExternalTimesheetCsvReader.php';
use Brebo\Office\Inzet\OfficeExternalTimesheetCsvReader as Reader;
$csv="project_id;person_id;work_date;claimed_hours;supplier_reference\n23;7;2026-11-13;8,00;sub-1\n";
$preview=Reader::preview($csv);
if (count($preview['rows'])!==1 || $preview['rows'][0]['claimed_seconds']!==28800
    || !$preview['requires_review'] || !$preview['rows'][0]['requires_review']) {
    throw new RuntimeException('Preview must be 8h and require manual review');
}
if ($preview['source_document_sha256']!==hash('sha256',$csv)) throw new RuntimeException('Document hash mismatch');
foreach ([
    "project_id;person_id;work_date;claimed_hours;supplier_reference\n23;7;2026-11-13;25;sub-1\n",
    "project_id;person_id;work_date;claimed_hours;supplier_reference\n23;7;2026-02-30;8;sub-1\n",
    "project_id;person_id;work_date;claimed_hours;supplier_reference\n23;unknown;2026-11-13;8;sub-1\n",
] as $bad) {
    try { Reader::preview($bad); throw new RuntimeException('Bad CSV accepted'); }
    catch (DomainException $expected) {}
}
echo "EXTERNAL_TIMESHEET_CSV_PREVIEW_OK\n";
