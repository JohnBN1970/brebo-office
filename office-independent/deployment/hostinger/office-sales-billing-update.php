<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');umask(0077);
$phase='scope';$createdSchema=false;$activated=false;
function billingPublish(string $file,string $expected,string $bytes,int $mode): void {
  if(is_link($file)||realpath($file)!==$file||hash_file('sha256',$file)!==$expected)throw new RuntimeException('Concurrent module change');
  $tmp=tempnam(dirname($file),'.billing-');
  try{chmod($tmp,$mode);if(file_put_contents($tmp,$bytes,LOCK_EX)!==strlen($bytes)||hash_file('sha256',$file)!==$expected||!rename($tmp,$file))throw new RuntimeException('Atomic module activation failed');}finally{if(is_file($tmp))unlink($tmp);}
}
try{
  $stage=dirname(__DIR__,2);$suffix=basename($stage);
  if(($argv[1]??'')!=='--upgrade-billing'||!preg_match('/^office-sales-stage-(\d+-\d+)$/D',$suffix,$match))throw new RuntimeException('Explicit staged upgrade required');
  $base=rtrim((string)getenv('HOME'),'/').'/domains/brebobv.nl/office_calc_test';$configFile=$base.'/config/config.php';
  if(is_link($configFile)||realpath($configFile)!==$configFile)throw new RuntimeException('Unsafe config');
  $configHash=hash_file('sha256',$configFile);$config=require $configFile;$effectiveConfigHash=hash('sha256',serialize($config));$root=$config['OFFICE_PRIVATE_ROOT']??'';
  if(!is_array($config)||($config['OFFICE_BASE_PATH']??'')!=='/office-test'||!is_string($root)||!str_starts_with($root,$base.'/release-')||!str_ends_with($root,'/private')||realpath($root)!==$root||($config['OFFICE_SALES_ENABLED']??'')!=='enabled'
    ||!preg_match('/^mysql:host=(?:localhost|127\.0\.0\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$config['OFFICE_INTAKE_PDO_DSN']??'')||($config['OFFICE_INTAKE_DB_USER']??'')!=='u213420663_office_test')throw new RuntimeException('Wrong isolated test scope');
  $db=new PDO($config['OFFICE_INTAKE_PDO_DSN'],$config['OFFICE_INTAKE_DB_USER'],$config['OFFICE_INTAKE_DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  if((int)$db->query("SELECT GET_LOCK('office-native-sales-install',0)")->fetchColumn()!==1)throw new RuntimeException('Sales update already running');
  $phase='baseline';$baseline=json_decode(file_get_contents(__DIR__.'/office-sales-billing-baseline.json'),true,16,JSON_THROW_ON_ERROR);
  foreach($baseline as $path=>$sha){$file=$root.'/'.$path;if(is_link($file)||realpath($file)!==$file)throw new RuntimeException('Unsafe active file');$bytes=file_get_contents($file);if(sha1('blob '.strlen($bytes)."\0".$bytes)!==$sha)throw new RuntimeException('Active sales source differs from reviewed version');}
  $q=$db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute(['office_finance_sales_context']);if((int)$q->fetchColumn()!==0)throw new RuntimeException('Existing new billing schema requires review');
  foreach(['office_project_contracts'=>['id','office_project_id','contract_number','contract_amount','currency','status'],'office_project_terms'=>['id','contract_id','term_number','description','percentage','amount','status','due_date']] as $table=>$columns){$q=$db->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);if(array_diff($columns,$q->fetchAll(PDO::FETCH_COLUMN))!==[])throw new RuntimeException('Term schema differs from preflight');}
  $phase='private_bundle';$version='billing-v2-'.$match[1];$bundle=$root.'/src/Sales/'.$version;$entryName='office-sales-billing-'.$match[1].'.php';$entry=$root.'/public/'.$entryName;
  if(file_exists($bundle)||is_link($bundle)||file_exists($entry)||is_link($entry)||!mkdir($bundle,0750))throw new RuntimeException('Versioned bundle already exists');
  $files=['OfficeSalesBilling.php','OfficeSalesSchema.php','OfficeSalesService.php','OfficeSalesView.php','OfficeSalesPdf.php','OfficeSalesTransport.php','brebo-logo.rgb.json','brebo-logo-web.json','brebo-fonts.json','brebo-fonts-LICENSE.txt'];$hashes=[];
  foreach($files as $name){$source=$stage.'/src/Sales/'.$name;$bytes=file_get_contents($source);if(str_ends_with($name,'.php'))token_get_all($bytes,TOKEN_PARSE);$dest=$bundle.'/'.$name;$h=fopen($dest,'x');if($h===false)throw new RuntimeException('Bundle path collision');try{if(fwrite($h,$bytes)!==strlen($bytes))throw new RuntimeException('Bundle copy failed');}finally{fclose($h);}chmod($dest,0640);$hashes[$dest]=hash('sha256',$bytes);if(hash_file('sha256',$dest)!==$hashes[$dest])throw new RuntimeException('Bundle checksum mismatch');}
  $controller=file_get_contents($stage.'/public/office-sales.php');$controller=str_replace("'/src/Sales/","'/src/Sales/".$version.'/',$controller,$count);if($count!==2)throw new RuntimeException('Unexpected controller dependencies');token_get_all($controller,TOKEN_PARSE);
  $h=fopen($entry,'x');if($h===false)throw new RuntimeException('Controller path collision');try{if(fwrite($h,$controller)!==strlen($controller))throw new RuntimeException('Controller copy failed');}finally{fclose($h);}chmod($entry,0640);$hashes[$entry]=hash('sha256',$controller);
  $manifest=$root.'/modules/sales/routes.php';$before=file_get_contents($manifest);$manifestHash=hash('sha256',$before);$after=str_replace("'office-sales.php'","'".$entryName."'",$before,$count);if($count!==1)throw new RuntimeException('Unexpected module route');token_get_all($after,TOKEN_PARSE);
  if(file_put_contents($stage.'/module-route-before.php',$before,LOCK_EX)!==strlen($before)||hash_file('sha256',$stage.'/module-route-before.php')!==$manifestHash)throw new RuntimeException('Module backup failed');
  $phase='additive_schema';require $bundle.'/OfficeSalesBilling.php';$db->exec(\Brebo\Office\Sales\OfficeSalesBilling::schema());$createdSchema=true;
  $db->query('SELECT invoice_id,price_mode,labor_cents,g_rate_units,g_cents,g_iban,source_contract_id,source_term_id,term_percent_units,source_snapshot,summary_json FROM office_finance_sales_context WHERE 1=0');
  $phase='read_only_runtime';$db->exec('SET SESSION TRANSACTION READ ONLY');$db->beginTransaction();
  require $bundle.'/OfficeSalesService.php';require $bundle.'/OfficeSalesView.php';require $bundle.'/OfficeSalesPdf.php';
  $service=new \Brebo\Office\Sales\OfficeSalesService($db,'1');$rows=$service->list();$customers=$service->customers();$projects=$service->projects();$terms=$service->termChoices();
  $html=\Brebo\Office\Sales\OfficeSalesView::render($rows,null,$customers,$projects,[],'check',true,false,date('Y-m-d'),'',true,false,[],'check');
  if(!str_contains($html,'sales-price-mode')||!str_contains($html,'sales-g-percent'))throw new RuntimeException('Billing form failed');
  if($terms!==[]){$draft=$service->termDraft((int)$terms[0]['id'],date('Y-m-d'));$html=\Brebo\Office\Sales\OfficeSalesView::render([],null,$customers,$projects,[],'check',true,false,date('Y-m-d'),'',true,false,[],'check',$draft);if(!str_contains($html,'sales-term-percent')||!str_contains($html,'Aanneemsom en termijnen'))throw new RuntimeException('Term draft failed');}
  $db->rollBack();
  $phase='activate';foreach($hashes as $file=>$hash)if(hash_file('sha256',$file)!==$hash)throw new RuntimeException('Bundle changed before activation');
  foreach($baseline as $path=>$sha){$bytes=file_get_contents($root.'/'.$path);if(sha1('blob '.strlen($bytes)."\0".$bytes)!==$sha)throw new RuntimeException('Concurrent source edit');}
  if(hash_file('sha256',$configFile)!==$configHash||hash('sha256',serialize(require $configFile))!==$effectiveConfigHash)throw new RuntimeException('Configuration changed');
  if(file_put_contents($stage.'/billing-upgrade-journal.json',json_encode(['bundle'=>$bundle,'controller'=>$entry,'manifest'=>$manifest,'before'=>$manifestHash,'after'=>hash('sha256',$after),'files'=>$hashes],JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Journal failed');
  billingPublish($manifest,$manifestHash,$after,fileperms($manifest)&0777);$activated=true;
  $db->query("SELECT RELEASE_LOCK('office-native-sales-install')");
  echo "OFFICE_SALES_BILLING_V2_ACTIVE=1\nOFFICE_SALES_INCLUSIVE_PRICES_READY=1\nOFFICE_SALES_TERM_SOURCE_READY=1\nOFFICE_SALES_G_ACCOUNT_LABOR_BASIS=1\nOFFICE_SALES_CONTRACT_SUMMARY_READY=1\nOFFICE_SALES_EXISTING_INVOICES_UNCHANGED=1\nOFFICE_SALES_CONFIG_UNCHANGED=1\nOFFICE_SALES_PROVIDER_NOT_CALLED=1\n";
}catch(Throwable $e){
  if(isset($db)&&$db->inTransaction())$db->rollBack();
  echo 'OFFICE_SALES_BILLING_FAILED_PHASE='.$phase."\nOFFICE_SALES_BILLING_SCHEMA_CREATED=".(int)$createdSchema."\nOFFICE_SALES_BILLING_ROUTE_ACTIVATED=".(int)$activated."\nOFFICE_SALES_NO_TABLES_DROPPED=1\n";exit(1);
}
