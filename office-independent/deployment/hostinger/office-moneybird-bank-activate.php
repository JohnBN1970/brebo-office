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
  if (!preg_match('/^mysql:host=(?:localhost|127\.0\.0\.1);dbname=u213420663_office_test;charset=utf8mb4$/D', $config['OFFICE_INTAKE_PDO_DSN'] ?? '')
      || ($config['OFFICE_INTAKE_DB_USER'] ?? '') !== 'u213420663_office_test') throw new RuntimeException('Wrong test database');
  return new PDO($config['OFFICE_INTAKE_PDO_DSN'], $config['OFFICE_INTAKE_DB_USER'], $config['OFFICE_INTAKE_DB_PASSWORD'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
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
      || realpath($j['root']) !== $j['root'] || array_keys($j['files'] ?? []) !== $paths) throw new RuntimeException('Invalid restoration scope');
  foreach ($j['files'] as $path=>$row) {
    $file = $j['root'] . '/' . $path;
    if (!is_file($file) || is_link($file) || !in_array(hash_file('sha256',$file),[$row['before'],$row['after']],true)) throw new RuntimeException('Concurrent file change');
  }
  if (!in_array(hash_file('sha256',$configFile),[$j['config_before'],$j['config_after']],true)) throw new RuntimeException('Concurrent configuration change');
  bankRestore($configFile, $j['config_before'], $j['config_after'], file_get_contents($stage . '/activation-backup/config.php'), 0600);
  foreach ($j['files'] as $path=>$row) bankRestore($j['root'].'/'.$path, $row['before'], $row['after'], file_get_contents($stage.'/activation-backup/'.$path), $row['mode']);
  if (!$j['grant_existed']) {
    $restoreDb = bankDb(bankLoad($stage . '/activation-backup/config.php'));
    $restoreDb->prepare("DELETE FROM office_module_permissions WHERE operator_id=? AND module='finance' AND permission='bank_read'")->execute(['john.boon']);
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
  $phase='operator';
  $db = bankDb($config);
  $q=$db->prepare("SELECT a.id FROM office_operator_accounts a INNER JOIN office_module_permissions r ON r.operator_id=a.id AND r.module='finance' AND r.permission='read' WHERE a.id=? AND a.active=1");
  $q->execute(['john.boon']);
  if ($q->fetchAll(PDO::FETCH_COLUMN) !== ['john.boon']) throw new RuntimeException('Authorized John account not unambiguous');
  $q=$db->prepare("SELECT COUNT(*) FROM office_module_permissions b INNER JOIN office_operator_accounts a ON a.id=b.operator_id WHERE b.module='finance' AND b.permission='bank_read' AND a.active=1 AND a.id<>?");
  $q->execute(['john.boon']);
  if ((int)$q->fetchColumn() !== 0) throw new RuntimeException('Other bank access requires review');
  echo "OFFICE_BANK_ACTIVATION_PREFLIGHT_OK=1\n";
  if ($mode === '--check') exit;
  $phase='secret';
  $secret=stream_get_contents(STDIN,4097);
  if (!is_string($secret) || strlen($secret)<24 || strlen($secret)>4096 || str_contains($secret,"\n") || str_contains($secret,"\r")) throw new RuntimeException('Integration secret unavailable');
  if (is_string($config['OFFICE_INTEGRATION_SHARED_SECRET']??null) && $config['OFFICE_INTEGRATION_SHARED_SECRET']!=='' && !hash_equals($config['OFFICE_INTEGRATION_SHARED_SECRET'],$secret)) throw new RuntimeException('Existing integration secret differs');
  if (is_string($config['OFFICE_MONEYBIRD_BANK_API_URL']??null) && $config['OFFICE_MONEYBIRD_BANK_API_URL']!=='' && $config['OFFICE_MONEYBIRD_BANK_API_URL']!=='https://brebo-integration-api.john-boon.workers.dev/v1/accounting/bank-data') throw new RuntimeException('Existing bank endpoint differs');
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
  $db->prepare("SELECT id FROM office_operator_accounts WHERE id=? AND active=1 FOR UPDATE")->execute(['john.boon']);
  $q=$db->prepare("SELECT permission FROM office_module_permissions WHERE operator_id=? AND module='finance' AND permission='bank_read' FOR UPDATE"); $q->execute(['john.boon']);
  $journal=['root'=>$root,'config'=>$configFile,'config_before'=>$expected[$configFile],'config_after'=>hash('sha256',$wrapper),'grant_existed'=>$q->fetchColumn()!==false,'files'=>[]];
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
  if (!$journal['grant_existed']) $db->prepare("INSERT INTO office_module_permissions (operator_id,module,permission) VALUES (?,'finance','bank_read')")->execute(['john.boon']);
  $activeConfig=$config;
  $activeConfig['OFFICE_MONEYBIRD_BANK_READ_ENABLED']='enabled';
  $activeConfig['OFFICE_MONEYBIRD_BANK_API_URL']='https://brebo-integration-api.john-boon.workers.dev/v1/accounting/bank-data';
  $activeConfig['OFFICE_INTEGRATION_SHARED_SECRET']=$secret;
  foreach (['OFFICE_BASE_PATH','OFFICE_FINANCE_SOURCE_KIND','OFFICE_MONEYBIRD_BANK_READ_ENABLED','OFFICE_MONEYBIRD_BANK_API_URL','OFFICE_INTEGRATION_SHARED_SECRET'] as $key) if (is_string($activeConfig[$key]??null)) putenv($key.'='.$activeConfig[$key]);
  require_once $root.'/src/Finance/OfficeFinanceAccess.php';
  require_once $root.'/src/Finance/OfficeMoneybirdBankSnapshot.php';
  require_once $root.'/src/Finance/OfficeMoneybirdBankApiClient.php';
  if (!(new \Brebo\Office\Finance\OfficeFinanceAccess($db,'john.boon'))->allowsBankRead()) throw new RuntimeException('Bank access verification failed');
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
  echo "OFFICE_BANK_SIGNED_GET_OK=1\nOFFICE_BANK_AUTHORIZED_RENDER_OK=1\nOFFICE_BANK_UNAUTHORIZED_RENDER_HIDDEN=1\nOFFICE_BANK_ACTIVATION_OK=1\n";
} catch (Throwable $error) {
  if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
  echo "OFFICE_BANK_ACTIVATION_FAILED=1\nOFFICE_BANK_ACTIVATION_FAILED_PHASE=".$phase."\n";
  if ($journal !== null) { try { $restore($journal); } catch (Throwable $ignored) { echo "OFFICE_BANK_RESTORE_REQUIRES_REVIEW=1\n"; } }
  exit(1);
}
