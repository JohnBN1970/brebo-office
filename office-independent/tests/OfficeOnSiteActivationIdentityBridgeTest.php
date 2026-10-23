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

// Private verification record, not a user registration or a browser assertion.
$path = tempnam(sys_get_temp_dir(),'onsite-map-');
$document = ['version'=>1,'verified_by'=>'ci-reviewer','verified_at'=>'2026-01-01T00:00:00Z',
  'mappings'=>[['source_system'=>'legacy-onsite','source_person_id'=>'employee:42','office_person_id'=>101]]];
try {
  file_put_contents($path,json_encode($document,JSON_THROW_ON_ERROR));
  $loaded = OnSiteActivationIdentityBridge::loadVerifiedMap($path);
  if (OnSiteActivationIdentityBridge::verifiedPerson($loaded,101)!==101) throw new RuntimeException('Reviewed mapping failed');
  foreach ([42,102] as $person) {
    try { OnSiteActivationIdentityBridge::verifiedPerson($loaded,$person); throw new LogicException('Unmapped identity accepted'); }
    catch (RuntimeException $expected) {}
  }
  foreach ([
    array_replace($document,['verified_by'=>'']),
    array_replace($document,['verified_at'=>'2099-01-01T00:00:00Z']),
    array_replace($document,['mappings'=>[...$document['mappings'],['source_system'=>'drupal','source_person_id'=>'42','office_person_id'=>101]]]),
    array_replace($document,['mappings'=>[...$document['mappings'],['source_system'=>'legacy-onsite','source_person_id'=>'employee:42','office_person_id'=>42]]]),
  ] as $bad) {
    file_put_contents($path,json_encode($bad,JSON_THROW_ON_ERROR));
    try { OnSiteActivationIdentityBridge::loadVerifiedMap($path); throw new LogicException('Unverified/conflicting mapping accepted'); }
    catch (RuntimeException|InvalidArgumentException $expected) {}
  }
} finally { unlink($path); }
echo "Private verified source mapping provenance and conflicts OK\n";
