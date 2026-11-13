<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Project/OfficeProjectKickstartReadModel.php';
use Brebo\Office\Project\OfficeProjectKickstartReadModel;

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_project_start_dossiers (id INTEGER PRIMARY KEY, project_id INTEGER, scope_type TEXT, scope_id INTEGER, revision INTEGER, status TEXT, items_json TEXT, critical_open_points INTEGER, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE office_project_start_approvals (id INTEGER PRIMARY KEY, dossier_id INTEGER, dossier_revision INTEGER, actor_user_id INTEGER, decision TEXT, note TEXT, created_at TEXT)');
$db->exec('CREATE TABLE office_project_start_questions (id INTEGER PRIMARY KEY, dossier_id INTEGER, created_revision INTEGER, question TEXT, answer TEXT, decision_text TEXT, owner_user_id INTEGER, due_date TEXT, is_critical INTEGER, created_at TEXT)');
$db->exec("INSERT INTO office_project_start_questions VALUES (1,1,2,'Is de steiger gereed?','Ja','Vrijgegeven',7,'2026-11-16',0,'2026-11-13')");
$db->exec("INSERT INTO office_project_start_dossiers VALUES (1,42,'project',0,2,'awaiting_second_approval','{\"projectteam\":true}',0,'2026-11-13','2026-11-13')");
$db->exec("INSERT INTO office_project_start_approvals VALUES (1,1,2,7,'approved_first',NULL,'2026-11-13')");
$reader = new OfficeProjectKickstartReadModel($db);
$found = $reader->forAuthorizedScope(42,'project',0);
if (!$found || $found['revision'] !== 2 || $found['items']['projectteam'] !== true || count($found['approvals']) !== 1 || count($found['questions']) !== 1 || $found['questions'][0]['decision'] !== 'Vrijgegeven') throw new RuntimeException('Dossier read failed');
if ($reader->forAuthorizedScope(43,'project',0) !== null) throw new RuntimeException('Cross-project dossier leak');
try { $reader->forAuthorizedScope(42,'invalid',0); throw new RuntimeException('Invalid scope accepted'); }
catch (DomainException $expected) {}
echo "KICKSTART_READMODEL_TESTS_OK\n";
