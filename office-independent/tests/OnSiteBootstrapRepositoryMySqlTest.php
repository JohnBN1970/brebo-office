<?php
declare(strict_types=1);
$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:')) throw new RuntimeException('Isolated MySQL test DSN required');
$db = new PDO($dsn, getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$sql = file_get_contents(__DIR__ . '/../deployment/hostinger/sql/onsite-presence-mysql.sql');
if ($sql === false) throw new RuntimeException('Missing migration');
$sql = preg_replace('/^--.*$/m', '', $sql);
foreach (explode(';', $sql) as $statement) if (trim($statement) !== '') $db->exec($statement);
require_once __DIR__ . '/../src/Inzet/OnSiteBootstrapRepository.php';
$db->prepare('INSERT INTO onsite_employees (employee_id,display_name,language,active) VALUES (?,?,?,1)')
  ->execute(['bootstrap-test-employee','Test medewerker','nl']);
$db->prepare('INSERT INTO onsite_projects (project_id,name,active) VALUES (?,?,1)')
  ->execute(['83721','Testproject']);
$db->prepare('INSERT INTO onsite_zones (zone_id,project_id,name,latitude,longitude,radius_metres,active) VALUES (?,?,?,?,?,?,1)')
  ->execute(['83911','83721','Testzone',51.3,4.4,100]);
$db->prepare('INSERT INTO onsite_project_zone_assignments (employee_id,project_id,zone_id,valid_from,valid_until,active) VALUES (?,?,?,?,?,1)')
  ->execute(['bootstrap-test-employee','83721','83911','2026-01-01T00:00:00+00:00',null]);
$repository = new Brebo\Office\Inzet\OnSiteBootstrapRepository($db);
$data = $repository->forEmployee('bootstrap-test-employee', new DateTimeImmutable('2026-10-18T09:00:00+00:00'));
if (count($data['projects']) !== 1 || count($data['projects'][0]['zones']) !== 1
  || $data['projects'][0]['zones'][0]['radius_metres'] !== 100.0) throw new RuntimeException('Assigned zone missing');
$outside = $repository->forEmployee('bootstrap-test-employee', new DateTimeImmutable('2025-12-31T23:00:00+00:00'));
if ($outside['projects'] !== []) throw new RuntimeException('Assignment exposed before valid_from');
$db->exec("UPDATE onsite_zones SET active = 0 WHERE zone_id = '83911'");
if ($repository->forEmployee('bootstrap-test-employee')['projects'] !== []) throw new RuntimeException('Inactive zone exposed');
echo "OnSiteBootstrapRepositoryMySqlTest OK\n";
