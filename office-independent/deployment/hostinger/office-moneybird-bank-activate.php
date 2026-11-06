<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');

function bankReplace(string $file, string $expected, string $content, int $mode): void {
  if (!is_file($file) || is_link($file) || realpath($file) !== $file || hash_file('sha256', $file) !== $expected) throw new RuntimeException('Target changed');
  $tmp = tempnam(dirname($file), '.bank-');
  try {
    chmod($tmp, $mode);
    if (file_put_contents($tmp, $content, LOCK_EX) !== strlen($content) || hash_file('sha256', $file) !== $expected) throw new RuntimeException('Target changed');
    if (!rename($tmp, $file)) throw new RuntimeException('Atomic replacement failed');
  } finally { if (is_file($tmp)) unlink($tmp); }
}
function bankRestore(string $file, string $before, string $after, string $backup, int $mode): void {
  $current = is_file($file) && !is_link($file) ? hash_file('sha256', $file) : null;
  if ($current === $before) return;
  if ($current !== $after || hash('sha256', $backup) !== $before) throw new RuntimeException('Concurrent change blocks restoration');
  bankReplace($file, $after, $backup, $mode);
}
function bankLoad(string $file): array {
  $level = ob_get_level(); ob_start();
  try { $config = require $file; }
  finally { while (ob_get_level() > $level) ob_end_clean(); }
  if (!is_array($config)) throw new RuntimeException('Invalid configuration');
  return $config;
}
function bankDb(array $config): PDO {
  $dsnMatches=preg_match('/^mysql:host=(?:localhost|127\.0\.0\.1);dbname=u213420663_office_test;charset=utf8mb4$/D', $config['OFFICE_INTAKE_PDO_DSN'] ?? '')===1;
  $userMatches=($config['OFFICE_INTAKE_DB_USER'] ?? '')==='u213420663_office_test';
  if (!defined('OFFICE_BANK_ACTIVATION_LIBRARY_ONLY')) echo 'OFFICE_BANK_TEST_DSN_SCOPE_MATCH='.(int)$dsnMatches."\nOFFICE_BANK_TEST_USER_SCOPE_MATCH=".(int)$userMatches."\n";
  if (!$dsnMatches || !$userMatches) throw new RuntimeException('Wrong test database');
  return new PDO($config['OFFICE_INTAKE_PDO_DSN'], $config['OFFICE_INTAKE_DB_USER'], $config['OFFICE_INTAKE_DB_PASSWORD'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
}
function bankJohn(PDO $db, string $fingerprint = ''): string {
  if ($fingerprint!=='') {
    if (!preg_match('/^[a-f0-9]{64}$/D',$fingerprint)) throw new RuntimeException('Invalid account fingerprint');
    $ids=$db->query("SELECT DISTINCT CAST(a.id AS CHAR) FROM office_operator_accounts a INNER JOIN office_module_permissions r ON r.operator_id=a.id AND r.module='finance' AND r.permission='read' WHERE a.active=1")->fetchAll(PDO::FETCH_COLUMN);
    $ids=array_values(array_filter($ids,static fn($id)=>is_string($id)&&hash_equals($fingerprint,hash('sha256',"office-bank-account\n".$id))));
  } else {
  $columns=$db->query('SHOW COLUMNS FROM office_operator_accounts')->fetchAll(PDO::FETCH_COLUMN);
  $condition=in_array('username',$columns,true) ? '(CAST(a.id AS CHAR)=? OR LOWER(a.username)=?)' : 'CAST(a.id AS CHAR)=?';
  $q=$db->prepare("SELECT DISTINCT CAST(a.id AS CHAR) FROM office_operator_accounts a INNER JOIN office_module_permissions r ON r.operator_id=a.id AND r.module='finance' AND r.permission='read' WHERE a.active=1 AND ".$condition);
  $q->execute(in_array('username',$columns,true) ? ['john.boon','john.boon'] : ['john.boon']);
  $ids=$q->fetchAll(PDO::FETCH_COLUMN);
  }
  echo 'OFFICE_BANK_JOHN_FINANCE_IDENTITY_VERIFIED='.(int)(count($ids)===1)."\n";
  if (count($ids)!==1 || !is_string($ids[0]) || $ids[0]==='' || strlen($ids[0])>128) throw new RuntimeException('Authorized John account not unambiguous');
  return $ids[0];
}
if (defined('OFFICE_BANK_ACTIVATION_LIBRARY_ONLY')) return;
$stage = dirname(__DIR__, 2);
$base = rtrim((string)getenv('HOME'), '/') . '/domains/brebobv.nl/office_calc_test';
$configFile = $base . '/config/config.php';
$journalFile = $stage . '/bank-activation-journal.json';
$paths = ['public/office-finance.php','src/Finance/OfficeFinanceAccess.php','src/Finance/OfficeFinanceBankPanel.php','src/Finance/OfficeFinanceView.php','src/Finance/OfficeMoneybirdBankSnapshot.php'];
$mode = $argv[1] ?? '';
$db = null;
$journal = null;
$phase = 'scope';
$restore = static function(array $j) use ($base, $configFile, $stage, $paths): void {
  if (($j['config'] ?? '') !== $configFile || !str_starts_with($j['root'] ?? '', $base . '/release-') || !str_ends_with($j['root'], '/private')
      || realpath($j['root']) !== $j['root'] || array_keys($j['files'] ?? []) !== $paths || !is_string($j['operator_id']??null) || $j['operator_id']==='' || strlen($j['operator_id'])>128) throw new RuntimeException('Invalid restoration scope');
  foreach ($j['files'] as $path=>$row) {
    $file = $j['root'] . '/' . $path;
    if (!is_file($file) || is_link($file) || !in_array(hash_file('sha256',$file),[$row['before'],$row['after']],true)) throw new RuntimeException('Concurrent file change');
  }
  if (!in_array(hash_file('sha256',$configFile),[$j['config_before'],$j['config_after']],true)) throw new RuntimeException('Concurrent configuration change');
  bankRestore($configFile, $j['config_before'], $j['config_after'], file_get_contents($stage . '/activation-backup/config.php'), 0600);
  foreach ($j['files'] as $path=>$row) bankRestore($j['root'].'/'.$path, $row['before'], $row['after'], file_get_contents($stage.'/activation-backup/'.$path), $row['mode']);
  if (!$j['grant_existed']) {
    $restoreDb = bankDb(bankLoad($stage . '/activation-backup/config.php'));
    $restoreDb->prepare("DELETE FROM office_module_permissions WHERE operator_id=? AND module='finance' AND permission='bank_read'")->execute([$j['operator_id']]);
  }
  echo "OFFICE_BANK_ACTIVATION_RESTORED=1\n";
};
try {
  if ($mode === '--rollback') {
    if (!is_file($journalFile)) { echo "OFFICE_BANK_ROLLBACK_NOT_NEEDED=1\n"; exit; }
    $restore(json_decode(file_get_contents($journalFile), true, 32, JSON_THROW_ON_ERROR));
    exit;
  }
  if (!in_array($mode, ['--check','--apply'],true) || !is_file($configFile) || is_link($configFile) || realpath($configFile) !== $configFile) throw new RuntimeException('Invalid activation scope');
  $config = bankLoad($configFile);
  $root = $config['OFFICE_PRIVATE_ROOT'] ?? '';
  if (($config['OFFICE_BASE_PATH'] ?? '') !== '/office-test' || !str_starts_with($root,$base.'/release-') || !str_ends_with($root,'/private') || realpath($root) !== $root) throw new RuntimeException('Wrong test root');
  foreach (['OFFICE_FINANCE_ENABLED','OFFICE_FINANCE_SCHEMA_VERIFIED','OFFICE_CRM_SESSION_MODE'] as $key) if (($config[$key] ?? '') !== 'enabled') throw new RuntimeException('Test prerequisite missing');
  if (!is_file($stage.'/activation-preview/PLAN_READY') || trim(file_get_contents($stage.'/activation-preview/PLAN_READY')) !== trim(file_get_contents($stage.'/SOURCE_SHA'))) throw new RuntimeException('Preview not accepted');
  $expected = [];
  foreach (file($stage.'/activation-preview/active-before.sha256', FILE_IGNORE_NEW_LINES) as $line) {
    if (!preg_match('/^([a-f0-9]{64})  (.+)$/D',$line,$m)) throw new RuntimeException('Invalid target manifest');
    $expected[$m[2]]=$m[1];
    if (!is_file($m[2]) || is_link($m[2]) || hash_file('sha256',$m[2]) !== $m[1]) throw new RuntimeException('Target changed since preview');
  }
  if (!isset($expected[$configFile])) throw new RuntimeException('Configuration not checked');
  foreach ($paths as $path) if (!isset($expected[$root.'/'.$path]) || !is_file($stage.'/activation-preview/'.$path)) throw new RuntimeException('Missing checked component');
  $phase='database_scope';
  $db = bankDb($config);
  $phase='operator_identity';
  $fingerprint=(string)getenv('OFFICE_BANK_ACCOUNT_FINGERPRINT');
  if (!preg_match('/^[a-f0-9]{64}$/D',$fingerprint)) throw new RuntimeException('Authenticated account proof missing');
  $operatorId=bankJohn($db,$fingerprint);
  $phase='bank_access_scope';
  $q=$db->prepare("SELECT COUNT(*) FROM office_module_permissions b INNER JOIN office_operator_accounts a ON a.id=b.operator_id WHERE b.module='finance' AND b.permission='bank_read' AND a.active=1 AND a.id<>?");
  $q->execute([$operatorId]);
  if ((int)$q->fetchColumn() !== 0) throw new RuntimeException('Other bank access requires review');
  echo "OFFICE_BANK_ACTIVATION_PREFLIGHT_OK=1\n";
  if ($mode === '--check') exit;
  $phase='secret';
  $secret=stream_get_contents(STDIN,4097);
  if (!is_string($secret) || strlen($secret)<24 || strlen($secret)>4096 || str_contains($secret,"\n") || str_contains($secret,"\r")) throw new RuntimeException('Integration secret unavailable');
  if (is_string($config['OFFICE_INTEGRATION_SHARED_SECRET']??null) && $config['OFFICE_INTEGRATION_SHARED_SECRET']!=='' && !hash_equals($config['OFFICE_INTEGRATION_SHARED_SECRET'],$secret)) throw new RuntimeException('Existing integration secret differs');
  if (is_string($config['OFFICE_MONEYBIRD_BANK_API_URL']??null) && $config['OFFICE_MONEYBIRD_BANK_API_URL']!=='' && $config['OFFICE_MONEYBIRD_BANK_API_URL']!=='https://brebo-integration-api.john-boon.workers.dev/v1/accounting/bank-data') throw new RuntimeException('Existing bank endpoint differs');
  $phase='server_route_ready';
  if (!function_exists('curl_init')) throw new RuntimeException('cURL unavailable');
  $routeReady=false;
  for ($attempt=0;$attempt<30;$attempt++) {
    $curl=curl_init('https://brebo-integration-api.john-boon.workers.dev/v1/accounting/bank-data');
    curl_setopt_array($curl,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>5,
      CURLOPT_WRITEFUNCTION=>static fn($handle,string $chunk): int=>strlen($chunk)]);
    curl_exec($curl); $status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE); curl_close($curl);
    if ($status===401) { $routeReady=true; break; }
    if ($status!==404) break;
    sleep(1);
  }
  if (!$routeReady) throw new RuntimeException('Server route not ready');
  echo "OFFICE_BANK_SERVER_ROUTE_READY=1\n";
  $phase='backup';
  $suffix=basename($stage);
  if (!preg_match('/^office-bank-staging-\d+-\d+$/D',$suffix)) throw new RuntimeException('Unsafe activation ID');
  $originalBase=$base.'/config/config-bank-base-'.$suffix.'.php';
  if (file_exists($originalBase) || file_exists($journalFile)) throw new RuntimeException('Activation already exists');
  mkdir($stage.'/activation-backup',0700);
  copy($configFile,$stage.'/activation-backup/config.php'); chmod($stage.'/activation-backup/config.php',0600);
  copy($configFile,$originalBase); chmod($originalBase,0600);
  if (hash_file('sha256',$originalBase)!==$expected[$configFile] || hash_file('sha256',$stage.'/activation-backup/config.php')!==$expected[$configFile]) throw new RuntimeException('Configuration backup changed');
  // Build PHP literals without ever printing a credential.
  $wrapper="<?php\ndeclare(strict_types=1);\n\$config = require __DIR__ . '/".basename($originalBase)."';\n";
  foreach (['OFFICE_MONEYBIRD_BANK_READ_ENABLED'=>'enabled','OFFICE_MONEYBIRD_BANK_API_URL'=>'https://brebo-integration-api.john-boon.workers.dev/v1/accounting/bank-data','OFFICE_INTEGRATION_SHARED_SECRET'=>$secret] as $key=>$value) $wrapper.='$config['.var_export($key,true).'] = '.var_export($value,true).";\n";
  $wrapper.="return \$config;\n";
  $db->beginTransaction();
  $db->prepare("SELECT id FROM office_operator_accounts WHERE id=? AND active=1 FOR UPDATE")->execute([$operatorId]);
  $q=$db->prepare("SELECT permission FROM office_module_permissions WHERE operator_id=? AND module='finance' AND permission='bank_read' FOR UPDATE"); $q->execute([$operatorId]);
  $journal=['operator_id'=>$operatorId,'root'=>$root,'config'=>$configFile,'config_before'=>$expected[$configFile],'config_after'=>hash('sha256',$wrapper),'grant_existed'=>$q->fetchColumn()!==false,'files'=>[]];
  foreach ($paths as $path) {
    $backup=$stage.'/activation-backup/'.$path; if (!is_dir(dirname($backup))) mkdir(dirname($backup),0700,true);
    copy($root.'/'.$path,$backup); chmod($backup,0600);
    if (hash_file('sha256',$backup)!==$expected[$root.'/'.$path]) throw new RuntimeException('Backup source changed');
    $journal['files'][$path]=['before'=>$expected[$root.'/'.$path],'after'=>hash_file('sha256',$stage.'/activation-preview/'.$path),'mode'=>fileperms($root.'/'.$path)&0777];
  }
  $journalJson=json_encode($journal,JSON_THROW_ON_ERROR);
  if (file_put_contents($journalFile,$journalJson,LOCK_EX)!==strlen($journalJson)) throw new RuntimeException('Journal write failed');
  chmod($journalFile,0600);
  $phase='files';
  foreach ($paths as $path) bankReplace($root.'/'.$path,$journal['files'][$path]['before'],file_get_contents($stage.'/activation-preview/'.$path),$journal['files'][$path]['mode']);
  $phase='config';
  bankReplace($configFile,$journal['config_before'],$wrapper,0600);
  $phase='grant';
  if (!$journal['grant_existed']) $db->prepare("INSERT INTO office_module_permissions (operator_id,module,permission) VALUES (?,'finance','bank_read')")->execute([$operatorId]);
  $activeConfig=$config;
  $activeConfig['OFFICE_MONEYBIRD_BANK_READ_ENABLED']='enabled';
  $activeConfig['OFFICE_MONEYBIRD_BANK_API_URL']='https://brebo-integration-api.john-boon.workers.dev/v1/accounting/bank-data';
  $activeConfig['OFFICE_INTEGRATION_SHARED_SECRET']=$secret;
  foreach (['OFFICE_BASE_PATH','OFFICE_FINANCE_SOURCE_KIND','OFFICE_MONEYBIRD_BANK_READ_ENABLED','OFFICE_MONEYBIRD_BANK_API_URL','OFFICE_INTEGRATION_SHARED_SECRET'] as $key) if (is_string($activeConfig[$key]??null)) putenv($key.'='.$activeConfig[$key]);
  require_once $root.'/src/Finance/OfficeFinanceAccess.php';
  require_once $root.'/src/Finance/OfficeMoneybirdBankSnapshot.php';
  require_once $root.'/src/Finance/OfficeMoneybirdBankApiClient.php';
  if (!(new \Brebo\Office\Finance\OfficeFinanceAccess($db,$operatorId))->allowsBankRead()) throw new RuntimeException('Bank access verification failed');
  $phase='bank_get';
  $snapshot=\Brebo\Office\Finance\OfficeMoneybirdBankApiClient::fetch();
  $phase='view';
  require_once $root.'/src/Intake/OfficeMount.php';
  require_once $root.'/src/Finance/OfficeFinanceBankPanel.php';
  require_once $root.'/src/Finance/OfficeFinanceView.php';
  $counts=array_fill_keys(array_keys(\Brebo\Office\Finance\OfficeFinanceView::QUEUES),0);
  $data=['counts'=>$counts,'amounts_inc_vat'=>$counts,'queues'=>array_fill_keys(array_keys($counts),[]),'generated_at'=>time(),'bank_read_allowed'=>true,'bank_snapshot'=>$snapshot];
  $html=\Brebo\Office\Finance\OfficeFinanceView::render($data,'payments');
  if (!str_contains($html,'aria-label="Bankgegevens"')) throw new RuntimeException('Bank panel not rendered');
  $data['bank_read_allowed']=false;
  if (str_contains(\Brebo\Office\Finance\OfficeFinanceView::render($data,'payments'),'aria-label="Bankgegevens"')) throw new RuntimeException('Bank panel authorization failed');
  unset($html,$snapshot,$data,$secret,$wrapper);
  $db->commit();
  foreach (glob(dirname($base).'/public_html/office-test/bank-account-check-*-*.php') ?: [] as $accountCheck) {
    if (preg_match('/^bank-account-check-\d+-\d+\.php$/D',basename($accountCheck)) && is_file($accountCheck) && !is_link($accountCheck) && hash_file('sha256',$accountCheck)===hash_file('sha256',__DIR__.'/office-moneybird-bank-account-check.php')) unlink($accountCheck);
  }
  echo "OFFICE_BANK_SIGNED_GET_OK=1\nOFFICE_BANK_AUTHORIZED_RENDER_OK=1\nOFFICE_BANK_UNAUTHORIZED_RENDER_HIDDEN=1\nOFFICE_BANK_ACTIVATION_OK=1\n";
} catch (Throwable $error) {
  if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
  echo "OFFICE_BANK_ACTIVATION_FAILED=1\nOFFICE_BANK_ACTIVATION_FAILED_PHASE=".$phase."\n";
  if ($journal !== null) { try { $restore($journal); } catch (Throwable $ignored) { echo "OFFICE_BANK_RESTORE_REQUIRES_REVIEW=1\n"; } }
  exit(1);
}
