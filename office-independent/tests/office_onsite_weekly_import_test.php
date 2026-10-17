<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteWeeklyTimesheetImport.php';

use Brebo\Office\Inzet\OnSiteWeeklyTimesheetImport;

$import = new OnSiteWeeklyTimesheetImport();
$result = $import->normalize([
  ['sheet'=>'wk35','cell'=>'C4','person'=>'Monteur A','project_label'=>'Project A',
   'weekday'=>'maandag','hours'=>8,'comment'=>'3 uur naar ander project'],
  ['sheet'=>'wk35','cell'=>'D4','person'=>'Monteur A','project_label'=>'Project A',
   'weekday'=>'dinsdag','hours'=>7.5],
  ['sheet'=>'wk35','cell'=>'E4','person'=>'Monteur A','project_label'=>'',
   'weekday'=>'woensdag','hours'=>8],
  ['sheet'=>'wk35','cell'=>'F4','person'=>'Monteur A','project_label'=>'Project A',
   'weekday'=>'donderdag','hours'=>'n.v.t.'],
]);
if (count($result['rows']) !== 3 || count($result['issues']) !== 1) throw new RuntimeException('Import row counts incorrect.');
if ($result['rows'][0]['minutes'] !== 480 || !$result['rows'][0]['review_required']
  || $result['rows'][0]['comment'] !== '3 uur naar ander project') throw new RuntimeException('Comment not preserved.');
if ($result['rows'][1]['minutes'] !== 450 || $result['rows'][1]['review_required']) throw new RuntimeException('Simple hours incorrect.');
if ($result['rows'][2]['review_reason'] !== 'project_unresolved') throw new RuntimeException('Unresolved project silently accepted.');
echo "OnSite weekly Excel normalization OK\n";
