<?php
declare(strict_types=1);
// Host-side compatibility test. Only connection-local temporary tables and staging fixtures.
$root=realpath($argv[1]??'');$stage=realpath($argv[2]??'');$configFile=$argv[3]??'';
$base=realpath((string)getenv('HOME').'/domains/brebobv.nl/office_calc_test');
if($root===false||$stage===false||$base===false||!str_starts_with($root,$base.'/release-')||!str_starts_with($stage,$base.'/.material-stage-'))exit(2);
// Match the web runtime: PHP CLI otherwise resolves its empty document root to the SSH working directory.
$_SERVER['DOCUMENT_ROOT']=(string)getenv('HOME').'/domains/brebobv.nl/public_html';
$config=require $configFile;if(!is_array($config)||($config['OFFICE_BASE_PATH']??'')!=='/office-test')exit(3);
$exitCode=0;$step='load';$db=null;$fixture=$stage.'/document-contract-'.bin2hex(random_bytes(5));
try{
 $step='temporary_schema';foreach(['OfficePrivateFileStorage','OfficeDocumentRegistry','OfficeDocumentAccessGate']as$class){$relative='/src/Documents/'.$class.'.php';require_once ($class==='OfficePrivateFileStorage'&&($argv[4]??'')==='staged-storage')?$stage.$relative:(is_file($root.$relative)?$root.$relative:$stage.$relative);}
 $step='database';$db=new PDO((string)$config['OFFICE_INTAKE_PDO_DSN'],(string)$config['OFFICE_INTAKE_DB_USER'],(string)$config['OFFICE_INTAKE_DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 if($db->query('SELECT DATABASE()')->fetchColumn()!=='u213420663_office_test')throw new RuntimeException('Wrong database');
 foreach([
 'CREATE TEMPORARY TABLE office_document_originals(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,sha256 CHAR(64) UNIQUE,storage_provider VARCHAR(40),storage_key VARCHAR(255),byte_size BIGINT UNSIGNED,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)',
 'CREATE TEMPORARY TABLE office_documents(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,original_id BIGINT UNSIGNED,title VARCHAR(255),original_filename VARCHAR(255),mime_type VARCHAR(150),lifecycle_status VARCHAR(32) DEFAULT \'active\',created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)',
 'CREATE TEMPORARY TABLE office_document_sources(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,document_id BIGINT UNSIGNED,source_system VARCHAR(80),source_external_id VARCHAR(255),received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)',
 'CREATE TEMPORARY TABLE office_document_contexts(document_id BIGINT UNSIGNED,context_type VARCHAR(32),context_id VARCHAR(100),relation_role VARCHAR(32),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(document_id,context_type,context_id,relation_role))'
 ]as$sql)$db->exec($sql);
 echo 'MATERIAL_STORAGE_ATOMIC_LINK='.(function_exists('link')?'AVAILABLE':'UNAVAILABLE')."\n";
 $step='storage';mkdir($fixture,0700);mkdir($fixture.'/storage',0700);file_put_contents($fixture.'/source.txt','Material module contract fixture');
 $storage=new \Brebo\Office\Documents\OfficePrivateFileStorage($fixture.'/storage');$stored=$storage->put($fixture.'/source.txt');
 if(!isset($stored['sha256'],$stored['storage_provider'],$stored['storage_key'],$stored['size'])||$stored['sha256']!==hash_file('sha256',$fixture.'/source.txt'))throw new RuntimeException('Storage contract');
 if(file_get_contents($storage->authorizedPath($stored['storage_key']))!=='Material module contract fixture')throw new RuntimeException('Stored original integrity');
 $step='register';$registry=new \Brebo\Office\Documents\OfficeDocumentRegistry($db);$id=$registry->register($stored,'Contract fixture','source.txt','text/plain','material_contract_probe');
 $step='attach_context';$registry->attachContext($id,'brebo','material-catalog','supporting');
 $q=$db->prepare('SELECT COUNT(*) FROM office_document_contexts WHERE document_id=? AND context_type=? AND context_id=?');$q->execute([$id,'brebo','material-catalog']);
 if((int)$q->fetchColumn()!==1)throw new RuntimeException('Registry context contract');
 $step='authorization';$gate=new \Brebo\Office\Documents\OfficeDocumentAccessGate(static fn(string $actor,string $type,string $context,string $action):bool=>$actor==='contract-fixture'&&$type==='brebo'&&$context==='material-catalog'&&$action==='read');
 $gate->requireAccess('contract-fixture',[['context_type'=>'brebo','context_id'=>'material-catalog']],'read');
 $denied=false;try{$gate->requireAccess('other',[['context_type'=>'brebo','context_id'=>'material-catalog']],'read');}catch(Throwable){$denied=true;}
 if(!$denied)throw new RuntimeException('Context authorization contract');
 echo "MATERIAL_DEPLOYED_DOCUMENT_CONTRACT=PASS\n";
}catch(Throwable $error){fwrite(STDERR,"MATERIAL_DEPLOYED_DOCUMENT_CONTRACT=FAIL\nMATERIAL_DOCUMENT_PROBE_STAGE=".$step."\nMATERIAL_DOCUMENT_PROBE_ERROR=".get_class($error)."\n");$exitCode=10;}
finally{
 $db=null;
 $remove=static function(string $path)use(&$remove):void{if(!is_dir($path))return;foreach(scandir($path)?:[]as$name){if($name==='.'||$name==='..')continue;$child=$path.'/'.$name;if(is_dir($child)&&!is_link($child))$remove($child);else @unlink($child);}@rmdir($path);};$remove($fixture);
}

exit($exitCode);
