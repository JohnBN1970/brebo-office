<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Buildings/OfficeResidentAddressXlsxReader.php';
use Brebo\Office\Buildings\OfficeResidentAddressXlsxReader as Reader;
if (!class_exists(ZipArchive::class) || !class_exists(XMLReader::class)) throw new RuntimeException('ZIP and XMLReader required');
function rejectedArchive(array $entries, string $reason): void {
  $file = tempnam(sys_get_temp_dir(), 'office-xlsx-security-');
  $zip = new ZipArchive();
  if ($zip->open($file, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Fixture create failed');
  foreach ($entries as $name => $data) $zip->addFromString($name, $data);
  $zip->close();
  try {
    try {
      Reader::read($file);
      throw new RuntimeException('Unsafe archive accepted: '.$reason);
    } catch (InvalidArgumentException|LengthException $e) {
      if (str_starts_with($e->getMessage(), 'Unsafe archive accepted')) throw $e;
    }
  } finally { unlink($file); }
}
$sheet = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData/></worksheet>';
$base = ['[Content_Types].xml'=>'<Types/>', 'xl/workbook.xml'=>'<workbook/>', 'xl/worksheets/sheet1.xml'=>$sheet];
rejectedArchive(['xl/worksheets/sheet1.xml'=>$sheet], 'missing workbook package');
rejectedArchive($base + ['xl/../escape.xml'=>'bad'], 'path traversal');
rejectedArchive($base + ['xl/worksheets/huge.xml'=>str_repeat('A', 13 * 1024 * 1024)], 'oversized expansion');
echo "OFFICE_RESIDENT_ADDRESS_XLSX_SECURITY_OK\n";
