<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetShiftRescheduler.php';
$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
if (!$dsn) throw new RuntimeException('MySQL DSN required');
$db = new PDO($dsn,getenv('OFFICE_INTAKE_TEST_USER') ?: '',getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach (['office_inzet_shifts','office_inzet_project_team','office_inzet_people','office_project_memberships','office_operator_accounts'] as $table) $db->exec('DROP TEMPORARY TABLE IF EXISTS '.$table);
$db->exec('CREATE TEMPORARY TABLE office_operator_accounts (id VARCHAR(128) PRIMARY KEY,active TINYINT) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE office_project_memberships (operator_id VARCHAR(128),project_id BIGINT,permission VARCHAR(20),PRIMARY KEY(operator_id,project_id)) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE office_inzet_people (id BIGINT PRIMARY KEY,active TINYINT) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE office_inzet_project_team (project_id BIGINT,person_id BIGINT,PRIMARY KEY(project_id,person_id)) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE office_inzet_shifts (id BIGINT PRIMARY KEY,project_id BIGINT,person_id BIGINT,starts_at DATETIME,ends_at DATETIME,status VARCHAR(24)) ENGINE=InnoDB');
$db->exec("INSERT INTO office_operator_accounts VALUES ('manager',1),('viewer',1)");
$db->exec("INSERT INTO office_project_memberships VALUES ('manager',17,'manage'),('viewer',17,'read')");
$db->exec('INSERT INTO office_inzet_people VALUES (101,1)');
$db->exec('INSERT INTO office_inzet_project_team VALUES (17,101)');
$db->exec("INSERT INTO office_inzet_shifts VALUES (1,17,101,'2026-10-19 08:00:00','2026-10-19 12:00:00','planned'),(2,17,101,'2026-10-19 14:00:00','2026-10-19 18:00:00','confirmed')");
$s = new \Brebo\Office\Inzet\OfficeInzetShiftRescheduler($db);
$oldStart='2026-10-19 08:00:00';$oldEnd='2026-10-19 12:00:00';
$reject = static function(callable $action): void {
  try { $action(); throw new RuntimeException('Unsafe reschedule accepted'); }
  catch (RuntimeException|InvalidArgumentException $e) { if ($e->getMessage()==='Unsafe reschedule accepted') throw $e; }
};
$reject(fn()=> $s->rescheduleForOperator('viewer',1,$oldStart,$oldEnd,'2026-10-19 09:00:00','2026-10-19 13:00:00'));
$reject(fn()=> $s->rescheduleForOperator('manager',1,$oldStart,$oldEnd,'2026-10-19 11:00:00','2026-10-19 15:00:00'));
$s->rescheduleForOperator('manager',1,$oldStart,$oldEnd,'2026-10-19 09:00:00','2026-10-19 13:00:00');
$reject(fn()=> $s->rescheduleForOperator('manager',1,$oldStart,$oldEnd,'2026-10-19 10:00:00','2026-10-19 13:00:00'));
$state=$db->query('SELECT starts_at,ends_at FROM office_inzet_shifts WHERE id=1')->fetch(PDO::FETCH_ASSOC);
if ($state!==['starts_at'=>'2026-10-19 09:00:00','ends_at'=>'2026-10-19 13:00:00']) throw new RuntimeException('Reschedule state mismatch');
if ((int)$db->query('SELECT COUNT(*) FROM office_inzet_shifts')->fetchColumn()!==2) throw new RuntimeException('History altered');
echo "Office Inzet MySQL reschedule ACL, overlap and revision OK\n";
