<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Inzet/OnSitePlanningProjection.php';
$projection = new Brebo\Office\Inzet\OnSitePlanningProjection();
$row = ['project_id'=>37,'user_id'=>12,'assignment_status'=>'confirmed','plan_date'=>'2026-10-18','start'=>'07:00','end'=>'16:00'];
$result = $projection->map($row,'verified-employee','verified-zone');
if ($result === null || $result['project_id'] !== '37' || $result['from']->format('H:i') !== '07:00'
  || $result['until']->format('H:i') !== '16:00') throw new RuntimeException('Confirmed planning not mapped');
foreach (['planned','cancelled'] as $status) {
  $row['assignment_status'] = $status;
  if ($projection->map($row,'verified-employee','verified-zone') !== null) throw new RuntimeException('Unconfirmed planning published');
}
$row['assignment_status'] = 'confirmed';
foreach (['25:00','16:00'] as $start) {
  $row['start'] = $start;
  if ($projection->map($row,'verified-employee','verified-zone') !== null) throw new RuntimeException('Invalid planning window published');
}
$row['start'] = '07:00';
$row['end'] = '16:00';
$row['plan_date'] = '2026-10-25';
if ($projection->map($row,'verified-employee','verified-zone') !== null)
  throw new RuntimeException('DST transition day must require manual planning review');
echo "OnSitePlanningProjectionTest OK\n";
