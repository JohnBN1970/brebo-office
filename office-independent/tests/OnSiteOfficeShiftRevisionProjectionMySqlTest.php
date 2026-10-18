<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OnSiteOfficeShiftRevisionProjection.php';
$dsn=getenv('OFFICE_INTAKE_TEST_DSN');
if (!$dsn) throw new RuntimeException('MySQL DSN required');
$db=new PDO($dsn,getenv('OFFICE_INTAKE_TEST_USER') ?: '',getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('DROP TEMPORARY TABLE IF EXISTS office_inzet_shifts');
$db->exec('DROP TEMPORARY TABLE IF EXISTS onsite_project_zone_assignments');
$db->exec('CREATE TEMPORARY TABLE office_inzet_shifts (id BIGINT PRIMARY KEY,project_id BIGINT,person_id BIGINT,starts_at DATETIME,ends_at DATETIME,status VARCHAR(24)) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE onsite_project_zone_assignments (employee_id VARCHAR(128),project_id VARCHAR(128),zone_id VARCHAR(128),valid_from VARCHAR(40),valid_until VARCHAR(40),active TINYINT) ENGINE=InnoDB');
$db->exec("INSERT INTO office_inzet_shifts VALUES (1,17,101,'2026-10-19 09:00:00','2026-10-19 13:00:00','confirmed'),(2,17,102,'2026-10-19 09:00:00','2026-10-19 13:00:00','planned')");
$db->exec("INSERT INTO onsite_project_zone_assignments VALUES ('employee-101','17','zone-a','2026-10-19T08:00:00+00:00','2026-10-19T12:00:00+00:00',1),('employee-102','17','zone-a','2026-10-19T08:00:00+00:00','2026-10-19T12:00:00+00:00',1)");
$s=new \Brebo\Office\Inzet\OnSiteOfficeShiftRevisionProjection($db);
if ($s->revise(2,'employee-102','zone-a','2026-10-19 08:00:00')) throw new RuntimeException('Planned shift revised');
if (!$s->revise(1,'employee-101','zone-a','2026-10-19 08:00:00')) throw new RuntimeException('Confirmed revision not applied');
if ($s->revise(1,'employee-101','zone-a','2026-10-19 09:00:00')) throw new RuntimeException('Idempotent revision should not change');
$rows=$db->query('SELECT employee_id,valid_from,valid_until,active FROM onsite_project_zone_assignments ORDER BY employee_id')->fetchAll(PDO::FETCH_ASSOC);
if (count($rows)!==2 || $rows[0]!==['employee_id'=>'employee-101','valid_from'=>'2026-10-19T09:00:00+00:00','valid_until'=>'2026-10-19T13:00:00+00:00','active'=>0]
  || $rows[1]['active']!=1 || $rows[1]['valid_from']!=='2026-10-19T08:00:00+00:00') throw new RuntimeException('Revision isolation or deactivation failed');
// A stale expected end must fail without changing the staged assignment.
try {
  $s->revise(1,'employee-101','zone-a','2026-10-19 09:00:00','2026-10-19 11:00:00');
  throw new RuntimeException('Stale end-time accepted');
} catch (DomainException $e) {}
// End-time-only corrections must update the existing identity, not collide with it.
$db->exec("UPDATE office_inzet_shifts SET ends_at = '2026-10-19 14:00:00' WHERE id = 1");
if (!$s->revise(1,'employee-101','zone-a','2026-10-19 09:00:00')) {
  throw new RuntimeException('End-only revision was rejected');
}
$corrected = $db->query("SELECT valid_from,valid_until,active FROM onsite_project_zone_assignments WHERE employee_id='employee-101'")->fetch(PDO::FETCH_ASSOC);
if ($corrected !== ['valid_from'=>'2026-10-19T09:00:00+00:00','valid_until'=>'2026-10-19T14:00:00+00:00','active'=>0]) {
  throw new RuntimeException('End-only revision must preserve identity and inactive state');
}
echo "OnSite Office revision MySQL OK\n";
