<?php
declare(strict_types=1);
// Invoke the real endpoint in isolated processes with disposable SQLite fixtures.
// Office session SQL is portable; no production DB, credentials or server required.
$root = sys_get_temp_dir() . '/brebo-review-' . bin2hex(random_bytes(8));
mkdir($root,0700);
mkdir($root.'/public',0700);
mkdir($root.'/sessions',0700);
function checkRoute(bool $ok, string $why): void {
  if (!$ok) throw new RuntimeException($why);
}
try {
  $office = new PDO('sqlite:'.$root.'/office.sqlite');
  $office->exec('CREATE TABLE office_operator_accounts(id TEXT PRIMARY KEY, active INTEGER, can_edit_crm INTEGER)');
  $office->exec('CREATE TABLE office_operator_sessions(token_hash TEXT PRIMARY KEY, operator_id TEXT, expires_at TEXT, revoked_at TEXT)');
  $insert = $office->prepare('INSERT INTO office_operator_accounts VALUES(?,1,0)');
  $session = $office->prepare('INSERT INTO office_operator_sessions VALUES(?,?,?,NULL)');
  foreach (['operator-a','operator-b','1','1-other'] as $actor) {
    $insert->execute([$actor]);
    $session->execute([hash('sha256',str_repeat('x',40).$actor),$actor,gmdate('Y-m-d H:i:s',time()+3600)]);
  }
  $source = new PDO('sqlite:'.$root.'/source.sqlite');
  $source->exec('CREATE TABLE worksheets(id INTEGER PRIMARY KEY,sheet_name TEXT)');
  $source->exec('CREATE TABLE inzet_source_workbench(source_row_id INTEGER PRIMARY KEY,row_number INTEGER,person_label TEXT,project_label TEXT)');
  $source->exec('CREATE TABLE review_issues(id INTEGER PRIMARY KEY,sheet_id INTEGER,source_row_id INTEGER,details TEXT,issue_code TEXT)');
  $source->exec("INSERT INTO worksheets VALUES(1,'week 43')");
  $source->exec("INSERT INTO inzet_source_workbench VALUES(1,5,'Test person','Test project')");
  $source->exec("INSERT INTO review_issues VALUES(7,1,1,'B5','missing_hours')");
  require_once dirname(__DIR__).'/src/Inzet/OfficeInzetReviewDecisionRepository.php';
  $decisions = new PDO('sqlite:'.$root.'/decisions.sqlite');
  $repo = new \Brebo\Office\Inzet\OfficeInzetReviewDecisionRepository($decisions,$source);
  $repo->install();
  $sourceHash = hash_file('sha256',$root.'/source.sqlite');
  $endpoint = dirname(__DIR__).'/public/office-inzet-import-review.php';
  $access = dirname(__DIR__).'/src/Inzet/OfficeInzetReviewAccess.php';
  $wrapper = <<<'PHP'
<?php
declare(strict_types=1);
$s = json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
foreach ($s['env'] as $k=>$v) putenv($k.'='.$v);
$_SERVER = $s['server']; $_POST = $s['post']; $_GET = [];
$_COOKIE = ['office_test_session'=>$s['token']];
require $s['access'];
ini_set('session.save_path',$s['sessions']);
session_name('BREBO_INZET_REVIEW_TEST'); session_id('test'.bin2hex(random_bytes(16)));
session_start();
$_SESSION = ['inzet_review_binding'=>\Brebo\Office\Inzet\OfficeInzetReviewAccess::sessionBinding($s['boundActor'],hash('sha256',$s['boundToken'])),'inzet_csrf'=>'known-test-csrf'];
$sid = session_id(); session_write_close(); session_id($sid);
register_shutdown_function(static function(): void { fwrite(STDERR,'STATUS='.(http_response_code() ?: 200)); });
require $s['endpoint'];
PHP;
  file_put_contents($root.'/wrapper.php',$wrapper);
  $run = static function(array $changes = []) use ($root,$endpoint,$access): array {
    $s = [
      'endpoint'=>$endpoint,'access'=>$access,'sessions'=>$root.'/sessions',
      'token'=>str_repeat('x',40).'operator-a','boundToken'=>str_repeat('x',40).'operator-a','boundActor'=>'operator-a',
      'env'=>['OFFICE_BASE_PATH'=>'/office-test','OFFICE_PUBLIC_HOST'=>'office-test.example','OFFICE_TEST_INZET_IMPORT_REVIEW_ENABLED'=>'enabled','OFFICE_TEST_INZET_IMPORT_REVIEWER_ID'=>'operator-a','OFFICE_INTAKE_PDO_DSN'=>'sqlite:'.$root.'/office.sqlite','OFFICE_INTAKE_DB_USER'=>'','OFFICE_INTAKE_DB_PASSWORD'=>'','OFFICE_TEST_INZET_SQLITE_PATH'=>$root.'/source.sqlite','OFFICE_TEST_INZET_REVIEW_DECISIONS_SQLITE_PATH'=>$root.'/decisions.sqlite'],
      'server'=>['REQUEST_METHOD'=>'GET','HTTP_HOST'=>'office-test.example','HTTPS'=>'on','DOCUMENT_ROOT'=>$root.'/public','CONTENT_TYPE'=>'application/x-www-form-urlencoded','CONTENT_LENGTH'=>'100','HTTP_ORIGIN'=>'https://office-test.example'],
      'post'=>['csrf'=>'known-test-csrf','issue_id'=>'7','revision'=>'0','action'=>'propose_person','note'=>'Verify source identity'],
    ];
    $s = array_replace_recursive($s,$changes);
    file_put_contents($root.'/scenario.json',json_encode($s,JSON_THROW_ON_ERROR));
    $p = proc_open([PHP_BINARY,$root.'/wrapper.php',$root.'/scenario.json'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($p)) throw new RuntimeException('Could not start PHP');
    fclose($pipes[0]); $body=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($p);
    if (!preg_match('/STATUS=(\d+)/',$error,$m) || $exit!==0) throw new RuntimeException('Endpoint process failed: '.$error.$body);
    return [(int)$m[1],$body];
  };
  [$status,$body]=$run();
  checkRoute($status===200 && str_contains($body,'Test person') && str_contains($body,'name="csrf"'), 'Authorized source and review form rendered: '.$status.' '.$body);
  checkRoute($run(['token'=>'bad-token'])[0]===302,'Anonymous login redirect');
  $office->exec("UPDATE office_operator_accounts SET active=0 WHERE id='operator-a'");
  checkRoute($run()[0]===302,'Inactive Office account denied');
  $office->exec("UPDATE office_operator_accounts SET active=1 WHERE id='operator-a'");
  $office->exec("UPDATE office_operator_sessions SET revoked_at='2026-01-01' WHERE operator_id='operator-a'");
  checkRoute($run()[0]===302,'Revoked Office session denied');
  $office->exec("UPDATE office_operator_sessions SET revoked_at=NULL WHERE operator_id='operator-a'");
  checkRoute($run(['token'=>str_repeat('x',40).'operator-b'])[0]===403,'Other reviewer denied');
  checkRoute($run(['env'=>['OFFICE_TEST_INZET_IMPORT_REVIEWER_ID'=>'1'],'token'=>str_repeat('x',40).'1-other'])[0]===403,'Numeric-prefix impersonation denied');
  checkRoute($run(['env'=>['OFFICE_BASE_PATH'=>'/office']])[0]===404,'Production mount denied');
  checkRoute($run(['server'=>['REQUEST_METHOD'=>'PUT']])[0]===405,'Invalid method denied');
  $post=['server'=>['REQUEST_METHOD'=>'POST']];
  checkRoute($run(array_replace_recursive($post,['post'=>['csrf'=>'wrong']]))[0]===403,'Bad CSRF denied');
  checkRoute($run(array_replace_recursive($post,['boundToken'=>'old-login-token']))[0]===403,'Old login form denied');
  checkRoute($run(array_replace_recursive($post,['server'=>['HTTP_ORIGIN'=>'https://evil.example']]))[0]===403,'Cross-origin denied');
  checkRoute($run(array_replace_recursive($post,['server'=>['CONTENT_LENGTH'=>'5000']]))[0]===400,'Large payload denied');
  checkRoute($run(array_replace_recursive($post,['post'=>['issue_id'=>'999']]))[0]===422,'Unknown source issue denied');
  checkRoute($run(array_replace_recursive($post,['post'=>['action'=>'link_person']]))[0]===422,'Unverified identity link denied');
  checkRoute(count($repo->allDecisions())===0,'Denied requests have no writes');
  checkRoute($run($post)[0]===303,'Successful review redirects');
  checkRoute($repo->find(7)['reviewer_id']==='operator-a','Saved reviewer exact');
  checkRoute($run($post)[0]===409,'Duplicate stale form rejected');
  checkRoute(count($repo->audit(7))===1,'One decision and one audit');
  checkRoute(hash_file('sha256',$root.'/source.sqlite')===$sourceHash,'Source file untouched');
  echo "Inzet real endpoint: authorization, login binding, CSRF, source immutability and replay OK\n";
} finally {
  // Remove only disposable files created by this fixture.
  unset($repo,$decisions,$source,$office,$insert,$session);
  foreach (glob($root.'/sessions/*') ?: [] as $file) unlink($file);
  rmdir($root.'/sessions'); rmdir($root.'/public');
  foreach (glob($root.'/*') ?: [] as $file) unlink($file);
  rmdir($root);
}
