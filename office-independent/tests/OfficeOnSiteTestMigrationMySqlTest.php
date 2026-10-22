<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/deployment/hostinger/OfficeOnSiteTestMigration.php';
require_once dirname(__DIR__).'/src/Inzet/OnSiteDeviceTokenRepository.php';
require_once dirname(__DIR__).'/src/Inzet/OnSiteEventInbox.php';
use Brebo\Office\Deployment\OfficeOnSiteTestMigration;
use Brebo\Office\Inzet\OnSiteDeviceTokenRepository;
use Brebo\Office\Inzet\OnSiteEventInbox;

function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function rejects(callable $f, string $message): void {
  try { $f(); } catch (Throwable) { return; }
  throw new RuntimeException($message);
}
$config = [
  'OFFICE_BASE_PATH'=>'/office-test', 'OFFICE_PUBLIC_HOST'=>'brebobv.nl',
  'OFFICE_PRIVATE_ROOT'=>'/home/u213420663/domains/brebobv.nl/office_calc_test/release-office-test-20261018/private',
  'OFFICE_INTAKE_DB_USER'=>'u213420663_office_test',
  'OFFICE_INTAKE_PDO_DSN'=>'mysql:host=localhost;dbname=u213420663_office_test;charset=utf8mb4',
];
OfficeOnSiteTestMigration::assertTarget($config);
OfficeOnSiteTestMigration::assertConsumersStopped($config);
$enabled=$config; $enabled['OFFICE_ONSITE_SCHEMA_VERIFIED']='enabled';
rejects(fn()=>OfficeOnSiteTestMigration::assertConsumersStopped($enabled),'Active configured schema gate accepted');
rejects(fn()=>OfficeOnSiteTestMigration::assertConsumersStopped($config,['OFFICE_ONSITE_SCHEMA_VERIFIED'=>'enabled']),'Active inherited schema gate accepted');
$local = $config; $local['OFFICE_INTAKE_PDO_DSN'] = str_replace('localhost','127.0.0.1',$local['OFFICE_INTAKE_PDO_DSN']);
OfficeOnSiteTestMigration::assertTarget($local);
foreach (['OFFICE_BASE_PATH'=>'/office','OFFICE_PUBLIC_HOST'=>'other.nl', 'OFFICE_PRIVATE_ROOT'=>'/production/private',
  'OFFICE_INTAKE_DB_USER'=>'root', 'OFFICE_INTAKE_PDO_DSN'=>'mysql:host=localhost;dbname=production;charset=utf8mb4'] as $key=>$value) {
  $wrong=$config; $wrong[$key]=$value;
  rejects(fn()=>OfficeOnSiteTestMigration::assertTarget($wrong), 'Production boundary accepted: '.$key);
}
$dsn = getenv('OFFICE_ONSITE_MIGRATION_CI_DSN');
if ($dsn !== 'mysql:host=127.0.0.1;port=3306;dbname=office_onsite_migration_ci;charset=utf8mb4') throw new RuntimeException('Dedicated migration CI DSN required');
$connect = static fn()=>new PDO($dsn, (string)getenv('OFFICE_INZET_TEST_USER'), (string)getenv('OFFICE_INZET_TEST_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db=$connect();
check($db->query('SELECT DATABASE()')->fetchColumn()==='office_onsite_migration_ci','CI isolation');
// These fixtures and all DROP statements are confined to the separately created CI database.
foreach (['office_onsite_event_inbox','office_onsite_device_tokens','office_inzet_project_team','office_projects','office_inzet_people','onsite_device_tokens'] as $table) $db->exec('DROP TABLE IF EXISTS '.$table);
$migration=new OfficeOnSiteTestMigration($db,'office_onsite_migration_ci');
check($migration->run()['status']==='BLOCK','Missing parent accepted');
rejects(fn()=>new OfficeOnSiteTestMigration($db,'production'),'Arbitrary database accepted');
rejects(fn()=>new OfficeOnSiteTestMigration($db),'Wrong connected database accepted');
$db->exec('CREATE TABLE office_inzet_people (id BIGINT UNSIGNED PRIMARY KEY, display_name VARCHAR(200) NOT NULL, active TINYINT NOT NULL) ENGINE=InnoDB');
$db->exec("INSERT INTO office_inzet_people VALUES (101,'Existing employee',1),(102,'Inactive employee',0)");
$db->exec('CREATE TABLE office_projects (id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB');
$db->exec('CREATE TABLE office_inzet_project_team (project_id BIGINT UNSIGNED,person_id BIGINT UNSIGNED) ENGINE=InnoDB');
$db->exec('INSERT INTO office_projects VALUES (17)');
$db->exec('INSERT INTO office_inzet_project_team VALUES (17,101)');
$db->exec('CREATE TABLE onsite_device_tokens (employee_id BIGINT PRIMARY KEY)');
$db->exec('INSERT INTO onsite_device_tokens VALUES (101)');
$peopleBefore=$db->query('SELECT * FROM office_inzet_people ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
check($migration->run()['status']==='READY_TO_APPLY','Fresh preflight');
check($migration->run('--postflight')['status']==='BLOCK','Missing target passed postflight');
check($db->query("SHOW TABLES LIKE 'office_onsite_%'")->fetchAll()===[],'Preflight created tables');
rejects(fn()=>$migration->run('--apply'),'Apply without confirmation');
rejects(fn()=>$migration->run('--enable'),'Activation mode accepted');
$db->beginTransaction(); rejects(fn()=>$migration->run('--apply','--confirm=office-test-onsite-schema-20261022'),'DDL inside transaction'); $db->rollBack();
$other=$connect(); $lock=$other->prepare('SELECT GET_LOCK(?,0)'); $lock->execute(['office_onsite_migration_ci:onsite-schema-20261022']);
rejects(fn()=>$migration->run(),'Concurrent migration accepted');
$other->query("SELECT RELEASE_LOCK('office_onsite_migration_ci:onsite-schema-20261022')");
$apply=fn()=>$migration->run('--apply','--confirm=office-test-onsite-schema-20261022');
$first=$apply();
check($first['status']==='SCHEMA_VERIFIED' && count($first['created_tables'])===2,'Initial apply');
check($apply()['created_tables']===[],'Repeated apply not idempotent');
check($migration->run('--postflight')['links']['usable_office_devices']===0,'Migration activated devices');

// Existing Office credential remains usable; inactive, expired, revoked and foreign credentials stay denied.
$tokens=['valid'=>str_repeat('a',48),'inactive'=>str_repeat('b',48),'expired'=>str_repeat('c',48),'revoked'=>str_repeat('d',48)];
$insert=$db->prepare('INSERT INTO office_onsite_device_tokens VALUES (?,?,?,?)');
$insert->execute([hash('sha256',$tokens['valid']),101,null,null]);
$insert->execute([hash('sha256',$tokens['inactive']),102,null,null]);
$insert->execute([hash('sha256',$tokens['expired']),101,'2020-01-01 00:00:00',null]);
$insert->execute([hash('sha256',$tokens['revoked']),101,null,'2020-01-01 00:00:00']);
$devices=new OnSiteDeviceTokenRepository($db);
check($devices->resolveEmployee($tokens['valid'])==='101','Existing employee/device link broken');
foreach (['inactive','expired','revoked'] as $kind) check($devices->resolveEmployee($tokens[$kind])===null,'Unsafe credential accepted: '.$kind);
check($devices->resolveEmployee(str_repeat('z',48))===null,'External device accepted');
$inbox=new OnSiteEventInbox($db);
$event=['project_id'=>'17','zone_id'=>'8','kind'=>'in','occurred_at'=>'2026-10-22T09:00:00.000000Z'];
check($inbox->store('101','migration_event_0001',$event),'Inbox first insert');
check(!$inbox->store('101','migration_event_0001',$event),'Inbox duplicate');
rejects(fn()=>$inbox->store('102','migration_event_0001',$event),'Conflicting employee replay');
$snapshot=static fn()=>[$db->query('SELECT * FROM office_onsite_device_tokens ORDER BY token_hash')->fetchAll(PDO::FETCH_ASSOC),$db->query('SELECT * FROM office_onsite_event_inbox')->fetchAll(PDO::FETCH_ASSOC)];
$before=$snapshot();
$report=$apply();
check($report['links']['usable_office_devices']===1 && $report['links']['assigned_usable_office_devices']===1,'Device/team audit');
check($report['links']['office_onsite_device_tokens']['inactive_people']===1,'Inactive employee audit');
check($report['legacy']['onsite_device_tokens']==='PRESENT_NOT_IMPORTED','Legacy boundary hidden');
check($report['identity_bridge']==='UNVERIFIED_REQUIRES_SEPARATE_DEVICE_AND_PERSON_ACCEPTANCE','Identity claimed verified');
check($migration->run('--rollback-plan')['status']==='RETAIN_TABLES_AND_DATA','Rollback procedure');
check($snapshot()===$before && $peopleBefore===$db->query('SELECT * FROM office_inzet_people ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),'Existing rows changed');
check($db->query('SELECT COUNT(*) FROM onsite_device_tokens')->fetchColumn()==1,'Legacy rows changed');
check(!str_contains(json_encode($report),hash('sha256',$tokens['valid'])) && !str_contains(json_encode($report),'Existing employee'),'Report leaked data');

// Resume after an independently committed first DDL (simulated interrupted apply).
$db->exec('DROP TABLE office_onsite_event_inbox');
check(count($apply()['created_tables'])===1 && $devices->resolveEmployee($tokens['valid'])==='101','Partial apply resume');
// Entire preflight completes before creating the other table.
$db->exec('DROP TABLE office_onsite_event_inbox');
$db->exec('ALTER TABLE office_onsite_device_tokens MODIFY token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL');
check($apply()['status']==='BLOCK','Case insensitive token schema accepted');
check($db->query("SHOW TABLES LIKE 'office_onsite_event_inbox'")->fetchAll()===[],'Partial creation despite incompatible sibling');
$db->exec('ALTER TABLE office_onsite_device_tokens MODIFY token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');
check($apply()['status']==='SCHEMA_VERIFIED','Resume after operator repair');
$db->exec('ALTER TABLE office_onsite_event_inbox DROP FOREIGN KEY fk_onsite_person');
check($apply()['status']==='BLOCK','Missing foreign key accepted');
$db->exec('ALTER TABLE office_onsite_event_inbox ADD CONSTRAINT fk_onsite_person FOREIGN KEY (person_id) REFERENCES office_inzet_people(id)');
$db->exec('ALTER TABLE office_onsite_event_inbox DROP PRIMARY KEY');
check($apply()['status']==='BLOCK','Missing index accepted');
$db->exec('ALTER TABLE office_onsite_event_inbox ADD PRIMARY KEY(event_id)');
$db->exec('CREATE TRIGGER unexpected_device_trigger BEFORE INSERT ON office_onsite_device_tokens FOR EACH ROW SET NEW.person_id=101');
check($apply()['status']==='BLOCK','Unexpected trigger accepted');
$db->exec('DROP TRIGGER unexpected_device_trigger');
$db->exec('SET FOREIGN_KEY_CHECKS=0');
$insert->execute([str_repeat('f',64),999,null,null]);
$db->exec('SET FOREIGN_KEY_CHECKS=1');
check($apply()['status']==='BLOCK','Orphan credential accepted');
$db->exec('DELETE FROM office_onsite_device_tokens WHERE person_id=999');
$insert->execute([str_repeat('X',64),101,null,null]);
check($apply()['status']==='BLOCK','Malformed credential accepted');
$db->exec("DELETE FROM office_onsite_device_tokens WHERE token_hash='".str_repeat('X',64)."'");
check($migration->run('--postflight')['status']==='SCHEMA_VERIFIED','Final postflight');
echo "Office-test OnSite migration, idempotency, interruption, schema drift, identity and rollback checks OK\n";
