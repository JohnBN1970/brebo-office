<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteExcelWeekSheetDetector.php';

use Brebo\Office\Inzet\OnSiteExcelWeekSheetDetector;

$detector = new OnSiteExcelWeekSheetDetector();
$headers = ['A'=>'Naam','B'=>'Project/opdrachtgever','C'=>'maandag','D'=>'dinsdag',
  'E'=>'woensdag','F'=>'donderdag ','G'=>'vrijdag','H'=>'Zaterdag',
  'I'=>'Totaal uren','J'=>'uurtarief inkoop'];
$days = $detector->dayColumns($headers);
if (count($days) !== 6 || $days['monday'] !== 'C' || $days['saturday'] !== 'H') {
  throw new RuntimeException('Actual supplier workbook headers not detected.');
}
$shifted = $detector->dayColumns(['E'=>'Maandag','F'=>'Dinsdag','G'=>'Totaal uren']);
if ($shifted !== ['monday'=>'E','tuesday'=>'F']) throw new RuntimeException('Shifted weekday headers not detected.');
try {
  $detector->dayColumns(['C'=>'maandag','D'=>'Monday']);
  throw new RuntimeException('Duplicate weekday silently accepted.');
} catch (DomainException $expected) {}
echo "OnSite Excel week detector OK\n";
