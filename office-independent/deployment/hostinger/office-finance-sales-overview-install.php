<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');umask(0077);$phase='scope';$journal=[];$published=[];$installedClass=false;
function salesOverviewReplace(string $file,string $expected,string $bytes,int $mode): void {
  if(is_link($file)||realpath($file)!==$file||hash_file('sha256',$file)!==$expected)throw new RuntimeException('Concurrent source change');
  $tmp=tempnam(dirname($file),'.sales-overview-');try{chmod($tmp,$mode);if(file_put_contents($tmp,$bytes,LOCK_EX)!==strlen($bytes)||hash_file('sha256',$file)!==$expected||!rename($tmp,$file))throw new RuntimeException('Atomic update failed');}finally{if(is_file($tmp))unlink($tmp);}
}
try{
  $stage=dirname(__DIR__,2);if(($argv[1]??'')!=='--connect-native-sales'||!preg_match('/^office-sales-stage-\d+-\d+$/D',basename($stage)))throw new RuntimeException('Explicit stage required');
  $base=rtrim((string)getenv('HOME'),'/').'/domains/brebobv.nl/office_calc_test';$configFile=$base.'/config/config.php';
  if(is_link($configFile)||realpath($configFile)!==$configFile)throw new RuntimeException('Unsafe config');
  $configHash=hash_file('sha256',$configFile);$config=require $configFile;$effectiveHash=hash('sha256',serialize($config));$root=$config['OFFICE_PRIVATE_ROOT']??'';
  if(($config['OFFICE_BASE_PATH']??'')!=='/office-test'||!is_string($root)||!str_starts_with($root,$base.'/release-')||!str_ends_with($root,'/private')||realpath($root)!==$root||($config['OFFICE_SALES_ENABLED']??'')!=='enabled'||($config['OFFICE_FINANCE_ENABLED']??'')!=='enabled'||($config['OFFICE_SALES_SCHEMA_VERIFIED']??'')!=='enabled'
    ||!preg_match('/^mysql:host=(?:localhost|127\.0\.0\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$config['OFFICE_INTAKE_PDO_DSN']??'')||($config['OFFICE_INTAKE_DB_USER']??'')!=='u213420663_office_test')throw new RuntimeException('Wrong Office-test scope');
  $db=new PDO($config['OFFICE_INTAKE_PDO_DSN'],$config['OFFICE_INTAKE_DB_USER'],$config['OFFICE_INTAKE_DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  if((int)$db->query("SELECT GET_LOCK('office-native-sales-install',0)")->fetchColumn()!==1)throw new RuntimeException('Concurrent installation');
  $changes=json_decode(file_get_contents(__DIR__.'/office-finance-sales-overview-changes.json'),true,32,JSON_THROW_ON_ERROR);
  if(array_keys($changes)!==['src/Finance/OfficeFinanceView.php','public/office-finance.php'])throw new RuntimeException('Wrong patch scope');
  $preview=$stage.'/native-overview-preview';if(file_exists($preview)||!mkdir($preview,0700))throw new RuntimeException('Preview exists');foreach(['src/Finance','src/Intake','public','backup/src/Finance','backup/public'] as $dir)if(!mkdir($preview.'/'.$dir,0700,true)&&!is_dir($preview.'/'.$dir))throw new RuntimeException('Preview failed');
  $phase='reviewed_anchors';
  foreach($changes as $path=>$chunks){
    $file=$root.'/'.$path;if(is_link($file)||realpath($file)!==$file)throw new RuntimeException('Unsafe source');$before=file_get_contents($file);$after=$before;
    foreach($chunks as $chunk){if(substr_count($after,$chunk['before'])!==1)throw new RuntimeException('Reviewed anchor not unique');$after=str_replace($chunk['before'],$chunk['after'],$after);}
    token_get_all($after,TOKEN_PARSE);$journal[$path]=['before'=>hash('sha256',$before),'after'=>hash('sha256',$after),'mode'=>fileperms($file)&0777];
    if(file_put_contents($preview.'/'.$path,$after,LOCK_EX)!==strlen($after)||file_put_contents($preview.'/backup/'.$path,$before,LOCK_EX)!==strlen($before))throw new RuntimeException('Verified backup failed');
  }
  foreach(['src/Intake/OfficeMount.php','src/Finance/OfficeFinanceBankPanel.php','src/Finance/OfficeFinanceAccess.php'] as $path){if(is_link($root.'/'.$path)||realpath($root.'/'.$path)!==$root.'/'.$path||!copy($root.'/'.$path,$preview.'/'.$path))throw new RuntimeException('Preview dependency failed');}
  $classPath='src/Finance/OfficeFinanceSalesOverview.php';$classBytes=file_get_contents($stage.'/'.$classPath);token_get_all($classBytes,TOKEN_PARSE);file_put_contents($preview.'/'.$classPath,$classBytes,LOCK_EX);
  $phase='read_only_runtime';$db->exec('SET SESSION TRANSACTION READ ONLY');$db->beginTransaction();
  require $preview.'/src/Finance/OfficeFinanceAccess.php';require $preview.'/'.$classPath;require $preview.'/src/Intake/OfficeMount.php';require $preview.'/src/Finance/OfficeFinanceBankPanel.php';require $preview.'/src/Finance/OfficeFinanceView.php';
  putenv('OFFICE_BASE_PATH=/office-test');$today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Amsterdam')))->format('Y-m-d');
  $snapshot=\Brebo\Office\Finance\OfficeFinanceSalesOverview::read($db,'1',null,$today);if(($snapshot['available']??false)!==true)throw new RuntimeException('Sales source unavailable');
  $keys=array_keys(\Brebo\Office\Finance\OfficeFinanceView::QUEUES);$data=['counts'=>array_fill_keys($keys,0),'amounts_inc_vat'=>array_fill_keys($keys,'0.00'),'queues'=>array_fill_keys($keys,[]),'generated_at'=>time(),'sales_overview'=>$snapshot];
  $html=\Brebo\Office\Finance\OfficeFinanceView::render($data,'dashboard');if(!str_contains($html,'Openstaande verkoopfacturen')||!str_contains($html,'Naar verkoopfacturen')||str_contains($html,'Geboekte verkoopfacturen uit BREBO nog niet aangesloten.'))throw new RuntimeException('Native sales rendering failed');$db->rollBack();
  $phase='install';
  foreach($journal as $path=>$row)if(hash_file('sha256',$root.'/'.$path)!==$row['before']||hash_file('sha256',$preview.'/backup/'.$path)!==$row['before']||hash_file('sha256',$preview.'/'.$path)!==$row['after'])throw new RuntimeException('Source or backup changed');
  if(hash_file('sha256',$configFile)!==$configHash||hash('sha256',serialize(require $configFile))!==$effectiveHash)throw new RuntimeException('Config changed');
  if(file_exists($root.'/'.$classPath)||is_link($root.'/'.$classPath))throw new RuntimeException('Existing overview source requires review');
  $h=fopen($root.'/'.$classPath,'x');if($h===false)throw new RuntimeException('Class publication failed');try{if(fwrite($h,$classBytes)!==strlen($classBytes))throw new RuntimeException('Short write');}finally{fclose($h);}chmod($root.'/'.$classPath,0640);$installedClass=true;
  if(hash_file('sha256',$root.'/'.$classPath)!==hash('sha256',$classBytes))throw new RuntimeException('Class checksum mismatch');
  file_put_contents($preview.'/JOURNAL.json',json_encode(['files'=>$journal,'new_class_sha256'=>hash('sha256',$classBytes),'database_write'=>false],JSON_THROW_ON_ERROR),LOCK_EX);
  foreach($journal as $path=>$row){salesOverviewReplace($root.'/'.$path,$row['before'],file_get_contents($preview.'/'.$path),$row['mode']);$published[]=$path;}
  foreach($journal as $path=>$row)if(hash_file('sha256',$root.'/'.$path)!==$row['after'])throw new RuntimeException('Installed checksum mismatch');
  if(hash_file('sha256',$configFile)!==$configHash||hash('sha256',serialize(require $configFile))!==$effectiveHash)throw new RuntimeException('Config changed');
  $db->query("SELECT RELEASE_LOCK('office-native-sales-install')");
  echo "OFFICE_FINANCE_NATIVE_SALES_CONNECTED=1\nOFFICE_FINANCE_OVERVIEW_RUNTIME_OK=1\nOFFICE_FINANCE_OVERVIEW_SCOPE_VERIFIED=1\nOFFICE_FINANCE_OVERVIEW_NO_SCHEMA_CHANGES=1\nOFFICE_FINANCE_OVERVIEW_NO_DATA_WRITES=1\nOFFICE_FINANCE_OVERVIEW_PROVIDER_NOT_CALLED=1\nOFFICE_FINANCE_OVERVIEW_CONFIG_UNCHANGED=1\n";
}catch(Throwable $e){
  if(isset($db)&&$db->inTransaction())$db->rollBack();echo 'OFFICE_FINANCE_SALES_OVERVIEW_FAILED_PHASE='.$phase."\n";
  try{foreach(array_reverse($published) as $path){$row=$journal[$path];salesOverviewReplace($root.'/'.$path,$row['after'],file_get_contents($preview.'/backup/'.$path),$row['mode']);}echo "OFFICE_FINANCE_OVERVIEW_PRIOR_FILES_RESTORED=1\n";}catch(Throwable){echo "OFFICE_FINANCE_OVERVIEW_RESTORE_REVIEW_REQUIRED=1\n";}
  // Retain any additive source after a partial install for safe inspection; no schema/data rollback is needed.
  exit(1);
}
