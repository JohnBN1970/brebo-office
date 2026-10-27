<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$base='/home/u213420663/domains/brebobv.nl/office_calc_test';
$config=require $base.'/config/config.php';
$root=realpath($config['OFFICE_PRIVATE_ROOT']??'');
if(!is_string($root)||!str_starts_with($root,$base.'/')||!str_ends_with($root,'/private')
 ||($config['OFFICE_BASE_PATH']??'')!=='/office-test'
 ||!preg_match('/^mysql:host=(?:localhost|127\.0\.0\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$config['OFFICE_INTAKE_PDO_DSN']??'')
 ||($config['OFFICE_INTAKE_DB_USER']??'')!=='u213420663_office_test')throw new RuntimeException('Isolated Office-test configuration required');
$db=new PDO($config['OFFICE_INTAKE_PDO_DSN'],$config['OFFICE_INTAKE_DB_USER'],$config['OFFICE_INTAKE_DB_PASSWORD']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();
$q=$db->query("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('office_onsite_hours_reviews','office_onsite_reviewed_sessions','office_onsite_event_inbox','office_inzet_people') ORDER BY TABLE_NAME,ORDINAL_POSITION");
$columns=[];
foreach($q->fetchAll(PDO::FETCH_ASSOC)as$row){
 $columns[$row['TABLE_NAME']][$row['COLUMN_NAME']]=$row;
 if(in_array($row['TABLE_NAME'],['office_onsite_hours_reviews','office_onsite_reviewed_sessions'],true)||in_array($row['COLUMN_NAME'],['id','event_id'],true)) echo 'SCHEMA | '.$row['TABLE_NAME'].' | '.$row['COLUMN_NAME'].' | '.$row['COLUMN_TYPE'].' | '.($row['COLLATION_NAME']??'none').PHP_EOL;
}
$db->rollBack();
foreach(['OnSiteBootstrapService'=>'bootstrap','OnSiteBootstrapRepository'=>'forEmployee']as$class=>$method){
 $file=$root.'/src/Inzet/'.$class.'.php';
 if(is_file($file)){
  require_once $file;
  $ref=new ReflectionMethod('Brebo\\Office\\Inzet\\'.$class,$method);
  echo 'RUNTIME_CONTRACT | '.$class.' | '.implode(',',array_map(static fn(ReflectionParameter $p):string=>$p->getName().':'.(string)$p->getType(),$ref->getParameters())).' | '.hash_file('sha256',$file).PHP_EOL;
 }
}
echo 'ROUTER_SHA256 | '.hash_file('sha256','/home/u213420663/domains/brebobv.nl/public_html/office-test/index.php').PHP_EOL;
echo 'BOOTSTRAP_ENDPOINT_SHA256 | '.hash_file('sha256',$root.'/public/onsite-bootstrap.php').PHP_EOL;
echo 'DATABASE_COLLATION | '.$db->query('SELECT @@collation_database')->fetchColumn().PHP_EOL;
$required=['person_id','project_id','zone_id','in_event_id','out_event_id','approval_status','reviewed_seconds','reviewer_operator_id','approved_at','reviewed_at','reason','revision','request_key','request_fingerprint'];
$present=!array_diff($required,array_keys($columns['office_onsite_hours_reviews']??[]));
echo 'Canonical hours ledger shape: '.($present?'PRESENT':'MISSING_OR_DIFFERENT').PHP_EOL;
foreach(['OFFICE_ONSITE_HOURS_REVIEWS_SCHEMA_VERIFIED','OFFICE_TEST_ONSITE_HOURS_REVIEW_ENABLED']as$flag)echo $flag.': '.(($config[$flag]??'')==='enabled'?'enabled':'disabled').PHP_EOL;
echo 'Review signing secret configured: '.(strlen($config['OFFICE_TEST_ONSITE_HOURS_REVIEW_CSRF_SECRET']??'')>=32?'yes':'no').PHP_EOL;
echo 'Review endpoint installed: '.(is_file($root.'/public/office-onsite-hours-review.php')?'yes':'no').PHP_EOL;
echo $present?'SCHEMA_REVIEW_REQUIRED_BEFORE_ENABLE'.PHP_EOL:'NEEDS_EXPLICIT_TEST_SCHEMA_APPROVAL'.PHP_EOL;
echo 'Read-only discovery complete; no migration, flag change, deploy or event writes.'.PHP_EOL;
