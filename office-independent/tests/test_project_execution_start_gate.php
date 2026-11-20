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
$db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, active INTEGER)');
$db->exec('CREATE TABLE office_project_memberships (project_id INTEGER, operator_id TEXT, permission TEXT)');
$db->exec("INSERT INTO office_operator_accounts VALUES ('7',1),('8',1)");
$db->exec("INSERT INTO office_project_memberships VALUES (42,'7','manage'),(42,'8','manage')");
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
// Regression matrix: release must be tied to one current revision, two distinct actors,
// complete required evidence and zero unresolved critical questions.
$mustBlock = static function (string $reason) use ($gate): void {
    try {
        $gate->authorizeStart(42, 'project', 0, true, true);
    } catch (DomainException $expected) {
        return;
    }
    throw new RuntimeException('Execution was permitted despite ' . $reason);
};
$db->exec("DELETE FROM office_project_start_approvals WHERE decision = 'approved_second'");
$mustBlock('missing second approval');
$db->exec("INSERT INTO office_project_start_approvals VALUES (3,1,2,7,'approved_second')");
$mustBlock('same reviewer approving twice');
$db->exec("UPDATE office_project_start_approvals SET actor_user_id=8 WHERE id=3");
$gate->authorizeStart(42,'project',0,true,true);
$db->exec("UPDATE office_project_start_approvals SET actor_user_id=9 WHERE id=1");
$mustBlock('first approval by someone other than the assigned project leader');
$db->exec("UPDATE office_project_start_approvals SET actor_user_id=7 WHERE id=1");
$gate->authorizeStart(42,'project',0,true,true);
$db->exec("UPDATE office_project_start_approvals SET dossier_revision=1 WHERE id=3");
$mustBlock('second approval tied to a different revision');
$db->exec("UPDATE office_project_start_approvals SET dossier_revision=2 WHERE id=3");
$gate->authorizeStart(42,'project',0,true,true);
$db->exec("UPDATE office_operator_accounts SET active=0 WHERE id='7'");
$mustBlock('inactive first approver');
$db->exec("UPDATE office_operator_accounts SET active=1 WHERE id='7'");
$db->exec("UPDATE office_project_memberships SET permission='read' WHERE operator_id='7'");
$mustBlock('first approver project management access revoked');
$db->exec("UPDATE office_project_memberships SET permission='manage' WHERE operator_id='7'");
$gate->authorizeStart(42,'project',0,true,true);
$db->exec("UPDATE office_operator_accounts SET active=0 WHERE id='8'");
$mustBlock('inactive second reviewer');
$db->exec("UPDATE office_operator_accounts SET active=1 WHERE id='8'");
$db->exec("UPDATE office_project_memberships SET permission='read' WHERE operator_id='8'");
$mustBlock('second reviewer project management access revoked');
$db->exec("UPDATE office_project_memberships SET permission='manage' WHERE operator_id='8'");
$gate->authorizeStart(42,'project',0,true,true);
$db->exec("UPDATE office_project_start_dossiers SET revision=3 WHERE id=1");
$mustBlock('stale approval revision');
$db->exec("UPDATE office_project_start_dossiers SET revision=2 WHERE id=1");
$db->exec("INSERT INTO office_project_start_questions VALUES (1,1,1,'')");
$mustBlock('unresolved critical decision');
$db->exec("UPDATE office_project_start_questions SET decision_text='Besluit vastgelegd' WHERE id=1");
$gate->authorizeStart(42,'project',0,true,true);
$items['bouwplaatsdocumenten']=false;
$db->prepare("UPDATE office_project_start_dossiers SET items_json=? WHERE id=1")->execute([json_encode($items,JSON_THROW_ON_ERROR)]);
$mustBlock('missing construction-site documents');
$items['bouwplaatsdocumenten']=true;
$db->prepare("UPDATE office_project_start_dossiers SET items_json=? WHERE id=1")->execute([json_encode($items,JSON_THROW_ON_ERROR)]);
$db->exec("UPDATE office_project_start_dossiers SET critical_open_points=1 WHERE id=1");
$mustBlock('critical open points');
$db->exec("UPDATE office_project_start_dossiers SET critical_open_points=0, status='draft' WHERE id=1");
$mustBlock('revoked dossier release');
echo "PROJECT_EXECUTION_START_GATE_OK\n";
