<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteExcelWeekRowExtractor.php';

use Brebo\Office\Inzet\OnSiteExcelWeekRowExtractor;

$result = (new OnSiteExcelWeekRowExtractor())->extract('wk35',[
  'B2'=>'Monteur A','C2'=>'Project A','D2'=>'8','E2'=>'4',
  'B3'=>'Monteur B','C3'=>'Project B','D3'=>'','E3'=>'tekst',
],['D2'=>'4 uur andere locatie','D3'=>'Materiaal opgehaald','E3'=>'Navragen'],
['monday'=>'D','tuesday'=>'E'],'B','C',2);
if (count($result['entries']) !== 2 || count($result['issues']) !== 2) {
  throw new RuntimeException('Unexpected day rows or exceptions.');
}
if ($result['entries'][0]['comment'] !== '4 uur andere locatie'
  || $result['entries'][0]['cell'] !== 'D2') {
  throw new RuntimeException('Day note not retained.');
}
if ($result['issues'][0]['reason'] !== 'comment_without_day_hours'
  || $result['issues'][1]['reason'] !== 'non_numeric_hours') {
  throw new RuntimeException('Missing exception for unallocated note or nonnumeric hours.');
}
echo "OnSite Excel week row extractor OK\n";
