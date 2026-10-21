<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bin/prepare-inzet-review-copy.php';
require_once dirname(__DIR__).'/src/Inzet/OfficeInzetReviewDecisionRepository.php';
$root=sys_get_temp_dir().'/review-copy-'.bin2hex(random_bytes(8));
mkdir($root,0700); mkdir($root.'/private',0700); mkdir($root.'/public',0700);
function checkCopy(bool $ok, string $why): void { if (!$ok) throw new RuntimeException($why); }
function refuseCopy(callable $call, string $why): void {
  try { $call(); } catch (RuntimeException) { return; }
  throw new RuntimeException($why);
}
try {
  $private=$root.'/private'; $web=$root.'/public';
  $sourcePath=$private.'/source.sqlite'; $oldPath=$private.'/old.sqlite';
  $source=new PDO('sqlite:'.$sourcePath);
  $source->exec('CREATE TABLE worksheets(id INTEGER PRIMARY KEY,sheet_name TEXT)');
  $source->exec('CREATE TABLE imported_rows(id INTEGER PRIMARY KEY,row_number INTEGER,person_label TEXT,project_label TEXT)');
  $source->exec('CREATE VIEW inzet_source_workbench AS SELECT id AS source_row_id,row_number,person_label,project_label FROM imported_rows');
  $source->exec('CREATE TABLE review_issues(id INTEGER PRIMARY KEY,sheet_id INTEGER,source_row_id INTEGER,details TEXT,issue_code TEXT)');
  $source->exec("INSERT INTO worksheets VALUES(1,'week43')");
  $source->exec("INSERT INTO imported_rows VALUES(1,3,'PRIVATE_PERSON','PRIVATE_PROJECT')");
  $source->exec("INSERT INTO review_issues VALUES(7,1,1,'B3','missing_hours'),(8,1,NULL,'','sheet_issue'),(9,1,999,'','unresolved_row')");
  $old=new PDO('sqlite:'.$oldPath);
  $old->exec('CREATE TABLE inzet_review_decisions(issue_id INTEGER PRIMARY KEY,action TEXT NOT NULL,target_id INTEGER,note TEXT NOT NULL,reviewer_id INTEGER NOT NULL,revision INTEGER NOT NULL DEFAULT 1,updated_at TEXT NOT NULL)');
  $old->exec('CREATE TABLE inzet_review_decision_audit(id INTEGER PRIMARY KEY AUTOINCREMENT,issue_id INTEGER NOT NULL,action TEXT NOT NULL,target_id INTEGER,note TEXT NOT NULL,reviewer_id INTEGER NOT NULL,revision INTEGER NOT NULL,recorded_at TEXT NOT NULL)');
  $note="PRIVATE_NOTE: Eén bron, 'quoted' & <tag>\nnext line";
  $now='2026-10-17T10:12:13Z';
  $old->prepare('INSERT INTO inzet_review_decisions VALUES(?,?,?,?,?,?,?)')->execute([7,'propose_person',null,$note,1,1,$now]);
  $old->prepare('INSERT INTO inzet_review_decision_audit VALUES(?,?,?,?,?,?,?,?)')->execute([4,7,'propose_person',null,$note,1,1,$now]);
  $old->exec("UPDATE sqlite_sequence SET seq=50 WHERE name='inzet_review_decision_audit'");
  $config=['OFFICE_BASE_PATH'=>'/office-test','OFFICE_TEST_INZET_SQLITE_PATH'=>$sourcePath,'OFFICE_TEST_INZET_REVIEW_DECISIONS_SQLITE_PATH'=>$oldPath,'OFFICE_TEST_INZET_IMPORT_REVIEWER_ID'=>'PRIVATE_ACTOR'];
  $configPath=$private.'/config.php'; file_put_contents($configPath,'<?php return '.var_export($config,true).';');
  $before=[hash_file('sha256',$sourcePath),hash_file('sha256',$oldPath),hash_file('sha256',$configPath)];
  $report=OfficeInzetReviewPreflight::inspect($config,$private,$web);
  checkCopy($report['source']==='READY' && $report['decisions']==='SCHEMA_CONVERSION_REVIEW_REQUIRED','Observed Hostinger view/integer fixture');
  checkCopy($report['issues_with_null_source_reference']===1 && $report['issues_with_unresolved_source_reference']===1,'Unlinked issue contexts distinguished');
  $result=OfficeInzetReviewCopyPreparation::prepare($config,$private,$web,'prepared.sqlite');
  checkCopy($result['status']==='COPY_CREATED_NOT_ACTIVATED' && $result['decisions_count']===1 && $result['audit_count']===1,'One decision and one audit copied');
  foreach (['PRIVATE_PERSON','PRIVATE_PROJECT','PRIVATE_NOTE','PRIVATE_ACTOR',$private] as $secret) checkCopy(!str_contains(json_encode($result),$secret),'No private data in copy report');
  checkCopy([hash_file('sha256',$sourcePath),hash_file('sha256',$oldPath),hash_file('sha256',$configPath)]===$before,'Input and config hashes retained');
  $copy=new PDO('sqlite:'.$private.'/prepared.sqlite');
  $current=$copy->query('SELECT * FROM inzet_review_decisions')->fetch(PDO::FETCH_ASSOC);
  checkCopy($current['reviewer_id']==='1' && $current['note']===$note && $current['target_id']===null && $current['updated_at']===$now && $current['revision']===1,'Exact historical values retained except explicit reviewer text cast');
  checkCopy((int)$copy->query("SELECT seq FROM sqlite_sequence WHERE name='inzet_review_decision_audit'")->fetchColumn()===50,'Historical audit allocator retained');
  $repo=new \Brebo\Office\Inzet\OfficeInzetReviewDecisionRepository($copy,$source);
  $repo->save(7,'propose_person',null,'New text identity','01',1);
  checkCopy($repo->find(7)['reviewer_id']==='01' && (int)$repo->audit(7)[0]['id']===51,'New text identity and next audit sequence work');
  refuseCopy(fn()=>OfficeInzetReviewCopyPreparation::prepare($config,$private,$web,'prepared.sqlite'),'Existing copy must not be overwritten');
  refuseCopy(fn()=>OfficeInzetReviewCopyPreparation::prepare($config,$private,$web,'../escape.sqlite'),'Directory traversal denied');
  $old->exec('CREATE TABLE unrelated_private_data(id INTEGER PRIMARY KEY)');
  refuseCopy(fn()=>OfficeInzetReviewCopyPreparation::prepare($config,$private,$web,'unknown.sqlite'),'Unknown tables must not be discarded');
  checkCopy(!file_exists($private.'/unknown.sqlite'),'No output on rejected schema');
  $old->exec('DROP TABLE unrelated_private_data');
  $process=proc_open([PHP_BINARY,dirname(__DIR__).'/bin/prepare-inzet-review-copy.php','--private-root='.$private,'--document-root='.$web,'--config='.$configPath,'--output-name=cli.sqlite','--create-copy'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
  checkCopy(is_resource($process),'CLI starts'); fclose($pipes[0]);
  $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
  checkCopy($exit===0 && $stderr==='' && json_decode($stdout,true)['status']==='COPY_CREATED_NOT_ACTIVATED','Real CLI produces a verified, inactive copy');
  checkCopy(hash_file('sha256',$sourcePath)===$before[0] && hash_file('sha256',$configPath)===$before[2],'CLI retains source and config');
  // Deliberately malformed old storage affinity must not silently alter values.
  $old->exec('ALTER TABLE inzet_review_decisions RENAME TO previous');
  $old->exec('CREATE TABLE inzet_review_decisions(issue_id INTEGER PRIMARY KEY,action TEXT,target_id INTEGER,note INTEGER,reviewer_id INTEGER,revision INTEGER,updated_at TEXT)');
  $old->exec('INSERT INTO inzet_review_decisions SELECT issue_id,action,target_id,77,reviewer_id,revision,updated_at FROM previous');
  $old->exec('DROP TABLE previous');
  $old->exec('ALTER TABLE inzet_review_decision_audit RENAME TO audit_previous');
  $old->exec('CREATE TABLE inzet_review_decision_audit(id INTEGER PRIMARY KEY AUTOINCREMENT,issue_id INTEGER,action TEXT,target_id INTEGER,note INTEGER,reviewer_id INTEGER,revision INTEGER,recorded_at TEXT)');
  $old->exec('INSERT INTO inzet_review_decision_audit SELECT id,issue_id,action,target_id,77,reviewer_id,revision,recorded_at FROM audit_previous');
  $old->exec('DROP TABLE audit_previous');
  $badBefore=hash_file('sha256',$oldPath);
  refuseCopy(fn()=>OfficeInzetReviewCopyPreparation::prepare($config,$private,$web,'mismatch.sqlite'),'Value-type mismatch must roll back prepared output');
  checkCopy(!file_exists($private.'/mismatch.sqlite') && !file_exists($private.'/mismatch.sqlite-journal'),'Failed copy cleaned up');
  checkCopy(hash_file('sha256',$oldPath)===$badBefore,'Failed copy preserves original');
  echo "Inzet copy preparation: preserved history, exact comparison, CLI, no activation and failure rollback OK\n";
} finally {
  unset($repo,$copy,$old,$source);
  foreach (glob($root.'/private/*') ?: [] as $file) unlink($file);
  foreach (glob($root.'/public/*') ?: [] as $file) unlink($file);
  rmdir($root.'/private');rmdir($root.'/public');rmdir($root);
}
