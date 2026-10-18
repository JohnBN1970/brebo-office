<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Inzet/OnSiteAssignmentRepository.php';
use Brebo\Office\Inzet\OnSiteAssignmentRepository;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  throw new RuntimeException('pdo_sqlite is required for OnSite assignment tests');
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$assignments = new OnSiteAssignmentRepository($db);
$assignments->migrate();
$insert = $db->prepare('INSERT INTO onsite_project_zone_assignments
 (employee_id, project_id, zone_id, valid_from, valid_until, active) VALUES (?,?,?,?,?,?)');
$insert->execute(['john.boon','37','zone-100m','2026-10-01T00:00:00+00:00','2026-11-01T00:00:00+00:00',1]);
$insert->execute(['john.boon','37','inactive','2026-10-01T00:00:00+00:00',null,0]);
foreach ([
  ['john.boon','37','zone-100m','2026-10-18T09:00:00+00:00',true],
  ['john.boon','37','zone-100m','2026-09-30T23:00:00+00:00',false],
  ['john.boon','37','zone-100m','2026-11-01T00:00:00+00:00',false],
  ['john.boon','37','inactive','2026-10-18T09:00:00+00:00',false],
  ['someone.else','37','zone-100m','2026-10-18T09:00:00+00:00',false],
  ['john.boon','38','zone-100m','2026-10-18T09:00:00+00:00',false],
  ['john.boon','37','zone-100m','bad timestamp',false],
] as [$employee,$project,$zone,$at,$expected]) {
  if ($assignments->allows($employee,$project,$zone,$at) !== $expected) {
    throw new RuntimeException('Unexpected authorization result');
  }
}
echo "OnSiteAssignmentRepositoryTest OK\n";
