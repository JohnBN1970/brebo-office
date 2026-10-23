<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OnSitePresenceIntervalProjector.php';
require_once dirname(__DIR__).'/src/Inzet/OnSitePresenceReviewRepository.php';
use Brebo\Office\Inzet\OnSitePresenceReviewRepository;

$dsn=getenv('OFFICE_ONSITE_MIGRATION_CI_DSN');
if ($dsn!=='mysql:host=127.0.0.1;port=3306;dbname=office_onsite_migration_ci;charset=utf8mb4') throw new RuntimeException('Dedicated review CI database required');
$db=new PDO($dsn,(string)getenv('OFFICE_INZET_TEST_USER'),(string)getenv('OFFICE_INZET_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
if ($db->query('SELECT DATABASE()')->fetchColumn()!=='office_onsite_migration_ci') throw new RuntimeException('CI isolation failed');
// Uses the isolated migration fixture; never connect to a server test or production database.
$db->exec('CREATE TABLE office_operator_accounts (id VARCHAR(128) PRIMARY KEY,active TINYINT NOT NULL) ENGINE=InnoDB');
$db->exec('CREATE TABLE office_project_memberships (project_id BIGINT UNSIGNED NOT NULL,operator_id VARCHAR(128) NOT NULL,permission VARCHAR(16) NOT NULL,PRIMARY KEY(project_id,operator_id)) ENGINE=InnoDB');
$q=$db->prepare('INSERT INTO office_operator_accounts VALUES (?,?)');
foreach (['office-manager:john','read-only','other-project','inactive','0',str_repeat('x',128)] as $id) $q->execute([$id,$id==='inactive'?0:1]);
$q=$db->prepare('INSERT INTO office_project_memberships VALUES (?,?,?)');
foreach (['office-manager:john','inactive','0',str_repeat('x',128)] as $id) $q->execute([17,$id,'manage']);
$q->execute([17,'read-only','read']);$q->execute([18,'other-project','manage']);
$q=$db->prepare('INSERT INTO office_onsite_event_inbox(event_id,person_id,project_id,zone_id,event_kind,occurred_at_utc) VALUES (?,101,17,8,?,?)');
$q->execute(['review_fixture_in_0001','in','2026-10-23T08:00:00.000000Z']);
$q->execute(['review_fixture_out_0001','out','2026-10-23T09:00:00.000000Z']);
$repo=new OnSitePresenceReviewRepository($db);
foreach (['office-manager:john','0',str_repeat('x',128)] as $id) {
  $view=$repo->forManager($id,'17');
  if (count($view['intervals'])!==1 || $view['intervals'][0]['elapsed_seconds']!==3600) throw new RuntimeException('Opaque manager ID denied or evidence incorrect');
}
foreach (['read-only','other-project','inactive','unknown',"' OR 1=1 --"] as $id) {
  $view=$repo->forManager($id,'17');
  if ($view!==['intervals'=>[],'active'=>[],'exceptions'=>[]]) throw new RuntimeException('Unauthorized event disclosure');
}
foreach ([['','17',250],[str_repeat('x',129),'17',250],['office-manager:john','0',250],['office-manager:john','17',0],['office-manager:john','17',501]] as [$id,$project,$limit]) {
  $denied=false;
  try {$repo->forManager($id,$project,$limit);} catch (InvalidArgumentException) {$denied=true;}
  if (!$denied) throw new RuntimeException('Invalid review context accepted');
}
$truncated=false;
try {$repo->forManager('office-manager:john','17',1);} catch (RuntimeException $e) {$truncated=$e->getMessage()==='presence_review_window_incomplete';}
if (!$truncated) throw new RuntimeException('Incomplete evidence window accepted');
$db->prepare('UPDATE office_project_memberships SET permission=? WHERE operator_id=? AND project_id=17')->execute(['read','office-manager:john']);
if ($repo->forManager('office-manager:john','17')!==['intervals'=>[],'active'=>[],'exceptions'=>[]]) throw new RuntimeException('Revoked manage permission retained');
echo "Presence review opaque accounts, project ACL, inactive accounts and evidence window OK\n";
