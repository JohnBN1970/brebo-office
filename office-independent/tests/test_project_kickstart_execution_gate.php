<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Project/OfficeProjectKickstartPolicy.php';
require_once dirname(__DIR__).'/src/Project/OfficeProjectKickstartRepository.php';
use Brebo\Office\Project\OfficeProjectKickstartRepository;
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_project_start_dossiers (id INTEGER PRIMARY KEY, project_id INTEGER, scope_type TEXT, scope_id INTEGER, revision INTEGER, project_leader_user_id INTEGER, status TEXT, items_json TEXT, critical_open_points INTEGER)');
$db->exec('CREATE TABLE office_project_start_questions (id INTEGER PRIMARY KEY, dossier_id INTEGER, is_critical INTEGER, decision_text TEXT)');
$db->exec('CREATE TABLE office_project_start_approvals (id INTEGER PRIMARY KEY, dossier_id INTEGER, dossier_revision INTEGER, actor_user_id INTEGER, decision TEXT)');
$items=[];
foreach (\Brebo\Office\Project\OfficeProjectKickstartPolicy::REQUIRED_ITEMS as $key) $items[$key]=true;
$q=$db->prepare('INSERT INTO office_project_start_dossiers VALUES (1,42,?,?,2,7,?,?,0)');
$q->execute(['project',0,'released',json_encode($items,JSON_THROW_ON_ERROR)]);
$gate=new OfficeProjectKickstartRepository($db);
$blocked=static function() use ($gate): void {
    try { $gate->assertReleased(42,'project',0); } catch (DomainException $e) { return; }
    throw new RuntimeException('Invalid start release accepted');
};
$blocked();
$db->exec("INSERT INTO office_project_start_approvals VALUES (1,1,2,7,'approved_first')");
$blocked();
$db->exec("INSERT INTO office_project_start_approvals VALUES (2,1,2,7,'approved_second')");
$blocked();
$db->exec("UPDATE office_project_start_approvals SET actor_user_id=8 WHERE id=2");
$gate->assertReleased(42,'project',0);
$db->exec("INSERT INTO office_project_start_questions VALUES (1,1,1,'')");
$blocked();
$db->exec("UPDATE office_project_start_questions SET decision_text='Besloten' WHERE id=1");
$gate->assertReleased(42,'project',0);
$db->exec('UPDATE office_project_start_dossiers SET project_leader_user_id=9 WHERE id=1');
$blocked();
$db->exec('UPDATE office_project_start_dossiers SET project_leader_user_id=7 WHERE id=1');
$gate->assertReleased(42,'project',0);
$db->exec('UPDATE office_project_start_dossiers SET revision=3 WHERE id=1');
$blocked();
echo "KICKSTART_EXECUTION_GATE_OK\n";
