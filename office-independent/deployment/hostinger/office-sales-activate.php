<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
ini_set('display_errors','0'); umask(0077);
require dirname(__DIR__,2).'/src/Sales/OfficeSalesSchema.php';
function salesReplace(string $file,string $expected,string $bytes,int $mode): void {
  if (is_link($file) || realpath($file)!==$file || hash_file('sha256',$file)!==$expected) throw new RuntimeException('Concurrent source change');
  $tmp=tempnam(dirname($file),'.sales-');
  try {
    chmod($tmp,$mode);
    if (file_put_contents($tmp,$bytes,LOCK_EX)!==strlen($bytes) || hash_file('sha256',$file)!==$expected || !rename($tmp,$file)) throw new RuntimeException('Atomic replacement failed');
  } finally { if (is_file($tmp)) unlink($tmp); }
}
$stage=dirname(__DIR__,2); $base=rtrim((string)getenv('HOME'),'/').'/domains/brebobv.nl/office_calc_test';
$configFile=$base.'/config/config.php'; $router=dirname($base).'/public_html/office-test/index.php';
$phase='scope'; $journal=[]; $created=[]; $active=false;
try {
  if (($argv[1]??'')!=='--activate-drafts-only' || !preg_match('/^office-sales-stage-\d+-\d+$/D',basename($stage))) throw new RuntimeException('Explicit staged installation required');
  if (is_link($configFile) || realpath($configFile)!==$configFile) throw new RuntimeException('Unsafe configuration');
  $beforeConfig=file_get_contents($configFile); $config=require $configFile; $root=$config['OFFICE_PRIVATE_ROOT']??'';
  if (!is_array($config) || ($config['OFFICE_BASE_PATH']??'')!=='/office-test' || !is_string($root) || !str_starts_with($root,$base.'/release-') || !str_ends_with($root,'/private') || realpath($root)!==$root
      || !preg_match('/^mysql:host=(?:localhost|127\.0\.0\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$config['OFFICE_INTAKE_PDO_DSN']??'') || ($config['OFFICE_INTAKE_DB_USER']??'')!=='u213420663_office_test' || ($config['OFFICE_SALES_ENABLED']??'disabled')==='enabled') throw new RuntimeException('Wrong test scope or already activated');
  $db=new PDO($config['OFFICE_INTAKE_PDO_DSN'],$config['OFFICE_INTAKE_DB_USER'],$config['OFFICE_INTAKE_DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  if ((int)$db->query("SELECT GET_LOCK('office-native-sales-install',0)")->fetchColumn()!==1) throw new RuntimeException('Installation already running');
  $phase='prerequisites';
  foreach (['read','draft_edit'] as $permission) {
    $q=$db->prepare("SELECT 1 FROM office_module_permissions p JOIN office_operator_accounts a ON a.id=p.operator_id WHERE a.id=? AND a.active=1 AND p.module='finance' AND p.permission=?");$q->execute(['1',$permission]);if($q->fetchColumn()===false)throw new RuntimeException('Existing operator permission required');
  }
  $tables=['office_finance_sales_invoice','office_finance_sales_delivery','office_finance_sales_audit','office_finance_sales_receipt'];
  foreach ($tables as $table) { $q=$db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);if((int)$q->fetchColumn()!==0)throw new RuntimeException('Existing sales schema requires review'); }
  $paths=['src/Sales/OfficeSalesSchema.php','src/Sales/OfficeSalesService.php','src/Sales/OfficeSalesTransport.php','src/Sales/OfficeSalesView.php','public/office-sales.php'];
  foreach ($paths as $path) { if (file_exists($root.'/'.$path)||is_link($root.'/'.$path)||!is_file($stage.'/'.$path))throw new RuntimeException('New source path not empty');token_get_all(file_get_contents($stage.'/'.$path),TOKEN_PARSE); }
  if (is_link($root.'/src/Sales') || (is_dir($root.'/src/Sales') && scandir($root.'/src/Sales')!==['.','..'])) throw new RuntimeException('Existing sales directory requires review');
  $view=$root.'/src/Finance/OfficeFinanceView.php';
  foreach ([$router,$view] as $file) if (is_link($file)||realpath($file)!==$file)throw new RuntimeException('Unsafe active file');
  $oldRouter=file_get_contents($router);$oldView=file_get_contents($view);
  $routeAnchor="  '/office-test/finance' => 'office-finance.php',";
  // Literal PHP source anchor, not a rendered value.
  $navAnchor="<a href=\"'.\$newInvoice.'\">Nieuwe inkoopfactuur</a></nav>";
  if (substr_count($oldRouter,$routeAnchor)!==1 || substr_count($oldView,$navAnchor)!==1 || str_contains($oldRouter,"'/office-test/finance/verkoop'"))throw new RuntimeException('Reviewed router/navigation anchor missing');
  $newRouter=str_replace($routeAnchor,$routeAnchor."\n  '/office-test/finance/verkoop' => 'office-sales.php',",$oldRouter);
  $newView=str_replace($navAnchor,str_replace('</nav>','<a href="/office-test/finance/verkoop">Verkoopfacturen</a></nav>',$navAnchor),$oldView);
  token_get_all($newRouter,TOKEN_PARSE);token_get_all($newView,TOKEN_PARSE);
  $suffix=basename($stage);$baseConfig=$base.'/config/config-sales-base-'.$suffix.'.php';
  if(file_exists($baseConfig)||is_link($baseConfig))throw new RuntimeException('Backup path exists');
  mkdir($stage.'/backup',0700);
  foreach ([$configFile=>$beforeConfig,$router=>$oldRouter,$view=>$oldView] as $file=>$content) {
    $backup=$stage.'/backup/'.hash('sha256',$file).'.php';
    if(file_put_contents($backup,$content,LOCK_EX)!==strlen($content)||hash_file('sha256',$backup)!==hash('sha256',$content))throw new RuntimeException('Backup verification failed');
    $journal[$file]=['before'=>hash('sha256',$content),'mode'=>fileperms($file)&0777,'backup'=>$backup];
  }
  if(file_put_contents($baseConfig,$beforeConfig,LOCK_EX)!==strlen($beforeConfig)||hash_file('sha256',$baseConfig)!==$journal[$configFile]['before'])throw new RuntimeException('Configuration backup failed');
  $wrapper="<?php\ndeclare(strict_types=1);\n\$config=require __DIR__.'/".basename($baseConfig)."';\n\$config['OFFICE_SALES_ENABLED']='enabled';\n\$config['OFFICE_SALES_SCHEMA_VERIFIED']='enabled';\n\$config['OFFICE_SALES_SEND_ENABLED']='disabled';\n\$config['OFFICE_SALES_REMINDERS_ENABLED']='disabled';\nreturn \$config;\n";
  token_get_all($wrapper,TOKEN_PARSE);
  $phase='additive_schema';
  foreach (\Brebo\Office\Sales\OfficeSalesSchema::statements() as $sql) $db->exec($sql);
  foreach ($tables as $table) $db->query('SELECT * FROM `'.$table.'` WHERE 1=0');
  echo "OFFICE_SALES_NEW_SCHEMA_VERIFIED=1\n";
  $phase='source';
  if(!is_dir($root.'/src/Sales'))mkdir($root.'/src/Sales',0750);
  foreach($paths as $path){$dest=$root.'/'.$path;$handle=fopen($dest,'x');if($handle===false)throw new RuntimeException('Concurrent new source');$created[$dest]=hash_file('sha256',$stage.'/'.$path);try{$bytes=file_get_contents($stage.'/'.$path);if(fwrite($handle,$bytes)!==strlen($bytes))throw new RuntimeException('Source copy failed');}finally{fclose($handle);}chmod($dest,0640);if(hash_file('sha256',$dest)!==$created[$dest])throw new RuntimeException('Source copy mismatch');}
  require_once $root.'/src/Sales/OfficeSalesService.php';
  $service=new \Brebo\Office\Sales\OfficeSalesService($db,'1');
  if($service->list()!==[])throw new RuntimeException('New sales register unexpectedly populated');
  $phase='activate'; $active=true;
  foreach([$view=>$newView,$router=>$newRouter,$configFile=>$wrapper] as $file=>$content){$journal[$file]['after']=hash('sha256',$content);salesReplace($file,$journal[$file]['before'],$content,$journal[$file]['mode']);}
  foreach($journal as $file=>$row)if(hash_file('sha256',$file)!==$row['after'])throw new RuntimeException('Installed source changed');
  file_put_contents($stage.'/activation-journal.json',json_encode(['root'=>$root,'files'=>$journal,'created'=>$created],JSON_THROW_ON_ERROR),LOCK_EX);
  $db->query("SELECT RELEASE_LOCK('office-native-sales-install')");
  echo "OFFICE_SALES_DRAFTS_ACTIVE=1\nOFFICE_SALES_SEND_DISABLED=1\nOFFICE_SALES_PERMISSIONS_UNCHANGED=1\nOFFICE_SALES_EXISTING_FINANCE_SOURCE_PRESERVED=1\n";
} catch(Throwable $e) {
  echo 'OFFICE_SALES_ACTIVATION_FAILED_PHASE='.$phase."\n";
  if($active){try{foreach($journal as $file=>$row){$current=hash_file('sha256',$file);if($current===$row['before'])continue;if(!isset($row['after'])||$current!==$row['after'])throw new RuntimeException('Concurrent change blocks restore');salesReplace($file,$row['after'],file_get_contents($row['backup']),$row['mode']);}echo "OFFICE_SALES_ACTIVE_FILES_RESTORED=1\n";}catch(Throwable $restore){echo "OFFICE_SALES_RESTORE_REQUIRES_REVIEW=1\n";}}
  echo "OFFICE_SALES_NO_TABLES_DROPPED=1\n";
  exit(1);
}
