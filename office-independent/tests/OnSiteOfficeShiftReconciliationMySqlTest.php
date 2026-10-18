<?php
declare(strict_types=1);
$root=dirname(__DIR__).'/src/Inzet/';
foreach (['OnSiteProjectionWriter.php','OnSiteOfficeCancellationProjection.php','OnSiteOfficeShiftRevisionProjection.php','OnSiteOfficeShiftReconciliation.php'] as $file) require_once $root.$file;
$dsn=getenv('OFFICE_INTAKE_TEST_DSN');
if (!$dsn) throw new RuntimeException('MySQL DSN required');
$db=new PDO($dsn,getenv('OFFICE_INTAKE_TEST_USER') ?: '',getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('DROP TEMPORARY TABLE IF EXISTS office_inzet_shifts');
$db->exec('DROP TEMPORARY TABLE IF EXISTS onsite_project_zone_assignments');
$db->exec('CREATE TEMPORARY TABLE office_inzet_shifts (id BIGINT PRIMARY KEY,project_id BIGINT,person_id BIGINT,starts_at DATETIME,ends_at DATETIME,status VARCHAR(24)) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE onsite_project_zone_assignments (employee_id VARCHAR(128),project_id VARCHAR(128),zone_id VARCHAR(128),valid_from VARCHAR(40),valid_until VARCHAR(40),active TINYINT) ENGINE=InnoDB');
$db->exec("INSERT INTO office_inzet_shifts VALUES (1,17,101,'2026-10-19 09:00:00','2026-10-19 13:00:00','confirmed'),(2,17,102,'2026-10-19 08:00:00','2026-10-19 12:00:00','cancelled'),(3,17,103,'2026-10-19 08:00:00','2026-10-19 12:00:00','planned')");
$db->exec("INSERT INTO onsite_project_zone_assignments VALUES ('employee-101','17','zone-a','2026-10-19T08:00:00+00:00','2026-10-19T12:00:00+00:00',1),('employee-102','17','zone-a','2026-10-19T08:00:00+00:00','2026-10-19T12:00:00+00:00',1)");
$writer=new \Brebo\Office\Inzet\OnSiteProjectionWriter($db);
$service=new \Brebo\Office\Inzet\OnSiteOfficeShiftReconciliation($db,
  new \Brebo\Office\Inzet\OnSiteOfficeCancellationProjection($db,$writer),
  new \Brebo\Office\Inzet\OnSiteOfficeShiftRevisionProjection($db));
$people=[101=>'employee-101',102=>'employee-102',103=>'employee-103'];$zones=[17=>'zone-a'];
try {
  $service->reconcile(1,[102=>'employee-102'],$zones,'2026-10-19 08:00:00');
  throw new RuntimeException('Unverified mapping accepted');
} catch (DomainException $e) {}
$changed=$service->reconcile(1,$people,$zones,'2026-10-19 08:00:00');
if ($changed!==['status'=>'confirmed','changed'=>true]) throw new RuntimeException('Revision not reconciled');
$cancel=$service->reconcile(2,$people,$zones);
if ($cancel!==['status'=>'cancelled','changed'=>true]) throw new RuntimeException('Cancellation not reconciled');
$planned=$service->reconcile(3,$people,$zones);
if ($planned!==['status'=>'ignored','changed'=>false]) throw new RuntimeException('Planned shift not ignored');
$rows=$db->query('SELECT employee_id,active,valid_from FROM onsite_project_zone_assignments ORDER BY employee_id')->fetchAll(PDO::FETCH_ASSOC);
if (count($rows)!==2 || $rows[0]['active']!=0 || $rows[0]['valid_from']!=='2026-10-19T09:00:00+00:00' || $rows[1]['active']!=0) throw new RuntimeException('OnSite reconciliation isolation failed');
echo "Verified OnSite reconciliation MySQL OK\n";
