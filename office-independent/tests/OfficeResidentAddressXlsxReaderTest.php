<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Buildings/OfficeResidentAddressXlsxReader.php';
require_once dirname(__DIR__) . '/src/Buildings/OfficeResidentAddressIntakeAdapter.php';
use Brebo\Office\Buildings\OfficeResidentAddressXlsxReader as Xlsx;
use Brebo\Office\Buildings\OfficeResidentAddressIntakeAdapter as Adapter;
if (!class_exists(ZipArchive::class) || !class_exists(XMLReader::class)) {
  throw new RuntimeException('XLSX test requires zip and XMLReader');
}
$file=tempnam(sys_get_temp_dir(),'office-xlsx-');
$zip=new ZipArchive();
if ($zip->open($file,ZipArchive::OVERWRITE)!==true) throw new RuntimeException('Cannot create XLSX fixture');
$zip->addFromString('xl/worksheets/sheet1.xml','<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Straatnaam</t></is></c><c r="B1" t="inlineStr"><is><t>Huisnummer</t></is></c><c r="C1" t="inlineStr"><is><t>Postcode</t></is></c><c r="D1" t="inlineStr"><is><t>Woonplaats</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>Kerkstraat</t></is></c><c r="B2"><v>10</v></c><c r="C2" t="inlineStr"><is><t>1234AB</t></is></c><c r="D2" t="inlineStr"><is><t>Utrecht</t></is></c></row></sheetData></worksheet>');
$zip->close();
try {
  $rows=Xlsx::read($file);
  $result=Adapter::fromExtractedRows($rows,'xlsx','xlsx-fixture');
  if (count($result['proposals'])!==1 || $result['proposals'][0]['proposal']['source']['id']!=='xlsx-fixture:row:2') {
    throw new RuntimeException('XLSX to proposal failed');
  }
} finally { unlink($file); }
echo "OFFICE_RESIDENT_ADDRESS_XLSX_READER_OK\n";
