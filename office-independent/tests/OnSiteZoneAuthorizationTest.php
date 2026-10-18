<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Inzet/OnSiteZoneAuthorization.php';
use Brebo\Office\Inzet\OnSiteZoneAuthorization;

$assignments = [
  ['id'=>'12','zones'=>[['id'=>'5'],['id'=>'6']]],
  ['id'=>'13','zones'=>[['id'=>'8']]],
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
echo "OnSiteZoneAuthorizationTest OK\n";
