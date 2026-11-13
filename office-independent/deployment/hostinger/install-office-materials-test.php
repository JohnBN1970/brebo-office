<?php
declare(strict_types=1);

// Controlled Office-test overlay. Catalogues, invoices and calculation data are never copied.
$stage=realpath($argv[1]??'');
$home=(string)getenv('HOME');$base=realpath($home.'/domains/brebobv.nl/office_calc_test');
if($stage===false||$base===false||!str_starts_with($stage,$base.'/.material-stage-'))exit(2);
$configPath=$base.'/config/config.php';$configHash=hash_file('sha256',$configPath);$config=require $configPath;
if(!is_array($config)||($config['OFFICE_BASE_PATH']??'')!=='/office-test')exit(3);
$root=realpath((string)($config['OFFICE_PRIVATE_ROOT']??''));
if($root===false||!str_starts_with($root,$base.'/release-')||!str_ends_with($root,'/private'))exit(4);
$router=$home.'/domains/brebobv.nl/public_html/office-test/index.php';
if(!is_file($router))exit(5);
$lock=fopen($base.'/.material-install.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))exit(6);
$stamp=gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));$backup=$base.'/.material-backups/'.$stamp;
if(!is_dir(dirname($backup)))mkdir(dirname($backup),0700);mkdir($backup,0700);
$changed=[];$addedGrants=[];$db=null;$configWrittenHash=null;
$phpFingerprint=static function(string $path):string{
 $source=str_replace("\r\n","\n",(string)file_get_contents($path));$parts=[];
 foreach(token_get_all($source)as$token){
  if(is_array($token)){if(in_array($token[0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true))continue;$parts[]=[$token[0],$token[1]];}
  else $parts[]=$token;
 }
 return hash('sha256',serialize($parts));
};

$write=static function(string $path,string $content)use(&$changed,$backup):void{
 if(is_link($path))throw new RuntimeException('symlink_target_denied');
 if(!array_key_exists($path,$changed)){
  $old=is_file($path)?file_get_contents($path):null;$oldPath=$backup.'/'.hash('sha256',$path).'.backup';
  if(is_string($old)){file_put_contents($oldPath,$old,LOCK_EX);chmod($oldPath,0600);}
  $changed[$path]=['backup'=>$old===null?null:$oldPath,'written_hash'=>null,'mode'=>is_file($path)?(fileperms($path)&0777):0600];
 }
 if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);
 $tmp=tempnam(dirname($path),'.material-');if($tmp===false)throw new RuntimeException('temporary_target_failed');
 try{if(file_put_contents($tmp,$content,LOCK_EX)!==strlen($content))throw new RuntimeException('write_failed');chmod($tmp,$changed[$path]['mode']);if(!rename($tmp,$path))throw new RuntimeException('atomic_swap_failed');$changed[$path]['written_hash']=hash('sha256',$content);}finally{if(is_file($tmp))unlink($tmp);}
};
$lint=static function(string $file):void{
 $pipes=[];$p=proc_open([PHP_BINARY,'-l',$file],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
 if(!is_resource($p))throw new RuntimeException('lint_start_failed');fclose($pipes[0]);stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException('lint_failed');
};
try{
 $db=new PDO((string)$config['OFFICE_INTAKE_PDO_DSN'],(string)$config['OFFICE_INTAKE_DB_USER'],(string)$config['OFFICE_INTAKE_DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 if($db->query('SELECT DATABASE()')->fetchColumn()!=='u213420663_office_test')throw new RuntimeException('isolated_database_required');
 // Exact, previously confirmed Office account -> John Boon binding; never infer rights from CRM editing.
 $q=$db->prepare('SELECT COUNT(*) FROM office_operator_accounts WHERE id=? AND username=? AND active=1');$q->execute(['1','tester_office']);
 if((int)$q->fetchColumn()!==1)throw new RuntimeException('owner_identity_not_verified');
 $sourceRoot=$home.'/domains/brebobv.nl/public_html/sboffice';$settingsFile=$sourceRoot.'/web/sites/default/settings.php';
 if(!is_file($settingsFile)||!is_file($sourceRoot.'/vendor/autoload.php'))throw new RuntimeException('canonical_source_unavailable');
 require_once $sourceRoot.'/vendor/autoload.php';
 $databases=[];$settings=[];$app_root=$sourceRoot.'/web';$site_path='sites/default';if(!defined('DRUPAL_ROOT'))define('DRUPAL_ROOT',$app_root);
 ob_start();try{require $settingsFile;}finally{ob_end_clean();}
 $options=$databases['default']['default']??null;
 if(!is_array($options)||($options['driver']??'')!=='mysql')throw new RuntimeException('canonical_database_not_resolved');
 $prefix=$options['prefix']??'';if(is_array($prefix))$prefix=$prefix['default']??null;
 if(!is_string($prefix)||!preg_match('/^[A-Za-z0-9_]{0,32}$/D',$prefix))throw new RuntimeException('canonical_prefix_not_supported');
 $host=(string)($options['host']??'localhost');$name=(string)($options['database']??'');$port=(int)($options['port']??3306);
 if(!preg_match('/^[A-Za-z0-9_.-]+$/D',$host)||!preg_match('/^[A-Za-z0-9_]+$/D',$name)||$port<1||$port>65535)throw new RuntimeException('invalid_canonical_source');
 $sourceDsn='mysql:host='.$host.';port='.$port.';dbname='.$name.';charset=utf8mb4';
 $catalogue=new PDO($sourceDsn,(string)($options['username']??''),(string)($options['password']??''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 foreach(['brebo_supplier','brebo_article','brebo_supplier_article','brebo_article_price','brebo_catalog_import']as$table)$catalogue->query('SELECT 1 FROM `'.$prefix.$table.'` LIMIT 0');
 $files=['public/office-materials.php'];foreach(glob($stage.'/src/Materials/*.php')?:[]as$file)$files[]='src/Materials/'.basename($file);
 foreach(['OfficeDocumentRegistry','OfficePrivateFileStorage','OfficeDocumentAccessGate']as$class){
  $file='src/Documents/'.$class.'.php';
  if(is_file($root.'/'.$file)&&!hash_equals($phpFingerprint($root.'/'.$file),$phpFingerprint($stage.'/'.$file))){echo 'MATERIAL_DEPENDENCY_MISMATCH='.$class."\n";throw new RuntimeException('document_dependency_differs');}
  if(!is_file($root.'/'.$file))$files[]=$file;
 }
 // Validate the already deployed worksheet adapter rather than overwriting OnSite code.
 $worksheet='src/Inzet/OnSiteXlsxCellReader.php';
 if(is_file($root.'/'.$worksheet)){
  if(!copy($root.'/'.$worksheet,$stage.'/'.$worksheet))throw new RuntimeException('worksheet_staging_failed');
 }else $files[]=$worksheet;
 foreach($files as$file){if(!is_file($stage.'/'.$file))throw new RuntimeException('staging_file_missing');$lint($stage.'/'.$file);}
 $testPipes=[];$test=proc_open([PHP_BINARY,$stage.'/tests/MaterialImportSourceReaderTest.php'],[['pipe','r'],['pipe','w'],['pipe','w']],$testPipes);
 if(!is_resource($test))throw new RuntimeException('worksheet_test_start_failed');fclose($testPipes[0]);$testOutput=stream_get_contents($testPipes[1]);stream_get_contents($testPipes[2]);fclose($testPipes[1]);fclose($testPipes[2]);
 if(proc_close($test)!==0||!str_contains($testOutput,'MATERIAL_SOURCE_READER=PASS'))throw new RuntimeException('deployed_worksheet_adapter_test_failed');

 $routerContent=file_get_contents($router);$newRouter=$routerContent;
 if(!str_contains($routerContent,"'/office-test/materialen'")){
  $newRouter=preg_replace('/(\$routes\s*=\s*\[)/','$1'."\n  '/office-test/materialen' => 'office-materials.php',",$routerContent,1,$count);
  if($count!==1||!is_string($newRouter))throw new RuntimeException('router_mapping_not_found');
 }elseif(!preg_match("~'/office-test/materialen'\\s*=>\\s*'office-materials\\.php'~",$routerContent))throw new RuntimeException('route_already_owned');
 // No existing article tables are created or moved: only their review ledger is installed.
 $sql=file_get_contents($stage.'/sql/material-import-review-ledger.sql');if(!is_string($sql))throw new RuntimeException('ledger_schema_missing');
 $catalogue->exec(str_replace('brebo_material_import_reviews',$prefix.'brebo_material_import_reviews',$sql));
 require_once $stage.'/src/Documents/OfficeDocumentRegistry.php';
 foreach(\Brebo\Office\Documents\OfficeDocumentRegistry::schema()as$statement)$db->exec($statement);
 $storage=$config['OFFICE_DOCUMENT_STORAGE_ROOT']??($base.'/document-originals');
 if(!is_string($storage)||!str_starts_with($storage,$base.'/')||str_contains($storage,'..')||is_link($storage))throw new RuntimeException('private_storage_required');
 if(!is_dir($storage)&&!mkdir($storage,0700,true))throw new RuntimeException('storage_creation_failed');chmod($storage,0700);
 foreach($files as$file)$write($root.'/'.$file,(string)file_get_contents($stage.'/'.$file));
 if($newRouter!==$routerContent){$write($router,$newRouter);$lint($router);}
 // Add only a bounded Finance link; preserve the active composed Finance implementation.
 $finance=$root.'/public/office-finance.php';$view=$root.'/src/Finance/OfficeFinanceView.php';$financeLinked=false;
 if(is_file($finance)&&is_file($view)){
  $body=file_get_contents($finance);$viewBody=file_get_contents($view);
  $needle='  $html = \\Brebo\\Office\\Finance\\OfficeFinanceView::render($data, $view, $queue, $page, $search);';
  $viewNeedle='      $html .= \'<p class="muted">Office-test · Concept · Niet geboekt of betaalbaar.</p>\';';
  if(str_contains($body,"can_import_materials")&&str_contains($viewBody,"can_import_materials"))$financeLinked=true;
  elseif(substr_count($body,$needle)===1&&substr_count($viewBody,$viewNeedle)===1){
   $hook=<<<'PHP'
  if($view==='dossier' && getenv('OFFICE_MATERIALS_ENABLED')==='enabled'){
    require_once dirname(__DIR__).'/src/Materials/OfficeMaterialAccess.php';
    $materialAccess=new \Brebo\Office\Materials\OfficeMaterialAccess($db);
    $data['can_import_materials']=$materialAccess->allows($operator->operatorId,'read')&&$materialAccess->allows($operator->operatorId,'import');
  }
PHP;
   $link=<<<'PHP'
      if(!empty($data['can_import_materials']))$html.='<p><a href="'.$e(OfficeMount::route('materialen',['invoice_id'=>(int)$invoice['id']])).'">Materiaalregels naar de vaste prijslijst</a></p>';
PHP;
   $write($finance,str_replace($needle,$hook."\n".$needle,$body));$write($view,str_replace($viewNeedle,$viewNeedle."\n".$link,$viewBody));$lint($finance);$lint($view);$financeLinked=true;
  }
 }
 if(!hash_equals((string)$configHash,(string)hash_file('sha256',$configPath)))throw new RuntimeException('configuration_changed_during_install');
 $delta=['OFFICE_MATERIALS_ENABLED'=>'enabled','OFFICE_MATERIALS_CANONICAL_SCHEMA_VERIFIED'=>'enabled',
  'OFFICE_MATERIALS_CANONICAL_PDO_DSN'=>$sourceDsn,'OFFICE_MATERIALS_CANONICAL_DB_USER'=>(string)($options['username']??''),
  'OFFICE_MATERIALS_CANONICAL_DB_PASSWORD'=>(string)($options['password']??''),'OFFICE_MATERIALS_CANONICAL_TABLE_PREFIX'=>$prefix,'OFFICE_DOCUMENT_STORAGE_ROOT'=>$storage];
 foreach(['BREBO_DOCUMENT_EXTRACTION_ENDPOINT'=>'brebo_document_extraction_endpoint','DOCUMENT_EXTRACTION_TOKEN'=>'brebo_document_extraction_token']as$environment=>$setting){
  if(empty($config[$environment])&&is_string($settings[$setting]??null)&&$settings[$setting]!=='')$delta[$environment]=$settings[$setting];
 }
 $previous='material-previous-'.$stamp.'.php';$provider='material-provider-'.$stamp.'.php';
 $write(dirname($configPath).'/'.$previous,(string)file_get_contents($configPath));$write(dirname($configPath).'/'.$provider,"<?php\nreturn ".var_export($delta,true).";\n");
 $wrapper="<?php\ndeclare(strict_types=1);\n\$config=require __DIR__.".var_export('/'.$previous,true).";\n\$materials=require __DIR__.".var_export('/'.$provider,true).";\nreturn array_replace(\$config,\$materials);\n";
 // Module grants are explicit and restricted to the verified existing owner account.
 $db->beginTransaction();
 foreach(['read','import']as$permission){$q=$db->prepare('INSERT IGNORE INTO office_module_permissions(operator_id,module,permission)VALUES(?,?,?)');$q->execute(['1','materials',$permission]);if($q->rowCount()===1)$addedGrants[]=$permission;}
 $write($configPath,$wrapper);$configWrittenHash=hash('sha256',$wrapper);$lint($configPath);$db->commit();
 $publicHost=(string)($config['OFFICE_PUBLIC_HOST']??'');
 if(!preg_match('/^[a-zA-Z0-9.-]+$/D',$publicHost))throw new RuntimeException('invalid_public_host');
 $httpReady=false;
 for($attempt=0;$attempt<3;$attempt++){
  $http=stream_context_create(['http'=>['method'=>'GET','timeout'=>10,'follow_location'=>0,'ignore_errors'=>true,'header'=>"User-Agent: BREBO-Office-Healthcheck\r\n"]]);
  @file_get_contents('https://'.$publicHost.'/office-test/materialen',false,$http);
  $status=0;foreach($http_response_header??[]as$line)if(preg_match('~^HTTP/\S+ ([0-9]{3})~',$line,$m))$status=(int)$m[1];
  if(in_array($status,[302,401],true)){$httpReady=true;break;}
  usleep(1000000);
 }
 if(!$httpReady)throw new RuntimeException('anonymous_route_check_failed');
 $manifest=['installed_at'=>gmdate('c'),'source'=>'verified_existing_sales_catalogue','files'=>array_keys($changed),'granted_operator'=>'1','added_permissions'=>$addedGrants,'finance_link'=>$financeLinked];
 file_put_contents($backup.'/manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);chmod($backup.'/manifest.json',0600);
 echo "MATERIAL_INSTALL=OFFICE_TEST_ONLY\nMATERIAL_CATALOG=EXISTING_SOURCE_CONNECTED\nMATERIAL_ORIGINAL_REGISTRY=READY\nMATERIAL_OWNER_BINDING=VERIFIED\nMATERIAL_OWNER_READ_IMPORT=READY\n";
 echo 'MATERIAL_FINANCE_LINK='.($financeLinked?'READY':'NOT_MOUNTED')."\n";
 echo 'MATERIAL_EXTRACTION='.(!empty($config['BREBO_DOCUMENT_EXTRACTION_ENDPOINT']??$delta['BREBO_DOCUMENT_EXTRACTION_ENDPOINT']??'')&&!empty($config['DOCUMENT_EXTRACTION_TOKEN']??$delta['DOCUMENT_EXTRACTION_TOKEN']??'')?'CONFIGURED':'NOT_CONFIGURED')."\n";
 echo "MATERIAL_EXISTING_ARTICLES_PRICES_INVOICES=NOT_MODIFIED\nMATERIAL_CONFIG=ACTIVATED\nMATERIAL_ANONYMOUS_ACCESS=AUTHENTICATION_REQUIRED\n";
}catch(Throwable $error){
 if($db instanceof PDO&&$db->inTransaction())$db->rollBack();
 foreach(array_reverse($changed,true)as$path=>$state){
  if(!is_file($path)||!hash_equals((string)$state['written_hash'],(string)hash_file('sha256',$path)))continue;
  if($state['backup']===null)@unlink($path);else @copy($state['backup'],$path);
 }
 if($db instanceof PDO&&$addedGrants!==[]){$q=$db->prepare('DELETE FROM office_module_permissions WHERE operator_id=? AND module=? AND permission=?');foreach($addedGrants as$p)$q->execute(['1','materials',$p]);}
 $reason=preg_match('/^[a-z_]+$/D',$error->getMessage())?$error->getMessage():'runtime_failure';
 fwrite(STDERR,'MATERIAL_INSTALL=BLOCKED_'.$reason."\n");exit(10);
}finally{flock($lock,LOCK_UN);fclose($lock);}
