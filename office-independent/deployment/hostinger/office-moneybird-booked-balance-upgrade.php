<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
ini_set('display_errors','0'); umask(0077);
define('OFFICE_BANK_ACTIVATION_LIBRARY_ONLY',true);
require __DIR__.'/office-moneybird-bank-activate.php';
// This upgrade never writes configuration or database permissions.
$stage=dirname(__DIR__,2);
$base=rtrim((string)getenv('HOME'),'/').'/domains/brebobv.nl/office_calc_test';
$configFile=$base.'/config/config.php';
$paths=['src/Finance/OfficeMoneybirdBankSnapshot.php','src/Finance/OfficeFinanceBankPanel.php'];
$journalFile=$stage.'/booked-balance-upgrade.json';
$db=null; $journal=null; $phase='scope';
$restore=static function(array $j) use($base,$paths,$stage): void {
  $root=$j['root']??'';
  if (!is_string($root) || !str_starts_with($root,$base.'/release-') || !str_ends_with($root,'/private') || realpath($root)!==$root || array_keys($j['files']??[])!==$paths) throw new RuntimeException('Unsafe restore scope');
  foreach($j['files'] as $path=>$row) {
    $file=$root.'/'.$path;
    if (!is_file($file) || is_link($file) || !in_array(hash_file('sha256',$file),[$row['before'],$row['after']],true)) throw new RuntimeException('Concurrent change blocks restore');
  }
  foreach($j['files'] as $path=>$row) bankRestore($root.'/'.$path,$row['before'],$row['after'],file_get_contents($stage.'/booked-balance-backup/'.$path),$row['mode']);
  echo "OFFICE_BOOKED_BALANCE_FILES_RESTORED=1\n";
};
try {
  $mode=$argv[1]??'';
  if($mode==='--rollback') {
    if(is_file($journalFile)) $restore(json_decode(file_get_contents($journalFile),true,32,JSON_THROW_ON_ERROR));
    else echo "OFFICE_BOOKED_BALANCE_ROLLBACK_NOT_NEEDED=1\n";
    exit;
  }
  if(!in_array($mode,['--check','--apply'],true) || !is_file($configFile) || is_link($configFile) || realpath($configFile)!==$configFile) throw new RuntimeException('Invalid scope');
  $configHash=hash_file('sha256',$configFile);
  $config=bankLoad($configFile); $root=$config['OFFICE_PRIVATE_ROOT']??'';
  if(($config['OFFICE_BASE_PATH']??'')!=='/office-test' || !str_starts_with($root,$base.'/release-') || !str_ends_with($root,'/private') || realpath($root)!==$root
      || ($config['OFFICE_MONEYBIRD_BANK_READ_ENABLED']??'')!=='enabled' || ($config['OFFICE_MONEYBIRD_BANK_API_URL']??'')!=='https://brebo-integration-api.john-boon.workers.dev/v1/accounting/bank-data') throw new RuntimeException('Existing bank configuration required');
  if(!is_file($stage.'/activation-preview/PLAN_READY') || trim(file_get_contents($stage.'/activation-preview/PLAN_READY'))!==trim(file_get_contents($stage.'/SOURCE_SHA'))) throw new RuntimeException('Preview missing');
  $expected=[];
  foreach(file($stage.'/activation-preview/active-before.sha256',FILE_IGNORE_NEW_LINES) as $line) {
    if(!preg_match('/^([a-f0-9]{64})  (.+)$/D',$line,$m) || !is_file($m[2]) || is_link($m[2]) || hash_file('sha256',$m[2])!==$m[1]) throw new RuntimeException('Checked target changed');
    $expected[$m[2]]=$m[1];
  }
  if(($expected[$configFile]??null)!==$configHash) throw new RuntimeException('Unchecked configuration');
  $phase='existing_access';
  $db=bankDb($config);
  $db->exec('SET TRANSACTION READ ONLY'); $db->beginTransaction();
  $fingerprint=(string)getenv('OFFICE_BANK_ACCOUNT_FINGERPRINT');
  if(!preg_match('/^[a-f0-9]{64}$/D',$fingerprint)) throw new RuntimeException('Authenticated account proof missing');
  $operatorId=bankJohn($db,$fingerprint);
  $q=$db->prepare("SELECT 1 FROM office_module_permissions WHERE operator_id=? AND module='finance' AND permission='bank_read'"); $q->execute([$operatorId]);
  if($q->fetchColumn()===false) throw new RuntimeException('Existing bank grant required');
  echo "OFFICE_BOOKED_BALANCE_EXISTING_ACCESS_VERIFIED=1\n";
  if($mode==='--check') { $db->rollBack(); exit; }
  $phase='backup';
  if(file_exists($journalFile)) throw new RuntimeException('Upgrade already exists');
  $journal=['root'=>$root,'files'=>[]];
  foreach($paths as $path) {
    $file=$root.'/'.$path; $source=$stage.'/activation-preview/'.$path;
    if(!isset($expected[$file]) || !is_file($source)) throw new RuntimeException('Unchecked bank file');
    $backup=$stage.'/booked-balance-backup/'.$path;
    if(!is_dir(dirname($backup))) mkdir(dirname($backup),0700,true);
    copy($file,$backup); chmod($backup,0600);
    if(hash_file('sha256',$backup)!==$expected[$file]) throw new RuntimeException('Backup changed');
    $journal['files'][$path]=['before'=>$expected[$file],'after'=>hash_file('sha256',$source),'mode'=>fileperms($file)&0777];
  }
  $json=json_encode($journal,JSON_THROW_ON_ERROR);
  if(file_put_contents($journalFile,$json,LOCK_EX)!==strlen($json)) throw new RuntimeException('Journal failed');
  $phase='files';
  foreach($paths as $path) bankReplace($root.'/'.$path,$journal['files'][$path]['before'],file_get_contents($stage.'/activation-preview/'.$path),$journal['files'][$path]['mode']);
  foreach(['OFFICE_BASE_PATH','OFFICE_MONEYBIRD_BANK_API_URL','OFFICE_INTEGRATION_SHARED_SECRET'] as $key) putenv($key.'='.$config[$key]);
  require_once $root.'/src/Finance/OfficeMoneybirdBankSnapshot.php';
  require_once $root.'/src/Finance/OfficeMoneybirdBankApiClient.php';
  require_once $root.'/src/Finance/OfficeFinanceBankPanel.php';
  $phase='report';
  $snapshot=\Brebo\Office\Finance\OfficeMoneybirdBankApiClient::fetch();
  if(!is_array($snapshot['booked_balances']??null) || ($snapshot['booked_balances']['source']??'')!=='moneybird_bank_balance_report') throw new RuntimeException('Booked report unavailable');
  $html=\Brebo\Office\Finance\OfficeFinanceBankPanel::render($snapshot);
  if(!str_contains($html,'Rapport opgevraagd:') || !str_contains($html,'Geen door de bank bevestigd actueel of beschikbaar saldo')) throw new RuntimeException('Report provenance missing');
  if(hash_file('sha256',$configFile)!==$configHash) throw new RuntimeException('Configuration changed externally');
  $db->rollBack();
  echo "OFFICE_BOOKED_BALANCE_REAL_GET_OK=1\nOFFICE_BOOKED_BALANCE_RENDER_OK=1\nOFFICE_CONFIG_UNCHANGED=1\nOFFICE_PERMISSIONS_READ_ONLY=1\nOFFICE_BOOKED_BALANCE_UPGRADE_OK=1\n";
} catch(Throwable $ignored) {
  if($db instanceof PDO && $db->inTransaction()) $db->rollBack();
  echo "OFFICE_BOOKED_BALANCE_UPGRADE_FAILED_PHASE=".$phase."\n";
  if($journal!==null && count($journal['files'])===count($paths)) { try { $restore($journal); } catch(Throwable $ignoredRestore) { echo "OFFICE_BOOKED_BALANCE_RESTORE_REQUIRES_REVIEW=1\n"; } }
  exit(1);
}
