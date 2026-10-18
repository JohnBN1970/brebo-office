<?php
declare(strict_types=1);
foreach (['OnSitePlanningProjection','OnSiteProjectionWriter','OnSitePlanningSynchronizer','OnSitePlanningImport','OnSiteBootstrapRepository'] as $name) {
  require_once __DIR__ . '/../src/Inzet/' . $name . '.php';
}
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) throw new RuntimeException('pdo_sqlite required');
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE onsite_employees (employee_id TEXT PRIMARY KEY,display_name TEXT,language TEXT,active INTEGER)');
$db->exec('CREATE TABLE onsite_projects (project_id TEXT PRIMARY KEY,name TEXT,active INTEGER)');
$db->exec('CREATE TABLE onsite_zones (zone_id TEXT,project_id TEXT,name TEXT,latitude REAL,longitude REAL,radius_metres REAL,active INTEGER)');
$db->exec('CREATE TABLE onsite_project_zone_assignments (employee_id TEXT,project_id TEXT,zone_id TEXT,valid_from TEXT,valid_until TEXT,active INTEGER,PRIMARY KEY(employee_id,project_id,zone_id,valid_from))');
$db->exec("INSERT INTO onsite_employees VALUES ('e','Employee','nl',1)");
$db->exec("INSERT INTO onsite_projects VALUES ('37','Project',1)");
$db->exec("INSERT INTO onsite_zones VALUES ('z','37','Zone',51.3,4.4,100,1)");
$writer = new Brebo\Office\Inzet\OnSiteProjectionWriter($db);
$sync = new Brebo\Office\Inzet\OnSitePlanningSynchronizer(new Brebo\Office\Inzet\OnSitePlanningProjection(), $writer);
$row = ['project_id'=>37,'user_id'=>12,'assignment_status'=>'planned','plan_date'=>'2026-10-18','start'=>'07:00','end'=>'16:00'];
$import = new Brebo\Office\Inzet\OnSitePlanningImport($sync);
$employeeResolver = static fn(int $uid): ?string => $uid === 12 ? 'e' : null;
$zoneResolver = static fn(int $pid): ?string => $pid === 37 ? 'z' : null;
if ($import->import($row,$employeeResolver,$zoneResolver)) throw new RuntimeException('Unconfirmed import accepted');
$row['assignment_status'] = 'confirmed';
try {
  $import->import($row,static fn(int $uid): ?string => null,$zoneResolver);
  throw new RuntimeException('Missing verified identity mapping accepted');
} catch (InvalidArgumentException $expected) {}
if (!$import->import($row,$employeeResolver,$zoneResolver)) throw new RuntimeException('Verified canonical import failed');
$row['assignment_status'] = 'planned';
if ($sync->stage($row,'e','z')) throw new RuntimeException('Unconfirmed planning staged');
$row['assignment_status'] = 'confirmed';
if (!$sync->stage($row,'e','z')) throw new RuntimeException('Confirmed planning not staged');
if (!$sync->stage($row,'e','z')) throw new RuntimeException('Repeated identical planning should be idempotent');
if ((int)$db->query('SELECT COUNT(*) FROM onsite_project_zone_assignments')->fetchColumn() !== 1)
 throw new RuntimeException('Repeated planning created duplicate assignment');
$row['end'] = '15:00';
try {
  $sync->stage($row,'e','z');
  throw new RuntimeException('Conflicting planning revision was silently accepted');
} catch (DomainException $expected) {
  // Explicit revision workflow required.
}
$row['end'] = '16:00';
$bootstrap = new Brebo\Office\Inzet\OnSiteBootstrapRepository($db);
if ($bootstrap->forEmployee('e',new DateTimeImmutable('2026-10-18T09:00:00+02:00'))['projects'] !== [])
 throw new RuntimeException('Staged planning became active without review');
echo "OnSitePlanningSynchronizerTest OK\n";
