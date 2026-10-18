<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetShiftCancellation.php';
$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
if (!$dsn) throw new RuntimeException('MySQL DSN required');
$db = new PDO($dsn,getenv('OFFICE_INTAKE_TEST_USER') ?: '',getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach (['office_inzet_shifts','office_inzet_people','office_project_memberships','office_operator_accounts'] as $table) $db->exec('DROP TEMPORARY TABLE IF EXISTS '.$table);
$db->exec('CREATE TEMPORARY TABLE office_operator_accounts (id VARCHAR(128) PRIMARY KEY,active TINYINT) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE office_project_memberships (operator_id VARCHAR(128),project_id BIGINT,permission VARCHAR(20),PRIMARY KEY(operator_id,project_id)) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE office_inzet_people (id BIGINT PRIMARY KEY,active TINYINT) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE office_inzet_shifts (id BIGINT PRIMARY KEY AUTO_INCREMENT,project_id BIGINT,person_id BIGINT,starts_at DATETIME,ends_at DATETIME,status VARCHAR(24)) ENGINE=InnoDB');
$db->exec("INSERT INTO office_operator_accounts VALUES ('john.boon',1),('viewer',1)");
$db->exec("INSERT INTO office_project_memberships VALUES ('john.boon',17,'manage'),('viewer',17,'read')");
$db->exec('INSERT INTO office_inzet_people VALUES (101,1)');
$db->exec("INSERT INTO office_inzet_shifts (project_id,person_id,starts_at,ends_at,status) VALUES (17,101,'2026-10-19 08:00:00','2026-10-19 16:00:00','planned')");
$service = new \Brebo\Office\Inzet\OfficeInzetShiftCancellation($db);
foreach (['viewer','unknown'] as $operator) {
  try { $service->cancelForOperator($operator,1); throw new RuntimeException('Unauthorized cancellation accepted'); }
  catch (RuntimeException $e) { if ($e->getMessage()==='Unauthorized cancellation accepted') throw $e; }
}
if (!$service->cancelForOperator('john.boon',1)) throw new RuntimeException('First cancellation should change state');
if ($service->cancelForOperator('john.boon',1)) throw new RuntimeException('Repeated cancellation should be idempotent');
if ($db->query('SELECT status FROM office_inzet_shifts WHERE id = 1')->fetchColumn() !== 'cancelled') throw new RuntimeException('Shift not cancelled');
if ((int)$db->query('SELECT COUNT(*) FROM office_inzet_shifts')->fetchColumn() !== 1) throw new RuntimeException('Shift history deleted');
echo "Office Inzet MySQL cancellation permissions and history OK\n";
