<?php
declare(strict_types=1);
foreach (['OnSitePlanningProjection','OnSiteProjectionWriter','OnSitePlanningSynchronizer','OnSitePlanningImport'] as $name) {
  require_once __DIR__ . '/../src/Inzet/' . $name . '.php';
}
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) throw new RuntimeException('pdo_sqlite required');
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE onsite_project_zone_assignments (employee_id TEXT,project_id TEXT,zone_id TEXT,valid_from TEXT,valid_until TEXT,active INTEGER,PRIMARY KEY(employee_id,project_id,zone_id,valid_from))');
$import = new Brebo\Office\Inzet\OnSitePlanningImport(new Brebo\Office\Inzet\OnSitePlanningSynchronizer(
  new Brebo\Office\Inzet\OnSitePlanningProjection(),new Brebo\Office\Inzet\OnSiteProjectionWriter($db)
));
$row = ['project_id'=>37,'user_id'=>12,'assignment_status'=>'confirmed','plan_date'=>'2026-10-18','start'=>'07:00','end'=>'16:00'];
$employee = static fn(int $id): ?string => $id === 12 ? 'employee-12' : null;
$zone = static fn(int $id): ?string => $id === 37 ? 'zone-37' : null;
if (!$import->import($row,$employee,$zone)) throw new RuntimeException('Verified Office import rejected');
if ((int)$db->query('SELECT COUNT(*) FROM onsite_project_zone_assignments')->fetchColumn() !== 1) throw new RuntimeException('Missing staged assignment');
if (!$import->import($row,$employee,$zone)) throw new RuntimeException('Idempotent Office import rejected');
if ((int)$db->query('SELECT COUNT(*) FROM onsite_project_zone_assignments')->fetchColumn() !== 1) throw new RuntimeException('Duplicate staged assignment');
try {
  $import->import($row,static fn(int $id): ?string => null,$zone);
  throw new RuntimeException('Unmapped Office employee accepted');
} catch (InvalidArgumentException $expected) {}
$row['assignment_status'] = 'planned';
if ($import->import($row,$employee,$zone)) throw new RuntimeException('Unconfirmed planning accepted');
echo "OnSitePlanningImportTest OK\n";
