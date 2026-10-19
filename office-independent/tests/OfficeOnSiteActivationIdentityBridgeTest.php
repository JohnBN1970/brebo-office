<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetIdentityMappingGuard.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteActivationIdentityBridge.php';

use Brebo\Office\Inzet\OfficeInzetIdentityMappingGuard;
use Brebo\Office\Inzet\OnSiteActivationIdentityBridge;

$map = OfficeInzetIdentityMappingGuard::validate([
  ['source_system'=>'drupal','source_person_id'=>'42','office_person_id'=>101],
]);
$now = 1792400000;
if (OnSiteActivationIdentityBridge::officePerson($map,['uid'=>42,'created'=>$now-60],$now) !== 101) {
  throw new RuntimeException('Verified activation mapping failed');
}
foreach ([
  [['uid'=>43,'created'=>$now-60],$now],
  [['uid'=>42,'created'=>$now-604800],$now],
  [['uid'=>42,'created'=>$now+1],$now],
  [['uid'=>42,'created'=>0],$now],
] as [$record,$time]) {
  try {
    OnSiteActivationIdentityBridge::officePerson($map,$record,$time);
    throw new RuntimeException('Unverified activation identity accepted');
  } catch (InvalidArgumentException|RuntimeException $expected) {
    if ($expected->getMessage() === 'Unverified activation identity accepted') throw $expected;
  }
}
echo "OnSite Drupal to Office activation identity contract OK\n";
