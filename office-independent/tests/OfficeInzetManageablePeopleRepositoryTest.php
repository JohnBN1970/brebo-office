<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetManageablePeopleRepository.php';
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, active INTEGER)');
$db->exec('CREATE TABLE office_project_memberships (operator_id TEXT, project_id INTEGER, permission TEXT)');
$db->exec('CREATE TABLE office_inzet_people (id INTEGER PRIMARY KEY, display_name TEXT, personnel_number TEXT, active INTEGER)');
$db->exec('CREATE TABLE office_inzet_project_team (project_id INTEGER, person_id INTEGER)');
$db->exec("INSERT INTO office_operator_accounts VALUES ('john.boon',1),('other.user',1),('disabled',0)");
$db->exec("INSERT INTO office_project_memberships VALUES ('john.boon',17,'manage'),('john.boon',18,'view'),('other.user',19,'manage'),('disabled',20,'manage')");
$db->exec("INSERT INTO office_inzet_people VALUES (101,'John Boon','BREBO-001',1),(102,'John Boon','BREBO-047',1),(103,'Other','BREBO-003',1),(104,'Inactive','BREBO-004',0)");
$db->exec('INSERT INTO office_inzet_project_team VALUES (17,101),(17,102),(18,103),(19,103),(17,104),(20,103)');
$repo = new \Brebo\Office\Inzet\OfficeInzetManageablePeopleRepository($db);
$rows = $repo->listForOperator('john.boon',17);
if (count($rows) !== 2 || (int)$rows[0]['id'] !== 101 || (int)$rows[1]['id'] !== 102) throw new RuntimeException('Namesakes must remain separate and scoped');
foreach ([18,19,20] as $project) {
  if ($repo->listForOperator('john.boon',$project) !== []) throw new RuntimeException('Unmanaged project exposed');
}
if ($repo->listForOperator('disabled',20) !== []) throw new RuntimeException('Inactive operator exposed');
echo "Project-scoped people selector OK\n";
