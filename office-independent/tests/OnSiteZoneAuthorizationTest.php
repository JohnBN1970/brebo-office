<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Inzet/OnSiteZoneAuthorization.php';
use Brebo\Office\Inzet\OnSiteZoneAuthorization;

$assignments = [
  ['id'=>'12','zones'=>[['id'=>'5','active'=>true],['id'=>'6','active'=>true]]],
  ['id'=>'13','zones'=>[['id'=>'8','active'=>true]]],
];
foreach ([['12','5'],['12','6'],['13','8']] as [$project,$zone]) {
  if (!OnSiteZoneAuthorization::allows($assignments,$project,$zone)) {
    throw new RuntimeException('Assigned zone rejected');
  }
}
foreach ([['12','8'],['13','5'],['14','5'],['12','0']] as [$project,$zone]) {
  if (OnSiteZoneAuthorization::allows($assignments,$project,$zone)) {
    throw new RuntimeException('Unassigned project-zone pair accepted');
  }
}
$assignments[] = ['id'=>'20','zones'=>[['id'=>'1','active'=>true],['id'=>'2','active'=>false]]];
if (!OnSiteZoneAuthorization::allows($assignments,'20','1')) throw new RuntimeException('Active zone rejected');
if (OnSiteZoneAuthorization::allows($assignments,'20','2')) throw new RuntimeException('Inactive zone accepted');
$assignments[] = ['id'=>'21','zones'=>[['id'=>'3']]];
if (OnSiteZoneAuthorization::allows($assignments,'21','3')) throw new RuntimeException('Zone without active state accepted');
echo "OnSiteZoneAuthorizationTest OK\n";
