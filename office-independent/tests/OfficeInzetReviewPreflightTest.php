<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bin/preflight-inzet-review.php';
require_once dirname(__DIR__).'/src/Inzet/OfficeInzetReviewDecisionRepository.php';
$root=sys_get_temp_dir().'/review-preflight-'.bin2hex(random_bytes(8));
mkdir($root,0700); mkdir($root.'/private',0700); mkdir($root.'/public',0700);
function verifyPreflight(bool $condition, string $why): void {
  if (!$condition) throw new RuntimeException($why);
}
try {
  $private=$root.'/private'; $web=$root.'/public';
  $sourcePath=$private.'/source.sqlite'; $decisionPath=$private.'/decisions.sqlite';
  $source=new PDO('sqlite:'.$sourcePath);
  $source->exec('CREATE TABLE worksheets(id INTEGER PRIMARY KEY,sheet_name TEXT)');
  $source->exec('CREATE TABLE inzet_source_workbench(source_row_id INTEGER PRIMARY KEY,row_number INTEGER,person_label TEXT,project_label TEXT)');
  $source->exec('CREATE TABLE review_issues(id INTEGER PRIMARY KEY,sheet_id INTEGER,source_row_id INTEGER,details TEXT,issue_code TEXT)');
  $source->exec("INSERT INTO worksheets VALUES(1,'private sheet')");
  $source->exec("INSERT INTO inzet_source_workbench VALUES(1,3,'NEVER_OUTPUT_PERSON','NEVER_OUTPUT_PROJECT')");
  $source->exec("INSERT INTO review_issues VALUES(7,1,1,'B3','missing_hours')");
  $decisions=new PDO('sqlite:'.$decisionPath);
  $repo=new \Brebo\Office\Inzet\OfficeInzetReviewDecisionRepository($decisions,$source);
  $repo->install(); $repo->save(7,'propose_person',null,'NEVER_OUTPUT_NOTE','NEVER_OUTPUT_ACTOR',0);
  $config=['OFFICE_BASE_PATH'=>'/office-test','OFFICE_TEST_INZET_SQLITE_PATH'=>$sourcePath,'OFFICE_TEST_INZET_REVIEW_DECISIONS_SQLITE_PATH'=>$decisionPath,'OFFICE_TEST_INZET_IMPORT_REVIEWER_ID'=>'NEVER_OUTPUT_ACTOR'];
  $run=static fn(array $changes=[])=>OfficeInzetReviewPreflight::inspect(array_replace($config,$changes),$private,$web);
  $before=[hash_file('sha256',$sourcePath),hash_file('sha256',$decisionPath),glob($private.'/*')];
  $result=$run();
  verifyPreflight($result['status']==='READY_FOR_REVIEW','Ready fixture: '.json_encode($result));
  foreach (['NEVER_OUTPUT_PERSON','NEVER_OUTPUT_PROJECT','NEVER_OUTPUT_NOTE','NEVER_OUTPUT_ACTOR',$private] as $secret) {
    verifyPreflight(!str_contains(json_encode($result),$secret),'Private content leaked');
  }
  verifyPreflight($run(['OFFICE_BASE_PATH'=>'/office'])['status']==='BLOCKED','Production mount refused');
  verifyPreflight($run(['OFFICE_TEST_INZET_REVIEW_DECISIONS_SQLITE_PATH'=>$sourcePath])['reason']==='SOURCE_DECISION_ALIAS_REJECTED','Source alias refused');
  verifyPreflight($run(['OFFICE_TEST_INZET_REVIEW_DECISIONS_SQLITE_PATH'=>''])['decisions']==='NOT_CONFIGURED','Absent store reported');
  verifyPreflight($run(['OFFICE_TEST_INZET_REVIEW_DECISIONS_SQLITE_PATH'=>$private.'/absent.sqlite'])['status']==='BLOCKED','Missing file refused');
  verifyPreflight(!file_exists($private.'/absent.sqlite'),'Missing file not created');
  copy($sourcePath,$web.'/public-source.sqlite');
  verifyPreflight($run(['OFFICE_TEST_INZET_SQLITE_PATH'=>$web.'/public-source.sqlite'])['reason']==='PRIVATE_FILE_REQUIRED','Public file refused');
  unlink($web.'/public-source.sqlite');
  verifyPreflight([hash_file('sha256',$sourcePath),hash_file('sha256',$decisionPath),glob($private.'/*')]===$before,'Preflight left hashes and directory untouched');
  file_put_contents($private.'/config.php', '<?php echo "NEVER_OUTPUT_CONFIG"; return '.var_export($config,true).';');
  $beforeCli=[hash_file('sha256',$sourcePath),hash_file('sha256',$decisionPath),glob($private.'/*')];
  $process=proc_open([PHP_BINARY,dirname(__DIR__).'/bin/preflight-inzet-review.php','--private-root='.$private,'--document-root='.$web,'--config='.$private.'/config.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
  verifyPreflight(is_resource($process),'CLI starts');
  fclose($pipes[0]); $stdout=stream_get_contents($pipes[1]); $stderr=stream_get_contents($pipes[2]);
  fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($process);
  verifyPreflight($exit===0 && $stderr==='' && json_decode($stdout,true)['status']==='READY_FOR_REVIEW','Standalone CLI report');
  verifyPreflight(!str_contains($stdout,'NEVER_OUTPUT'),'CLI suppresses trusted config output and personal data');
  verifyPreflight([hash_file('sha256',$sourcePath),hash_file('sha256',$decisionPath),glob($private.'/*')]===$beforeCli,'Standalone CLI did not mutate database files or create sidecars');
  $decisions->exec("UPDATE inzet_review_decision_audit SET reviewer_id='different-actor'");
  verifyPreflight($run()['audit_mismatches']===1 && $run()['status']==='REVIEW_REQUIRED','Audit divergence detected');
  $decisions->exec("UPDATE inzet_review_decision_audit SET reviewer_id='NEVER_OUTPUT_ACTOR'");
  $decisions->exec('INSERT INTO inzet_review_decision_audit(issue_id,action,target_id,note,reviewer_id,revision,recorded_at) SELECT issue_id,action,target_id,note,reviewer_id,revision,recorded_at FROM inzet_review_decision_audit');
  verifyPreflight($run()['duplicate_audit_revisions']===1,'Duplicate audit detected');
  $decisions->exec('DELETE FROM inzet_review_decision_audit WHERE id=2');
  $decisions->exec("CREATE TRIGGER unknown_trigger AFTER INSERT ON inzet_review_decisions BEGIN SELECT 1; END");
  verifyPreflight($run()['unexpected_schema_objects']===1 && $run()['status']==='REVIEW_REQUIRED','Unknown trigger requires review');
  $decisions->exec('DROP TRIGGER unknown_trigger');
  $decisions->exec('ALTER TABLE inzet_review_decisions RENAME TO original');
  $decisions->exec('CREATE TABLE inzet_review_decisions(issue_id INTEGER PRIMARY KEY,action TEXT,target_id INTEGER,note TEXT,reviewer_id INTEGER,revision INTEGER,updated_at TEXT)');
  $decisions->exec('INSERT INTO inzet_review_decisions SELECT * FROM original');
  $decisions->exec('DROP TABLE original');
  $oldHash=hash_file('sha256',$decisionPath);
  verifyPreflight($run()['decisions']==='SCHEMA_CONVERSION_REVIEW_REQUIRED','Old INTEGER schema detected');
  verifyPreflight(hash_file('sha256',$decisionPath)===$oldHash,'Old schema remains unchanged');
  $decisions->exec('PRAGMA journal_mode=WAL');
  verifyPreflight($run()['reason']==='OFFLINE_SNAPSHOT_REQUIRED','WAL requires explicit snapshot');
  unset($repo,$decisions); // Close WAL before fixture cleanup.
  echo "Inzet preflight: private boundaries, read-only evidence, legacy schema and audit diagnostics OK\n";
} finally {
  unset($repo,$decisions,$source);
  foreach (glob($root.'/private/*') ?: [] as $file) unlink($file);
  foreach (glob($root.'/public/*') ?: [] as $file) unlink($file);
  rmdir($root.'/private'); rmdir($root.'/public'); rmdir($root);
}
