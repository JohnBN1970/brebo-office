<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bin/preflight-inzet-review.php';
require_once dirname(__DIR__).'/bin/prepare-inzet-review-copy.php';
require_once dirname(__DIR__).'/bin/OfficeInzetReviewTestDeployment.php';
require_once dirname(__DIR__).'/bin/build-inzet-review-deployment.php';
$root=str_replace('\\','/',sys_get_temp_dir()).'/review-rollout-'.bin2hex(random_bytes(8));mkdir($root,0700);
function rolloutAssert(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function refused(callable $call,string $why):void{try{$call();}catch(RuntimeException){return;}throw new RuntimeException($why);}
function fixture(string $root,array $payload):array{
  $base=$root.'/private';$web=$root.'/public_html';$runtime=$base.'/release/private';
  foreach([$runtime.'/public',$runtime.'/src/Inzet',$runtime.'/src/Intake',$web.'/office-test'] as $path)mkdir($path,0700,true);
  $sourcePath=$base.'/source.sqlite';$oldPath=$base.'/decisions.sqlite';
  $source=new PDO('sqlite:'.$sourcePath);
  $source->exec('CREATE TABLE worksheets(id INTEGER PRIMARY KEY,sheet_name TEXT)');
  $source->exec('CREATE TABLE source_rows(id INTEGER PRIMARY KEY,row_number INTEGER,person_label TEXT,project_label TEXT)');
  $source->exec('CREATE VIEW inzet_source_workbench AS SELECT id AS source_row_id,row_number,person_label,project_label FROM source_rows');
  $source->exec('CREATE TABLE review_issues(id INTEGER PRIMARY KEY,sheet_id INTEGER,source_row_id INTEGER,details TEXT,issue_code TEXT)');
  $source->exec("INSERT INTO worksheets VALUES(1,'week43')");$source->exec("INSERT INTO source_rows VALUES(1,5,'Private person','Private project')");$source->exec("INSERT INTO review_issues VALUES(7,1,1,'B5','missing_hours')");
  $old=new PDO('sqlite:'.$oldPath);
  $old->exec('CREATE TABLE inzet_review_decisions(issue_id INTEGER PRIMARY KEY,action TEXT NOT NULL,target_id INTEGER,note TEXT NOT NULL,reviewer_id INTEGER NOT NULL,revision INTEGER NOT NULL DEFAULT 1,updated_at TEXT NOT NULL)');
  $old->exec('CREATE TABLE inzet_review_decision_audit(id INTEGER PRIMARY KEY AUTOINCREMENT,issue_id INTEGER NOT NULL,action TEXT NOT NULL,target_id INTEGER,note TEXT NOT NULL,reviewer_id INTEGER NOT NULL,revision INTEGER NOT NULL,recorded_at TEXT NOT NULL)');
  $old->exec("INSERT INTO inzet_review_decisions VALUES(7,'propose_person',NULL,'Private note',1,1,'2026-10-17T10:00:00Z')");
  $old->exec("INSERT INTO inzet_review_decision_audit VALUES(1,7,'propose_person',NULL,'Private note',1,1,'2026-10-17T10:00:00Z')");
  $config=['OFFICE_BASE_PATH'=>'/office-test','OFFICE_PUBLIC_HOST'=>'brebobv.nl','OFFICE_PRIVATE_ROOT'=>$runtime,'OFFICE_INTAKE_DB_USER'=>'u213420663_office_test','OFFICE_INTAKE_PDO_DSN'=>'mysql:host=localhost;dbname=u213420663_office_test;charset=utf8mb4','OFFICE_TEST_INZET_IMPORT_REVIEW_ENABLED'=>'enabled','OFFICE_TEST_INZET_IMPORT_REVIEWER_ID'=>'1','OFFICE_TEST_INZET_SQLITE_PATH'=>$sourcePath,'OFFICE_TEST_INZET_REVIEW_DECISIONS_SQLITE_PATH'=>$oldPath,'PRIVATE_SECRET'=>'do not disclose'];
  $configPath=$base.'/config.php';file_put_contents($configPath,"<?php\n// Preserve this comment.\nreturn ".var_export($config,true).";\n");
  $router=$web.'/office-test/index.php';file_put_contents($router,'<?php $root='.var_export($runtime,true).'; $private=realpath($root); $routes=[\'/office-test/inzet/importcontrole\'=>\'office-inzet-import-review.php\'];');
  foreach($payload['shared'] as $path=>$hash)file_put_contents($runtime.'/'.$path,str_replace("\r\n","\n",file_get_contents(dirname(__DIR__).'/'.$path)));
  foreach($payload['files'] as $path=>$entry)if(!str_ends_with($path,'OfficeInzetReviewAccess.php'))file_put_contents($runtime.'/'.$path,"<?php // Old runtime: ".$path."\n");
  file_put_contents($runtime.'/public/onsite-presence.php','Native OnSite must remain unchanged');
  OfficeInzetReviewCopyPreparation::prepare($config,$base,$web,'review-decisions-text-v1.sqlite');
  unset($source,$old);$copy=$base.'/review-decisions-text-v1.sqlite';
  return compact('base','web','runtime','sourcePath','oldPath','copy','configPath','router','config')+['copyHash'=>hash_file('sha256',$copy)];
}
try{
  $payload=OfficeInzetReviewDeploymentPackage::payload(dirname(__DIR__));$f=fixture($root,$payload);$deploy=new OfficeInzetReviewTestDeployment($payload);
  $before=[];foreach([$f['configPath'],$f['router'],$f['oldPath'],$f['copy'],$f['sourcePath'],$f['runtime'].'/public/onsite-presence.php'] as $path)$before[$path]=hash_file('sha256',$path);
  $ctx=$deploy->check($f['base'],$f['web'],$f['configPath'],$f['copyHash']);unset($ctx);
  foreach($before as $path=>$hash)rolloutAssert(hash_file('sha256',$path)===$hash,'Check is read-only');
  rolloutAssert(glob($f['base'].'/review-test-rollout-*')===[],'Check creates no snapshot');
  $configBytes=file_get_contents($f['configPath']);$prod=$f['config'];$prod['OFFICE_BASE_PATH']='/office';file_put_contents($f['configPath'],'<?php return '.var_export($prod,true).';');
  refused(fn()=>$deploy->check($f['base'],$f['web'],$f['configPath'],$f['copyHash']),'Production mount refused');file_put_contents($f['configPath'],$configBytes);
  $routerBytes=file_get_contents($f['router']);file_put_contents($f['router'],str_replace("/office-test/inzet/importcontrole","/office-test/unrelated",$routerBytes));
  refused(fn()=>$deploy->check($f['base'],$f['web'],$f['configPath'],$f['copyHash']),'Missing route refused');file_put_contents($f['router'],$routerBytes);
  refused(fn()=>$deploy->check($f['base'],$f['web'],$f['configPath'],str_repeat('0',64)),'Wrong prepared-copy hash refused');
  $dependency=$f['runtime'].'/src/Intake/OfficeMount.php';$dependencyCode=file_get_contents($dependency);file_put_contents($dependency,$dependencyCode."\n// changed");
  refused(fn()=>$deploy->check($f['base'],$f['web'],$f['configPath'],$f['copyHash']),'Unknown shared adapter refused');file_put_contents($dependency,$dependencyCode);
  $result=$deploy->install($f['base'],$f['web'],$f['configPath'],$f['copyHash']);
  rolloutAssert($result['status']==='OFFICE_TEST_REVIEW_ACTIVATED','Review runtime installed');
  foreach($payload['files'] as $path=>$entry)rolloutAssert(hash_file('sha256',$f['runtime'].'/'.$path)===$entry['sha256'],'Exact payload published');
  $updated=require $f['configPath'];$expected=$f['config'];$expected['OFFICE_TEST_INZET_REVIEW_DECISIONS_SQLITE_PATH']=realpath($f['copy']);rolloutAssert($updated===$expected,'Only one config value changed');
  rolloutAssert(str_contains(file_get_contents($f['configPath']),'Preserve this comment.'),'Config comments retained');
  foreach([$f['router'],$f['oldPath'],$f['copy'],$f['sourcePath'],$f['runtime'].'/public/onsite-presence.php'] as $path)rolloutAssert(hash_file('sha256',$path)===$before[$path],'Router, databases and unrelated native module unchanged');
  $manifest=$f['base'].'/'.$result['snapshot_id'].'/manifest.json';$page=$f['runtime'].'/public/office-inzet-import-review.php';
  $knownPage=file_get_contents($page);file_put_contents($page,$knownPage."\n// Later edit");
  refused(fn()=>$deploy->rollback($f['base'],$f['web'],$manifest),'Rollback refuses later unknown edits');
  rolloutAssert(hash_file('sha256',$f['configPath'])!==$before[$f['configPath']],'Refused rollback makes no partial config changes');file_put_contents($page,$knownPage);
  $copyBytes=file_get_contents($f['copy']);$later=new PDO('sqlite:'.$f['copy']);$later->exec("UPDATE inzet_review_decisions SET note='New review after activation'");unset($later);
  refused(fn()=>$deploy->rollback($f['base'],$f['web'],$manifest),'Rollback must not hide later decisions');
  rolloutAssert(hash_file('sha256',$f['configPath'])!==$before[$f['configPath']],'Refused rollback preserves active database path');
  file_put_contents($f['copy'],$copyBytes); // Restore only this disposable test fixture.
  $rollback=$deploy->rollback($f['base'],$f['web'],$manifest);rolloutAssert($rollback['status']==='OFFICE_TEST_REVIEW_ROLLED_BACK','Explicit rollback');
  foreach($before as $path=>$hash)rolloutAssert(hash_file('sha256',$path)===$hash,'Rollback restores originals without deleting data');
  rolloutAssert(!file_exists($f['runtime'].'/src/Inzet/OfficeInzetReviewAccess.php'),'Rollback removes only its newly installed file');
  // Fault after config publication must restore runtime/config automatically.
  $failureRoot=$root.'/failure';mkdir($failureRoot,0700);$g=fixture($failureRoot,$payload);
  $failureBefore=hash_file('sha256',$g['configPath']);$fail=new OfficeInzetReviewTestDeployment($payload,static function(string $step):void{if($step==='config_published')throw new RuntimeException('INJECTED_FAILURE');});
  refused(fn()=>$fail->install($g['base'],$g['web'],$g['configPath'],$g['copyHash']),'Failure must restore previous state');
  rolloutAssert(hash_file('sha256',$g['configPath'])===$failureBefore,'Automatic rollback restores config');
  rolloutAssert(str_contains(file_get_contents($g['runtime'].'/public/office-inzet-import-review.php'),'Old runtime'),'Automatic rollback restores endpoint');
  // Exercise the actual self-contained package, not only the library.
  $packageCode=OfficeInzetReviewDeploymentPackage::build(dirname(__DIR__));$packageFile=$root.'/installer.php';file_put_contents($packageFile,$packageCode);
  $process=proc_open([PHP_BINARY,$packageFile,'--check','--private-root='.$f['base'],'--document-root='.$f['web'],'--config='.$f['configPath'],'--copy-sha256='.$f['copyHash']],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
  fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
  rolloutAssert($exit===0&&$err===''&&json_decode($out,true)['status']==='READY_FOR_OFFICE_TEST_REVIEW_INSTALL','Standalone package check succeeds: '.$out.$err);
  rolloutAssert(!str_contains($out,'do not disclose')&&!str_contains($out,'Private person'),'Package report excludes secrets/PII');
  $cliRoot=$root.'/cli-install';mkdir($cliRoot,0700);$h=fixture($cliRoot,$payload);$oldConfigHash=hash_file('sha256',$h['configPath']);
  $args=[PHP_BINARY,$packageFile,'--install','--private-root='.$h['base'],'--document-root='.$h['web'],'--config='.$h['configPath'],'--copy-sha256='.$h['copyHash']];
  $process=proc_open($args,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
  $installed=json_decode($out,true);rolloutAssert($exit===0&&$err===''&&$installed['status']==='OFFICE_TEST_REVIEW_ACTIVATED','Built package actual installation: '.$out.$err);
  $args=[PHP_BINARY,$packageFile,'--rollback='.$h['base'].'/'.$installed['snapshot_id'].'/manifest.json','--private-root='.$h['base'],'--document-root='.$h['web']];
  $process=proc_open($args,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
  rolloutAssert($exit===0&&$err===''&&json_decode($out,true)['status']==='OFFICE_TEST_REVIEW_ROLLED_BACK'&&hash_file('sha256',$h['configPath'])===$oldConfigHash,'Built package actual rollback: '.$out.$err);
  echo "Office-test review deployment: read-only check, bounded install, exact config edit, backups, rollback, injected failure and standalone package OK\n";
}finally{
  // Only the verified, randomly named test workspace is removed; never follow links.
  $real=realpath($root);$temp=realpath(sys_get_temp_dir());
  if($real!==false&&$temp!==false&&str_starts_with(str_replace('\\','/',$real),rtrim(str_replace('\\','/',$temp),'/').'/review-rollout-')){
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $entry){if($entry->isDir()&&!$entry->isLink())rmdir($entry->getPathname());else unlink($entry->getPathname());}rmdir($real);
  }
}
