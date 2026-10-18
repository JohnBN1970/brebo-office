<?php
declare(strict_types=1);
$root = dirname(__DIR__);
require_once $root . '/src/Inzet/OnSiteProjectionWriter.php';
require_once $root . '/src/Inzet/OnSiteOfficeCancellationProjection.php';
$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
if (!$dsn) throw new RuntimeException('MySQL DSN required');
$db = new PDO($dsn,getenv('OFFICE_INTAKE_TEST_USER') ?: '',getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('DROP TEMPORARY TABLE IF EXISTS office_inzet_shifts');
$db->exec('DROP TEMPORARY TABLE IF EXISTS onsite_project_zone_assignments');
$db->exec('CREATE TEMPORARY TABLE office_inzet_shifts (id BIGINT PRIMARY KEY,project_id BIGINT,person_id BIGINT,status VARCHAR(24)) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE onsite_project_zone_assignments (employee_id VARCHAR(128),project_id VARCHAR(128),zone_id VARCHAR(128),valid_from VARCHAR(40),valid_until VARCHAR(40),active TINYINT) ENGINE=InnoDB');
$db->exec("INSERT INTO office_inzet_shifts VALUES (1,17,101,'cancelled'),(2,17,101,'confirmed'),(3,17,102,'cancelled'),(4,18,101,'cancelled')");
$db->exec("INSERT INTO onsite_project_zone_assignments VALUES ('employee-101','17','zone-a','2026-10-19T08:00:00+00:00',NULL,1),('employee-102','17','zone-a','2026-10-19T08:00:00+00:00',NULL,1),('employee-101','18','zone-b','2026-10-19T08:00:00+00:00',NULL,1)");
$sync = new \Brebo\Office\Inzet\OnSiteOfficeCancellationProjection($db,new \Brebo\Office\Inzet\OnSiteProjectionWriter($db));
$employees = [101=>'employee-101',102=>'employee-102'];
$zones = [17=>'zone-a',18=>'zone-b'];
if ($sync->deactivateCancelled(1,$employees,$zones) !== 0) throw new RuntimeException('Other confirmed shift should retain access');
if ($sync->deactivateCancelled(3,$employees,$zones) !== 1) throw new RuntimeException('Cancelled worker access not deactivated');
if ($sync->deactivateCancelled(4,$employees,$zones) !== 1) throw new RuntimeException('Other project access not deactivated');
if ($sync->deactivateCancelled(2,$employees,$zones) !== 0) throw new RuntimeException('Confirmed shift was deactivated');
if ($sync->deactivateCancelled(3,$employees,$zones) !== 0) throw new RuntimeException('Reconciliation not idempotent');
$active = $db->query("SELECT employee_id,project_id,active FROM onsite_project_zone_assignments ORDER BY employee_id,project_id")->fetchAll(PDO::FETCH_ASSOC);
if (count($active)!==3 || (int)$active[0]['active']!==1) {
  // Do not rely on lexical ordering for the security assertions below.
}
$stmt = $db->query("SELECT project_id,employee_id,active FROM onsite_project_zone_assignments");
$states = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $states[$row['project_id'].':'.$row['employee_id']] = (int)$row['active'];
if ($states !== ['17:employee-101'=>1,'17:employee-102'=>0,'18:employee-101'=>0]) throw new RuntimeException('OnSite assignment isolation failed');
try {
  $sync->deactivateCancelled(3,[101=>'employee-101'],$zones);
  throw new RuntimeException('Missing verified mapping accepted');
} catch (DomainException $e) {}
echo "OnSite Office cancellation reconciliation MySQL OK\n";
