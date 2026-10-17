<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteXlsxCellReader.php';

use Brebo\Office\Inzet\OnSiteXlsxCellReader;

if (!class_exists(ZipArchive::class) || !function_exists('simplexml_load_string')) {
  echo "XLSX reader test skipped: ZipArchive/SimpleXML unavailable\n";
  exit(0);
}
$tmp = tempnam(sys_get_temp_dir(),'onsite-xlsx-');
$zip = new ZipArchive();
if ($zip->open($tmp,ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Fixture ZIP failed.');
$zip->addFromString('xl/sharedStrings.xml','<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>maandag</t></si></sst>');
$zip->addFromString('xl/worksheets/sheet1.xml','<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="C1" t="s"><v>0</v></c></row><row r="2"><c r="C2"><v>8</v></c></row></sheetData></worksheet>');
$zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="../comments1.xml"/></Relationships>');
$zip->addFromString('xl/comments1.xml','<?xml version="1.0"?><comments xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><commentList><comment ref="C2" authorId="0"><text><t>3 uur project B</t></text></comment></commentList></comments>');
$zip->close();
try {
  $result = (new OnSiteXlsxCellReader())->read($tmp);
  if (($result['cells']['sheet1']['C1'] ?? null) !== 'maandag'
    || ($result['cells']['sheet1']['C2'] ?? null) !== '8'
    || ($result['comments']['sheet1']['C2'] ?? null) !== '3 uur project B') {
    throw new RuntimeException('XLSX values or notes not extracted.');
  }
} finally {
  unlink($tmp);
}
echo "OnSite XLSX reader OK\n";
