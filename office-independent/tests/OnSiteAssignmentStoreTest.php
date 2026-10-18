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
$db->exec("INSERT INTO onsite_projects VALUES ('42','OnSite test')");
$db->exec("INSERT INTO onsite_zones VALUES ('7','42','Test zone',51.4,4.5,100,1)");
$db->exec("INSERT INTO onsite_assignments VALUES ('employee-1','42','2026-10-18','2026-10-19')");
$projects = $store->projectsForEmployee('employee-1','2026-10-18');
if (count($projects)!==1 || count($projects[0]['zones'])!==1) {
  throw new RuntimeException('Assignment projection failed');
}
if (!$store->isAssigned('employee-1','42','7','2026-10-18T08:00:00+02:00')) {
  throw new RuntimeException('Valid assignment rejected');
}
if ($store->isAssigned('employee-2','42','7','2026-10-18T08:00:00+02:00')
  || $store->isAssigned('employee-1','42','8','2026-10-18T08:00:00+02:00')
  || $store->isAssigned('employee-1','42','7','2026-10-20T08:00:00+02:00')) {
  throw new RuntimeException('Unauthorized assignment accepted');
}
echo "OnSiteAssignmentStoreTest OK\n";
