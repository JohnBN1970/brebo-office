<?php
declare(strict_types=1);
/**
 * Read-only, fail-closed Office-test OnSite schema preflight.
 * CLI only. Prints table/column statuses, never credentials or personal data.
 */
if (PHP_SAPI!=='cli') {http_response_code(404);exit(1);}
$configFile='/home/u213420663/domains/brebobv.nl/office_calc_test/config/config.php';
try {
  $c=require $configFile;
  if (!is_array($c) || ($c['OFFICE_BASE_PATH']??null)!=='/office-test'
    || ($c['OFFICE_PUBLIC_HOST']??null)!=='brebobv.nl'
    || ($c['OFFICE_INTAKE_DB_USER']??null)!=='u213420663_office_test'
    || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',(string)($c['OFFICE_INTAKE_PDO_DSN']??'')))
    throw new RuntimeException('test_isolation_failed');
  $db=new PDO($c['OFFICE_INTAKE_PDO_DSN'],$c['OFFICE_INTAKE_DB_USER'],$c['OFFICE_INTAKE_DB_PASSWORD'],
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  $required=[
    'office_inzet_people'=>['id','active'],
    'office_inzet_project_team'=>['person_id','project_id'],
    'office_operator_accounts'=>['id','active'],
    'office_project_memberships'=>['operator_id','project_id','permission'],
    'office_onsite_event_inbox'=>['event_id','person_id','project_id','zone_id','event_kind','occurred_at_utc'],
    'office_onsite_device_tokens'=>['token_hash','person_id','expires_at','revoked_at'],
    'onsite_event_inbox'=>['event_id','employee_id','project_id','zone_id','kind','occurred_at'],
    'onsite_device_tokens'=>[],
  ];
  $result=[];
  $missing=0;
  foreach ($required as $table=>$columns) {
    $stmt=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    $actual=$stmt->fetchAll(PDO::FETCH_COLUMN);
    $absent=array_values(array_diff($columns,$actual));
    $state=$actual===[]?'MISSING_TABLE':($absent!==[]?'MISSING_COLUMNS':'PRESENT');
    if ($state!=='PRESENT') $missing++;
    $result[$table]=['status'=>$state,'missing_columns'=>$absent];
  }
  echo json_encode(['status'=>$missing?'BLOCK':'SCHEMA_PRESENT_NOT_YET_VERIFIED','tables'=>$result,
    'note'=>'No writes. Presence identity mapping and ingestion must be separately verified.'],
    JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),PHP_EOL;
  exit($missing?2:0);
} catch (Throwable $e) {
  echo json_encode(['status'=>'BLOCK','reason'=>'preflight_failed'],JSON_PRETTY_PRINT),PHP_EOL;
  exit(1);
}
