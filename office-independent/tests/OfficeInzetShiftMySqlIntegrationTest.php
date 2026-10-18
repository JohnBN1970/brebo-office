<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetShiftAssignmentControl.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetShiftWriter.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetShiftCommand.php';

$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
if (!$dsn) throw new RuntimeException('MySQL test DSN required');
$db = new PDO($dsn, getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach (['office_inzet_shifts','office_inzet_project_team','office_project_memberships','office_inzet_people','office_operator_accounts'] as $table) {
  $db->exec('DROP TEMPORARY TABLE IF EXISTS ' . $table);
}
$db->exec('CREATE TEMPORARY TABLE office_operator_accounts (id VARCHAR(128) PRIMARY KEY, active TINYINT NOT NULL) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE office_project_memberships (operator_id VARCHAR(128),project_id BIGINT,permission VARCHAR(20), PRIMARY KEY(operator_id,project_id)) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE office_inzet_people (id BIGINT PRIMARY KEY,active TINYINT NOT NULL) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE office_inzet_project_team (project_id BIGINT,person_id BIGINT,PRIMARY KEY(project_id,person_id)) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE office_inzet_shifts (id BIGINT PRIMARY KEY AUTO_INCREMENT,project_id BIGINT,person_id BIGINT,starts_at DATETIME,ends_at DATETIME,status VARCHAR(24)) ENGINE=InnoDB');
$db->exec("INSERT INTO office_operator_accounts VALUES ('john.boon',1),('inactive',0)");
$db->exec("INSERT INTO office_project_memberships VALUES ('john.boon',17,'manage'),('john.boon',18,'read'),('inactive',17,'manage')");
$db->exec('INSERT INTO office_inzet_people VALUES (101,1),(102,1)');
$db->exec('INSERT INTO office_inzet_project_team VALUES (17,101),(17,102)');
$command = new \Brebo\Office\Inzet\OfficeInzetShiftCommand($db,new \Brebo\Office\Inzet\OfficeInzetShiftWriter($db));
$base = ['project_id'=>17,'person_id'=>101,'starts_at'=>'2026-10-19 08:00:00','ends_at'=>'2026-10-19 16:00:00'];
$created = $command->createForOperator('john.boon',$base);
if ($created < 1) throw new RuntimeException('Valid shift not created');
foreach ([
  ['john.boon',$base],
  ['john.boon',array_replace($base,['project_id'=>18,'person_id'=>102])],
  ['inactive',array_replace($base,['person_id'=>102])],
  ['john.boon',array_replace($base,['person_id'=>999])],
] as [$operator,$shift]) {
  try {
    $command->createForOperator($operator,$shift);
    throw new RuntimeException('Invalid shift accepted');
  } catch (RuntimeException $e) {
    if ($e->getMessage() === 'Invalid shift accepted') throw $e;
  }
}
$db->exec('DELETE FROM office_inzet_project_team WHERE project_id = 17 AND person_id = 102');
try {
  $command->createForOperator('john.boon',array_replace($base,['person_id'=>102]));
  throw new RuntimeException('Revoked team member accepted');
} catch (RuntimeException $e) {
  if ($e->getMessage() === 'Revoked team member accepted') throw $e;
}
$count = (int)$db->query('SELECT COUNT(*) FROM office_inzet_shifts')->fetchColumn();
if ($count !== 1) throw new RuntimeException('Rejected writes changed persisted shifts');
echo "Inzet MySQL transactional authorization and overlap OK\n";
