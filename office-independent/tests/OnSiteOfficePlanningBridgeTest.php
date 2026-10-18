<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Inzet/OnSiteProjectionWriter.php';
require_once __DIR__ . '/../src/Inzet/OnSiteOfficePlanningBridge.php';
if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) throw new RuntimeException('pdo_sqlite required');
$db = new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE onsite_project_zone_assignments (employee_id TEXT,project_id TEXT,zone_id TEXT,valid_from TEXT,valid_until TEXT,active INTEGER,PRIMARY KEY(employee_id,project_id,zone_id,valid_from))');
$bridge = new Brebo\Office\Inzet\OnSiteOfficePlanningBridge(new Brebo\Office\Inzet\OnSiteProjectionWriter($db));
$from = new DateTimeImmutable('2026-10-18T07:00:00+02:00');
$until = new DateTimeImmutable('2026-10-18T16:00:00+02:00');
if ($bridge->stageConfirmed('employee-john','office-project-uuid','office-zone-uuid','planned',$from,$until)) throw new RuntimeException('Planned shift staged');
if (!$bridge->stageConfirmed('employee-john','office-project-uuid','office-zone-uuid','confirmed',$from,$until)) throw new RuntimeException('Confirmed shift rejected');
$bridge->stageConfirmed('employee-john','office-project-uuid','office-zone-uuid','confirmed',$from,$until);
if ((int)$db->query('SELECT COUNT(*) FROM onsite_project_zone_assignments')->fetchColumn() !== 1) throw new RuntimeException('Duplicate projection');
if ((int)$db->query('SELECT active FROM onsite_project_zone_assignments')->fetchColumn() !== 0) throw new RuntimeException('Implicit activation');
echo "OnSiteOfficePlanningBridgeTest OK\n";
