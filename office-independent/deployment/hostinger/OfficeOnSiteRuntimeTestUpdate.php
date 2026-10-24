<?php
declare(strict_types=1);
namespace Brebo\Office\Deployment;

use RuntimeException;
use Throwable;

/** Code-only, opt-in Office-test update. No PDO, DDL, import or flag changes. */
final class OfficeOnSiteRuntimeTestUpdate {
  private const RULE = "# BREBO Office-test OnSite runtime v1\nRewriteRule ^(?:api/onsite/v1/(?:activate|bootstrap|presence)|inzet/onsite/(?:activatie-uitgeven|apparaat-intrekken))/?$ index.php [END,QSA]\n";
  private const ROUTES = [
    '/office-test/api/onsite/v1/activate'=>'onsite-activate.php',
    '/office-test/api/onsite/v1/bootstrap'=>'onsite-bootstrap.php',
    '/office-test/api/onsite/v1/presence'=>'onsite-presence.php',
    '/office-test/inzet/onsite/activatie-uitgeven'=>'office-onsite-invitation.php',
    '/office-test/inzet/onsite/apparaat-intrekken'=>'office-onsite-device-revoke.php',
  ];
  public function __construct(private string $base, private string $root,
    private string $router, private string $config, private array $sources,
    private array $baseline) {}

  private function hash(string $path): ?string {
    if (is_link($path) || realpath(dirname($path))!==dirname($path)) throw new RuntimeException('unsafe_path:'.$path);
    if (!file_exists($path)) return null;
    if (!is_file($path) || !is_readable($path)) throw new RuntimeException('unreadable_path');
    return hash_file('sha256',$path);
  }
  private function target(string $relative): string {
    if (!array_key_exists($relative,$this->sources) && !isset($this->baseline['protected'][$relative])) {
      throw new RuntimeException('unexpected_target');
    }
    return $this->root.'/'.$relative;
  }
  private function write(string $path,string $data,int $mode=0600): void {
    $tmp=dirname($path).'/.onsite-update-'.bin2hex(random_bytes(12));
    try {
      if (file_put_contents($tmp,$data,LOCK_EX)!==strlen($data) || !chmod($tmp,$mode)
        || !rename($tmp,$path)) throw new RuntimeException('code_write_failed');
    } finally {if (is_file($tmp)) unlink($tmp);}
  }
  private function guards(): array {
    foreach ([$this->base,$this->root,$this->config,$this->router,dirname($this->router).'/.htaccess'] as $path) {
      if (realpath($path)!==$path) throw new RuntimeException('test_paths_unverified');
    }
    if (!str_starts_with($this->root,$this->base.'/') || str_contains($this->root,'/public_html/')) {
      throw new RuntimeException('private_test_root_required');
    }
    $cfg=require $this->config;
    if (!is_array($cfg) || ($cfg['OFFICE_BASE_PATH']??'')!=='/office-test'
      || ($cfg['OFFICE_PRIVATE_ROOT']??'')!==$this->root
      || ($cfg['OFFICE_PUBLIC_HOST']??'')!=='brebobv.nl'
      || ($cfg['OFFICE_INTAKE_DB_USER']??'')!=='u213420663_office_test'
      || !preg_match('/^mysql:host=(?:localhost|127\.0\.0\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',(string)($cfg['OFFICE_INTAKE_PDO_DSN']??''))) {
      throw new RuntimeException('office_test_isolation_failed');
    }
    foreach (['OFFICE_ONSITE_SCHEMA_VERIFIED','OFFICE_ONSITE_ACTIVATION_ENABLED',
      'OFFICE_ONSITE_BOOTSTRAP_ENABLED','OFFICE_ONSITE_PRESENCE_ENABLED',
      'OFFICE_ONSITE_ADMIN_ENABLED','OFFICE_ONSITE_ADMIN_ISSUE_ENABLED',
      'OFFICE_TEST_ONSITE_INVITATION_ISSUE_ENABLED','OFFICE_TEST_ONSITE_DEVICE_REVOKE_ENABLED'] as $gate) {
      if (!in_array($cfg[$gate]??null,[null,'','disabled','false','0',false,0],true)) {
        throw new RuntimeException('consumer_gate_must_remain_closed:'.$gate);
      }
    }
    if (array_keys($this->sources)!==array_keys($this->baseline['old']??[])) throw new RuntimeException('unexpected_code_set');
    foreach ($this->sources as $path=>$code) {
      if (!preg_match('~^(?:public/[a-z-]+\.php|src/Inzet/[A-Za-z]+\.php)$~D',$path)
        || !is_string($code) || !str_starts_with($code,'<?php')) throw new RuntimeException('invalid_runtime_source');
      $this->hash($this->target($path));
      token_get_all($code,TOKEN_PARSE);
    }
    foreach ($this->baseline['protected'] as $path=>$hash) {
      if ($this->hash($this->target($path))!==$hash) throw new RuntimeException('preserved_file_changed:'.$path);
    }
    foreach (['OfficeMount','WebsiteCrmOperatorContext','WebsiteCrmOperatorSessionProviderInterface',
      'WebsiteOfficeCrmSessionRepository','WebsiteOfficeCrmCookieSessionProvider'] as $name) {
      if ($this->hash($this->root.'/src/Intake/'.$name.'.php')===null) throw new RuntimeException('session_dependency_missing:'.$name);
    }
    return ['config'=>$this->hash($this->config),'router'=>$this->hash($this->router),
      'apache'=>$this->hash(dirname($this->router).'/.htaccess')];
  }
  private function patchedRouter(string $source): string {
    foreach (self::ROUTES as $path=>$file) {
      $pattern='~([\'\"])'.preg_quote($path,'~').'\1\s*=>\s*([\'\"])'.preg_quote($file,'~').'\2~';
      if (preg_match($pattern,$source)) continue;
      // Existing test routers can dispatch APIs before consulting the route map.
      $explicit='~if\s*\(\s*\$path\s*===\s*[\'\"]'.preg_quote($path,'~').'[\'\"]\s*\)\s*\{[^}]*?require\s+\$private\s*\.\s*[\'\"]/public/'.preg_quote($file,'~').'[\'\"]\s*;~s';
      if (preg_match($explicit,$source)) continue;
      if (str_contains($source,$path)) throw new RuntimeException('existing_route_requires_review');
      $count=0;
      $source=preg_replace_callback('/(\$routes\s*=\s*\[\s*\n)/',
        static fn(array $m):string=>$m[1]."  '".$path."' => '".$file."',\n",$source,1,$count);
      if ($count!==1) throw new RuntimeException('router_anchor_missing');
    }
    token_get_all($source,TOKEN_PARSE);
    return $source;
  }
  private function patchedApache(string $source): string {
    if (str_contains($source,'# BREBO Office-test OnSite runtime v1')) {
      if (substr_count($source,self::RULE)!==1) throw new RuntimeException('apache_rule_drift');
      return $source;
    }
    $count=0;
    $result=preg_replace_callback('/^([ \t]*RewriteEngine[ \t]+On[ \t]*\r?\n)/mi',
      static fn(array $m):string=>$m[1].self::RULE,$source,-1,$count);
    if ($count!==1) throw new RuntimeException('apache_anchor_missing_or_ambiguous');
    return $result;
  }
  private function entries(string $router,string $apache): array {
    $entries=[];
    foreach ($this->sources as $path=>$new) {
      $target=$this->target($path);
      $entries[$path]=['path'=>$target,'old'=>$this->hash($target),'new'=>hash('sha256',$new),
        'mode'=>is_file($target)?(fileperms($target)&0777):0640];
    }
    foreach (['router'=>[$this->router,$router],'apache'=>[dirname($this->router).'/.htaccess',$apache]] as $key=>[$target,$new]) {
      $entries[$key]=['path'=>$target,'old'=>$this->hash($target),'new'=>hash('sha256',$new),'mode'=>fileperms($target)&0777];
    }
    return $entries;
  }
  private function validateJournal(array $journal,string $dir,bool $installed): void {
    $expected=[...array_keys($this->sources),'router','apache'];
    if (array_keys($journal['entries']??[])!==$expected || ($journal['root']??null)!==$this->root
      || ($journal['config']??null)!==$this->hash($this->config)) throw new RuntimeException('update_journal_or_config_changed');
    foreach ($journal['entries'] as $key=>$entry) {
      $path=$key==='router'?$this->router:($key==='apache'?dirname($this->router).'/.htaccess':$this->target($key));
      if (($entry['path']??'')!==$path) throw new RuntimeException('journal_target_changed');
      $now=$this->hash($path);
      if ($installed?$now!==$entry['new']:!in_array($now,[$entry['old'],$entry['new']],true)) throw new RuntimeException('deployed_code_changed:'.$key);
      if ($entry['old']!==null && $this->hash($dir.'/'.hash('sha256',$key).'.before')!==$entry['old']) {
        throw new RuntimeException('backup_changed:'.$key);
      }
    }
  }
  private function rollback(array $journal,string $dir): void {
    $this->validateJournal($journal,$dir,false);
    // Restore entrypoints before implementation; every consumer gate is closed.
    foreach (array_reverse($journal['entries'],true) as $key=>$entry) {
      if ($entry['old']===null) {
        if (is_file($entry['path']) && !unlink($entry['path'])) throw new RuntimeException('rollback_new_code_failed');
      } else {
        $this->write($entry['path'],(string)file_get_contents($dir.'/'.hash('sha256',$key).'.before'),$entry['mode']);
      }
    }
    $journal['state']='rolled_back';
    $this->write($dir.'/state.json',json_encode($journal,JSON_THROW_ON_ERROR));
  }
  public function run(string $mode): array {
    if (!in_array($mode,['--check','--apply','--status','--rollback'],true)) throw new RuntimeException('invalid_update_mode');
    $guards=$this->guards();
    $digest=substr(hash('sha256',json_encode([$this->sources,$this->baseline],JSON_THROW_ON_ERROR)),0,16);
    $dir=$this->base.'/onsite-runtime-update-'.$digest;
    if (is_link($dir)) throw new RuntimeException('unsafe_update_state');
    $state=$dir.'/state.json';
    if (is_file($state)) {
      if (is_link($state)) throw new RuntimeException('unsafe_update_journal');
      $journal=json_decode((string)file_get_contents($state),true,32,JSON_THROW_ON_ERROR);
      $installed=($journal['state']??'')==='installed';
      if (!in_array($journal['state']??'',['installed','preparing'],true)) throw new RuntimeException('update_already_rolled_back');
      $this->validateJournal($journal,$dir,$installed);
      if ($mode==='--rollback') {
        $lock=fopen($dir.'/lock','c');
        if ($lock===false || !flock($lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('update_lock_busy');
        try {$this->rollback($journal,$dir);} finally {flock($lock,LOCK_UN);fclose($lock);}
        return ['status'=>'ROLLED_BACK','database'=>'UNCHANGED','config'=>'UNCHANGED','flags'=>'UNCHANGED'];
      }
      if (!$installed) throw new RuntimeException('interrupted_update_requires_rollback');
      return ['status'=>'INSTALLED','files'=>count($this->sources),'database'=>'UNCHANGED','config'=>'UNCHANGED','flags'=>'UNCHANGED'];
    }
    if ($mode==='--rollback') throw new RuntimeException('update_not_installed');
    if (file_exists($dir)) throw new RuntimeException('update_state_without_journal_requires_review');
    foreach ($this->sources as $path=>$source) {
      $now=$this->hash($this->target($path));
      if ($now!==$this->baseline['old'][$path] && $now!==hash('sha256',$source)) throw new RuntimeException('unreviewed_server_code:'.$path);
    }
    $router=$this->patchedRouter((string)file_get_contents($this->router));
    $apache=$this->patchedApache((string)file_get_contents(dirname($this->router).'/.htaccess'));
    $entries=$this->entries($router,$apache);
    if ($mode==='--status') return ['status'=>'NOT_INSTALLED'];
    if ($mode==='--check') return ['status'=>'READY_TO_APPLY','files'=>count($this->sources),'database'=>'UNCHANGED','config'=>'UNCHANGED','flags'=>'UNCHANGED','shared_adapter'=>'PRESERVED'];
    if (!mkdir($dir,0700)) throw new RuntimeException('update_state_create_failed');
    $lock=fopen($dir.'/lock','x');
    if ($lock===false || !flock($lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('update_lock_busy');
    try {
      // Every original is durable and verified before the first runtime write.
      foreach ($entries as $key=>$entry) {
        if ($entry['old']!==null) {
          $before=(string)file_get_contents($entry['path']);
          if (hash('sha256',$before)!==$entry['old']) throw new RuntimeException('server_code_changed_before_backup');
          $this->write($dir.'/'.hash('sha256',$key).'.before',$before);
        }
      }
      $journal=['state'=>'preparing','root'=>$this->root,'config'=>$guards['config'],'entries'=>$entries];
      $this->write($state,json_encode($journal,JSON_THROW_ON_ERROR));
      try {
        foreach ([...$this->sources,'router'=>$router,'apache'=>$apache] as $key=>$new) {
          $entry=$entries[$key];
          if ($this->hash($entry['path'])!==$entry['old'] || $this->hash($this->config)!==$guards['config']) throw new RuntimeException('server_changed_during_update');
          if ($entry['old']!==$entry['new']) $this->write($entry['path'],$new,$entry['mode']);
        }
        $this->guards();
        $this->validateJournal($journal,$dir,true);
      } catch (Throwable $error) {
        // Refuse to overwrite any concurrent edit; leave the private journal for review.
        $this->rollback($journal,$dir);
        throw $error;
      }
      $journal['state']='installed';
      $this->write($state,json_encode($journal,JSON_THROW_ON_ERROR));
      return ['status'=>'INSTALLED','files'=>count($this->sources),'database'=>'UNCHANGED','config'=>'UNCHANGED','flags'=>'UNCHANGED','shared_adapter'=>'PRESERVED'];
    } finally {flock($lock,LOCK_UN);fclose($lock);}
  }
}
