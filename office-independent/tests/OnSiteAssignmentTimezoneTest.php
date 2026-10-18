<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Inzet/OnSiteAssignmentStore.php';
use Brebo\Office\Inzet\OnSiteAssignmentStore;
if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) {
  echo "SKIP: pdo_sqlite unavailable\n";
  exit(0);
}
$db = new PDO('sqlite::memory:');
$store = new OnSiteAssignmentStore($db);
$store->migrate();
$store->replaceProject('42','Test',[
  ['id'=>'7','name'=>'Zone','latitude'=>51.4,'longitude'=>4.5,'radius_metres'=>100,'active'=>true],
],[
  ['employee_id'=>'employee-1','starts_on'=>'2026-10-18','ends_on'=>'2026-10-18'],
]);
if (!$store->isAssigned('employee-1','42','7','2026-10-17T22:30:00+00:00')) {
  throw new RuntimeException('Local date assignment rejected');
}
if ($store->isAssigned('employee-1','42','7','2026-10-18T22:30:00+00:00')) {
  throw new RuntimeException('Next local day incorrectly authorized');
}
echo "OnSiteAssignmentTimezoneTest OK\n";
