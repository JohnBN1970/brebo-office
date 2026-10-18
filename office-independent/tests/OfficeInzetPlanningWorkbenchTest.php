<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetPlanningWorkbench.php';
$rows = [[
  'project_id'=>17,'person_id'=>8,'person_name'=>'Testmedewerker',
  'starts_at'=>'2026-10-19 22:00:00','ends_at'=>'2026-10-20 06:00:00','status'=>'confirmed',
]];
$html = \Brebo\Office\Inzet\OfficeInzetPlanningWorkbench::renderWeek($rows,[17],2026,43,[17=>'Testproject']);
if (substr_count($html,'Testproject') !== 2) throw new RuntimeException('Overnight shift not visible on both days');
if (!str_contains($html,'Testmedewerker') || !str_contains($html,'Weekplanning 2026-W43')) throw new RuntimeException('Roster missing personnel or week');
if (str_contains($html,'Project #17')) throw new RuntimeException('Verified project name not shown');
echo "Office personnel roster overnight test OK\n";
