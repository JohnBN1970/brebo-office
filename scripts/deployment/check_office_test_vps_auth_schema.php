#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * Read-only VPS auth/schema readiness preflight.
 * Run locally on the isolated VPS with OFFICE_INTAKE_* environment set.
 * Never creates tables, prints credentials, or opens the router.
 */
$root=realpath($argv[1]??dirname(__DIR__,2));
if(!$root||!is_dir($root.'/public')||!is_dir($root.'/src/Intake')){fwrite(STDERR,"FAIL RELEASE_ROOT\n");exit(2);}
$required=[
  'public/office-login.php',
  'src/Intake/WebsiteOfficeLoginController.php',
  'src/Intake/WebsiteOfficeLoginCsrf.php',
  'src/Intake/WebsiteOfficeLoginFormRenderer.php',
  'src/Intake/WebsiteOfficeLoginThrottle.php',
  'src/Intake/WebsiteOfficeOperatorAuthenticator.php',
  'src/Intake/WebsiteOfficeCrmSessionIssuer.php',
  'src/Intake/WebsiteOfficeCrmSessionRepository.php',
  'database/office_dashboard_preferences.sql',
];
$ok=true;
foreach($required as $file){$present=is_file($root.'/'.$file);echo ($present?'PASS ':'MISSING ').$file."\n";$ok=$ok&&$present;}
$dsn=getenv('OFFICE_INTAKE_PDO_DSN');
if(!is_string($dsn)||$dsn===''||!str_starts_with($dsn,'mysql:')){echo "SKIP DB_ENV_NOT_SET\n";exit($ok?0:2);}
try{
  $db=new PDO($dsn,getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $dbName=$db->query('SELECT DATABASE()')->fetchColumn();
  if(!is_string($dbName)||$dbName===''){throw new RuntimeException('No database selected');}
  $tables=['office_operator_accounts','office_operator_sessions','office_login_attempts','office_projects','office_project_memberships','office_dashboard_preferences'];
  $stmt=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
  foreach($tables as $table){$stmt->execute([$table]);$present=(int)$stmt->fetchColumn()===1;echo ($present?'PASS ':'MISSING ').$table."\n";$ok=$ok&&$present;}
  $columns=['office_operator_accounts'=>['id','username','password_hash','can_edit_crm','active'],'office_operator_sessions'=>['token_hash','operator_id','expires_at','revoked_at']];
  $q=$db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
  foreach($columns as $table=>$names){foreach($names as $name){$q->execute([$table,$name]);$present=(int)$q->fetchColumn()===1;echo ($present?'PASS ':'MISSING ').$table.'.'.$name."\n";$ok=$ok&&$present;}}
}catch(Throwable $e){echo 'FAIL DB_PREFLIGHT '.get_class($e)."\n";$ok=false;}
echo $ok?"VPS_AUTH_PREFLIGHT=PASS\n":"VPS_AUTH_PREFLIGHT=BLOCKED\n";
exit($ok?0:2);
