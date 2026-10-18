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
$zones = [['id'=>'7','name'=>'Zone','latitude'=>51.4,'longitude'=>4.5,'radius_metres'=>100,'active'=>true]];
$people = [['employee_id'=>'employee-1','starts_on'=>'2026-10-18','ends_on'=>'2026-10-19']];
$store->replaceProject('42','Testproject',$zones,$people);
if (count($store->projectsForEmployee('employee-1','2026-10-18')) !== 1) {
  throw new RuntimeException('Projection missing');
}
$store->replaceProject('42','Testproject',[],[]);
if ($store->projectsForEmployee('employee-1','2026-10-18') !== []) {
  throw new RuntimeException('Removed assignments still visible');
}
$store->replaceProject('42','Testproject',$zones,$people);
try {
  $store->replaceProject('42','Corrupt',[['id'=>'8','name'=>'Bad','latitude'=>999,'longitude'=>4.5,'radius_metres'=>100]],[]);
  throw new RuntimeException('Invalid projection accepted');
} catch (InvalidArgumentException $error) {
  if ($error->getMessage() === 'Invalid projection accepted') throw $error;
}
if (!$store->isAssigned('employee-1','42','7','2026-10-18T08:00:00+02:00')) {
  throw new RuntimeException('Rejected update destroyed valid projection');
}
echo "OnSiteProjectionRefreshTest OK\n";
