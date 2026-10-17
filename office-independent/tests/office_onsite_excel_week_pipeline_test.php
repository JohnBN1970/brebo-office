<?php

declare(strict_types=1);

foreach (['OnSiteXlsxCellReader','OnSiteExcelWeekSheetDetector','OnSiteExcelWeekRowExtractor',
  'OnSiteWeeklyTimesheetImport','OnSiteWeekDateResolver','OnSiteExcelWeekImportPipeline'] as $name) {
  require_once dirname(__DIR__).'/src/Inzet/'.$name.'.php';
}
use Brebo\Office\Inzet\OnSiteExcelWeekImportPipeline;

if (!class_exists(ZipArchive::class) || !function_exists('simplexml_load_string')) {
  echo "XLSX pipeline test skipped: required PHP extensions unavailable\n";
  exit(0);
}
$tmp = tempnam(sys_get_temp_dir(),'onsite-pipeline-');
$zip = new ZipArchive();
if ($zip->open($tmp,ZipArchive::OVERWRITE) !== true) throw new RuntimeException('ZIP creation failed.');
$zip->addFromString('xl/workbook.xml','<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="wk35" sheetId="1" r:id="rId1"/></sheets></workbook>');
$zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
$xml = '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1">';
foreach (['D'=>'maandag','E'=>'dinsdag','F'=>'woensdag','G'=>'donderdag','H'=>'vrijdag'] as $col=>$day) {
  $xml .= '<c r="'.$col.'1" t="inlineStr"><is><t>'.$day.'</t></is></c>';
}
$xml .= '</row><row r="2"><c r="B2" t="inlineStr"><is><t>Monteur A</t></is></c><c r="C2" t="inlineStr"><is><t>Project A</t></is></c><c r="D2"><v>8</v></c></row></sheetData></worksheet>';
$zip->addFromString('xl/worksheets/sheet1.xml',$xml);
$zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="../comments1.xml"/></Relationships>');
$zip->addFromString('xl/comments1.xml','<?xml version="1.0"?><comments xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><commentList><comment ref="D2" authorId="0"><text><t>4 uur ander project</t></text></comment></commentList></comments>');
$zip->close();
try {
  $result = (new OnSiteExcelWeekImportPipeline())->import($tmp,[
    'wk35'=>['header_row'=>1,'first_data_row'=>2,'person_column'=>'B','project_column'=>'C']
  ], 2026);
  if (count($result['rows']) !== 1 || count($result['issues']) !== 0
    || $result['rows'][0]['minutes'] !== 480 || !$result['rows'][0]['review_required']
    || $result['rows'][0]['comment'] !== '4 uur ander project'
    || $result['rows'][0]['date'] !== '2026-08-24') {
    throw new RuntimeException('XLSX pipeline lost hours or notes.');
  }
} finally {
  unlink($tmp);
}
echo "OnSite XLSX pipeline OK\n";
