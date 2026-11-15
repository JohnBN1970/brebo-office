<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Project/OfficeProjectKickstartPolicy.php';
require_once dirname(__DIR__).'/src/Project/OfficeProjectKickstartRepository.php';
require_once dirname(__DIR__).'/src/Project/OfficeProjectExecutionStartGate.php';
use Brebo\Office\Project\OfficeProjectKickstartRepository;
use Brebo\Office\Project\OfficeProjectExecutionStartGate;
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_project_start_dossiers (id INTEGER PRIMARY KEY, project_id INTEGER, scope_type TEXT, scope_id INTEGER, revision INTEGER, project_leader_user_id INTEGER, status TEXT, items_json TEXT, critical_open_points INTEGER)');
$db->exec('CREATE TABLE office_project_start_questions (id INTEGER PRIMARY KEY, dossier_id INTEGER, is_critical INTEGER, decision_text TEXT)');
$db->exec('CREATE TABLE office_project_start_approvals (id INTEGER PRIMARY KEY, dossier_id INTEGER, dossier_revision INTEGER, actor_user_id INTEGER, decision TEXT)');
$items=[];
foreach (\Brebo\Office\Project\OfficeProjectKickstartPolicy::REQUIRED_ITEMS as $key) $items[$key]=true;
$q=$db->prepare('INSERT INTO office_project_start_dossiers VALUES (1,42,?,?,2,7,?,?,0)');
$q->execute(['project',0,'released',json_encode($items,JSON_THROW_ON_ERROR)]);
$db->exec("INSERT INTO office_project_start_approvals VALUES (1,1,2,7,'approved_first')");
$db->exec("INSERT INTO office_project_start_approvals VALUES (2,1,2,8,'approved_second')");
$gate = new OfficeProjectExecutionStartGate(new OfficeProjectKickstartRepository($db));
$gate->authorizeStart(42,'project',0,true,true);
foreach ([[42,'project',0,false,true],[42,'project',0,true,false],[43,'project',0,true,true],[42,'zone',9,true,true]] as $args) {
    try {
        $gate->authorizeStart(...$args);
        throw new RuntimeException('Unauthorized execution start accepted');
    } catch (DomainException $expected) {}
}
echo "PROJECT_EXECUTION_START_GATE_OK\n";
