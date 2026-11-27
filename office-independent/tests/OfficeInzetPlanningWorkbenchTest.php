<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetPlanningWorkbench.php';
$rows = [[
  'project_id'=>17,'person_id'=>8,'person_name'=>'Testmedewerker',
  'starts_at'=>'2026-10-19 20:00:00','ends_at'=>'2026-10-20 04:00:00','status'=>'confirmed',
]];
$html = \Brebo\Office\Inzet\OfficeInzetPlanningWorkbench::renderWeek($rows,[17],2026,43,[17=>'Testproject']);
if (substr_count($html,'Testproject') !== 2) throw new RuntimeException('Overnight shift not visible on both days');
if (!str_contains($html,'Testmedewerker') || !str_contains($html,'Weekplanning 2026-W43')) throw new RuntimeException('Roster missing personnel or week');
if (str_contains($html,'Project #17')) throw new RuntimeException('Verified project name not shown');
$namesakes = [
  ['project_id'=>17,'person_id'=>101,'person_name'=>'John Boon','personnel_number'=>'BREBO-001','starts_at'=>'2026-10-19 07:00:00','ends_at'=>'2026-10-19 15:00:00','status'=>'planned'],
  ['project_id'=>17,'person_id'=>102,'person_name'=>'John Boon','personnel_number'=>'BREBO-047','starts_at'=>'2026-10-19 08:00:00','ends_at'=>'2026-10-19 16:00:00','status'=>'planned'],
];
$namesakeHtml = \Brebo\Office\Inzet\OfficeInzetPlanningWorkbench::renderWeek($namesakes,[17],2026,43);
if (substr_count($namesakeHtml, '<th scope="row"') !== 2) throw new RuntimeException('Employees with same name merged');
if (!str_contains($namesakeHtml,'BREBO-001') || !str_contains($namesakeHtml,'BREBO-047')) throw new RuntimeException('Personnel numbers not displayed');
echo "Office personnel roster overnight and namesake tests OK\n";


$rows[0]['id'] = 42;
$editable = \Brebo\Office\Inzet\OfficeInzetPlanningWorkbench::renderWeek($rows,[17],2026,43,[17=>'Testproject'],[17],str_repeat('a',64),true);
$route = '/office-test/inzet/dienst/verplaatsen';
$router = file_get_contents(dirname(__DIR__).'/deployment/hostinger/office-test-index.php');
if (!str_contains($editable,'action="'.$route.'"') || !str_contains($router,"'".$route."' => 'office-inzet-shift-reschedule.php'") || str_contains($editable,'action="/office-test/inzet/dienst/wijzigen"')) {
  throw new RuntimeException('Reschedule form route does not match router');
}
echo "Office Inzet reschedule route OK\n";


$capacityRows = [
  ['project_id'=>17,'person_id'=>8,'person_name'=>'Testmedewerker','starts_at'=>'2026-10-19 06:00:00','ends_at'=>'2026-10-19 14:00:00','status'=>'planned'],
  ['project_id'=>18,'person_id'=>8,'person_name'=>'Testmedewerker','starts_at'=>'2026-10-20 06:00:00','ends_at'=>'2026-10-20 10:00:00','status'=>'planned'],
];
$capacityHtml = \Brebo\Office\Inzet\OfficeInzetPlanningWorkbench::renderWeek($capacityRows,[17,18],2026,43);
if (!str_contains($capacityHtml,'Gepland: 8,00 u (100%)')
  || !str_contains($capacityHtml,'Gepland: 4,00 u (50%)')
  || !str_contains($capacityHtml,'12,00 geplande uren')) {
  throw new RuntimeException('Daily capacity or weekly planned hours incorrect');
}
if (!str_contains($html,'Gepland: 4,00 u (50%)') || !str_contains($html,'8,00 geplande uren')) {
  throw new RuntimeException('Overnight hours not split across local days');
}
echo "Office Inzet daily capacity and week totals OK\n";
