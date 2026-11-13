<?php
declare(strict_types=1);
$path=dirname(__DIR__).'/src/Inzet/OfficeExternalTimesheetReviewedImport.php';
$source=file_get_contents($path);
foreach ([
    'Caller-owned transaction required',
    'Explicit unique reviewed line references required',
    'Reviewed line not found in source',
    'OfficeExternalTimesheetCsvReader::preview',
    'importInsideTransaction(',
] as $fragment) {
    if (!str_contains($source,$fragment)) throw new RuntimeException('Missing reviewed import guard: '.$fragment);
}
if (str_contains($source,'commit()') || str_contains($source,'beginTransaction()')) {
    throw new RuntimeException('Reviewed importer must not own transaction');
}
echo "EXTERNAL_TIMESHEET_REVIEWED_IMPORT_CONTRACT_OK\n";
