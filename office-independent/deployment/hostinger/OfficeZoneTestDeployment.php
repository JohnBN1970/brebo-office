<?php
declare(strict_types=1);
namespace Brebo\Office\Deployment;
use RuntimeException;

/** Fixed-scope test installer. No database writes, deletions, shared adapter replacement or symlinks. */
final class OfficeZoneTestDeployment {
  private string $stateDir;
  public function __construct(
    private string $base, private string $root, private string $router, private string $config,
    private string $routerHash, private array $protected, private array $files, private \Closure $schemaGuard
  ) {
    $this->stateDir=$base.'/zone-deploy-'.substr(hash('sha256',json_encode($files,JSON_THROW_ON_ERROR)),0,12);
  }

  public function run(string $mode): array {
    if (!in_array($mode,['--check','--install','--status','--enable','--rollback'],true)) throw new RuntimeException('unknown_mode');
    $this->paths();
    if ($mode==='--rollback') return $this->rollback();
    $c=$this->configuration();
    ($this->schemaGuard)($c);
    foreach ($this->protected as $file=>$hash) {
      if ($this->hash($this->root.'/'.$file)!==$hash) throw new RuntimeException('shared_file_changed:'.$file);
    }
    $meta=$this->metadata();
    if ($meta!==null) {
      $this->verifyInstalled($meta);
      if ($mode==='--enable') return $this->enable($c,$meta);
      if (!in_array($mode,['--status','--check','--install'],true)) throw new RuntimeException('invalid_installed_action');
      return $this->status($c,$meta);
    }
    if (in_array($mode,['--enable','--status'],true)) throw new RuntimeException('install_required');
    if (($c['OFFICE_TEST_ZONE_WRITE_ENABLED'] ?? '')==='enabled') throw new RuntimeException('writes_must_be_disabled');
    if ($this->hash($this->router)!==$this->routerHash) throw new RuntimeException('router_changed_since_inspection');
    foreach ($this->files as $path=>$content) {
      if (!preg_match('~^(?:public|src/(?:Buildings|Inzet))/[A-Za-z0-9_-]+\.php$~D',$path)) throw new RuntimeException('unsafe_payload_path');
      if (file_exists($this->root.'/'.$path) || is_link($this->root.'/'.$path)) throw new RuntimeException('target_already_exists:'.$path);
      token_get_all($content,TOKEN_PARSE);
      $this->safeParents($this->root.'/'.$path);
    }
    $routerSource=file_get_contents($this->router);
    $patched=$this->patchRouter($routerSource);
    token_get_all($patched,TOKEN_PARSE);
    if ($mode==='--check') return ['status'=>'READY_TO_INSTALL','files'=>count($this->files),'schema'=>'PASS','writes'=>'disabled','shared_files'=>'unchanged'];
    if (file_exists($this->stateDir) || !mkdir($this->stateDir,0700)) throw new RuntimeException('state_directory_unavailable');
    $this->atomic($this->stateDir.'/router.original.php',$routerSource,0600);
    $this->atomic($this->stateDir.'/config.original.php',file_get_contents($this->config),0600);
    $meta=['state'=>'installing','router_original'=>$this->routerHash,'router_installed'=>hash('sha256',$patched),
      'config_original'=>$this->hash($this->config),'config_enabled'=>null,'files'=>[]];
    foreach ($this->files as $path=>$content) $meta['files'][$path]=hash('sha256',$content);
    $this->save($meta);
    foreach ($this->files as $path=>$content) {
      $target=$this->root.'/'.$path;
      $this->safeParents($target);
      if (!is_dir(dirname($target)) && !mkdir(dirname($target),0750,true)) throw new RuntimeException('target_directory_unavailable');
      $this->safeParents($target);
      $this->atomic($target,$content,0640);
    }
    // Route publication is last: a partial copy cannot expose an incomplete endpoint.
    if ($this->hash($this->router)!==$this->routerHash || $this->hash($this->config)!==$meta['config_original']) {
      throw new RuntimeException('runtime_changed_during_install');
    }
    $this->atomic($this->router,$patched,fileperms($this->router)&0777);
    $meta['state']='installed';$this->save($meta);
    $this->verifyInstalled($meta);
    return $this->status($c,$meta);
  }

  private function paths(): void {
    foreach ([$this->base,$this->root] as $dir) {
      if (realpath($dir)!==$dir || !is_dir($dir)) throw new RuntimeException('test_path_unavailable_or_symlink');
    }
    if (!str_starts_with($this->root,$this->base.'/')) throw new RuntimeException('private_root_outside_test');
    if (!str_starts_with($this->config,$this->base.'/') || realpath($this->config)!==$this->config
      || realpath($this->router)!==$this->router) throw new RuntimeException('config_or_router_path_changed');
    $this->safeParents($this->config);
    if (is_link($this->stateDir)) throw new RuntimeException('state_symlink_refused');
  }

  private function configuration(): array {
    $c=require $this->config;
    if (!is_array($c) || ($c['OFFICE_BASE_PATH'] ?? '')!=='/office-test'
      || ($c['OFFICE_PRIVATE_ROOT'] ?? '')!==$this->root) throw new RuntimeException('isolated_test_configuration_required');
    foreach ($c as $key=>$value) if (!is_string($key) || !is_string($value)) throw new RuntimeException('non_string_configuration');
    return $c;
  }

  private function patchRouter(string $source): string {
    foreach (['/office-test/inzet/projectkaart','/office-test/inzet/zone/aanmaken'] as $route) {
      if (str_contains($source,$route)) throw new RuntimeException('route_already_present_review_required');
    }
    $positions=[];$offset=0;$phase=0;
    foreach (token_get_all($source,TOKEN_PARSE) as $token) {
      $text=is_array($token) ? $token[1] : $token;
      $ignored=is_array($token) && in_array($token[0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true);
      if (!$ignored) {
        if (is_array($token) && $token[0]===T_VARIABLE && $text==='$routes') $phase=1;
        elseif ($phase===1 && $text==='=') $phase=2;
        elseif ($phase===2 && $text==='[') {$positions[]=$offset+1;$phase=0;}
        else $phase=0;
      }
      $offset+=strlen($text);
    }
    if (count($positions)!==1) {
      throw new RuntimeException('route_table_shape_unrecognized');
    }
    $at=$positions[0];
    return substr($source,0,$at)."\n  '/office-test/inzet/projectkaart' => 'office-onsite-project-map.php',\n"
      ."  '/office-test/inzet/zone/aanmaken' => 'office-onsite-zone-create.php',".substr($source,$at);
  }

  private function enable(array $c,array $meta): array {
    if ($meta['state']==='enabled') return $this->status($c,$meta);
    if ($this->hash($this->config)!==$meta['config_original']) throw new RuntimeException('config_changed_since_install');
    foreach (['OFFICE_NATIVE_PROJECTS_ENABLED','OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED','OFFICE_CRM_SESSION_MODE'] as $flag) {
      if (($c[$flag] ?? '')!=='enabled') throw new RuntimeException('required_runtime_gate_disabled:'.$flag);
    }
    if (strlen($c['OFFICE_TEST_ZONE_CSRF_SECRET'] ?? '')<32) $c['OFFICE_TEST_ZONE_CSRF_SECRET']=bin2hex(random_bytes(32));
    $c['OFFICE_TEST_BUILDING_ZONE_SCHEMA_VERIFIED']='enabled';
    $c['OFFICE_TEST_ZONE_WRITE_ENABLED']='enabled';
    $source="<?php\n// Office-test configuration. Never copy to production.\nreturn ".var_export($c,true).";\n";
    token_get_all($source,TOKEN_PARSE);
    $meta['config_enabled']=hash('sha256',$source);$meta['state']='enabling';$this->save($meta);
    $this->atomic($this->config,$source,0600);
    $meta['state']='enabled';$this->save($meta);
    return $this->status($c,$meta);
  }

  private function rollback(): array {
    $meta=$this->metadata();
    if ($meta===null) throw new RuntimeException('no_installation_snapshot');
    $routerHash=$this->hash($this->router);$configHash=$this->hash($this->config);
    if (!in_array($routerHash,[$meta['router_original'],$meta['router_installed']],true)
      || !in_array($configHash,[$meta['config_original'],$meta['config_enabled']],true)) {
      throw new RuntimeException('runtime_changed_rollback_requires_review');
    }
    if ($this->hash($this->stateDir.'/router.original.php')!==$meta['router_original']
      || $this->hash($this->stateDir.'/config.original.php')!==$meta['config_original']) throw new RuntimeException('snapshot_integrity_failed');
    $this->atomic($this->config,file_get_contents($this->stateDir.'/config.original.php'),0600);
    $this->atomic($this->router,file_get_contents($this->stateDir.'/router.original.php'),fileperms($this->router)&0777);
    $meta['state']='rolled_back';$this->save($meta);
    return ['status'=>'ROLLED_BACK','routes'=>'original','config'=>'original','database'=>'unchanged','files'=>'retained privately'];
  }

  private function verifyInstalled(array $meta): void {
    if (!in_array($meta['state'],['installed','enabling','enabled'],true)) throw new RuntimeException('incomplete_or_rolled_back_installation');
    if ($this->hash($this->router)!==$meta['router_installed']) throw new RuntimeException('installed_router_changed');
    if (!in_array($this->hash($this->config),[$meta['config_original'],$meta['config_enabled']],true)) throw new RuntimeException('installed_config_changed');
    foreach ($meta['files'] as $file=>$hash) {
      $this->safeParents($this->root.'/'.$file);
      if ($this->hash($this->root.'/'.$file)!==$hash) throw new RuntimeException('installed_file_changed:'.$file);
    }
  }

  private function status(array $c,array $meta): array {
    return ['status'=>strtoupper($meta['state']),'files'=>count($meta['files']),'schema'=>'PASS',
      'zone_write_enabled'=>($c['OFFICE_TEST_ZONE_WRITE_ENABLED'] ?? '')==='enabled',
      'zone_schema_verified'=>($c['OFFICE_TEST_BUILDING_ZONE_SCHEMA_VERIFIED'] ?? '')==='enabled',
      'zone_secret_configured'=>strlen($c['OFFICE_TEST_ZONE_CSRF_SECRET'] ?? '')>=32,
      'shared_files'=>'unchanged','database'=>'unchanged'];
  }

  private function safeParents(string $file): void {
    if ($file===$this->router) {
      if (realpath($file)!==$file) throw new RuntimeException('router_path_changed');
      return;
    }
    if (!str_starts_with($file,$this->base.'/') || str_contains($file,'/../')) throw new RuntimeException('unsafe_test_path');
    $path=$file;
    while (str_starts_with($path,$this->base.'/')) {
      if (is_link($path)) throw new RuntimeException('symlink_refused');
      $path=dirname($path);
    }
  }

  private function metadata(): ?array {
    $file=$this->stateDir.'/state.json';
    if (!is_file($file)) return null;
    if (is_link($file)) throw new RuntimeException('state_symlink_refused');
    return json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
  }
  private function save(array $meta): void {$this->atomic($this->stateDir.'/state.json',json_encode($meta,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),0600);}
  private function hash(string $file): ?string {return is_file($file) && !is_link($file) ? hash_file('sha256',$file) : null;}
  private function atomic(string $path,string $source,int $mode): void {
    $this->safeParents($path);
    $tmp=dirname($path).'/.zone-'.bin2hex(random_bytes(12)).'.tmp';
    try {
      if (file_put_contents($tmp,$source,LOCK_EX)!==strlen($source) || !chmod($tmp,$mode) || !rename($tmp,$path)) {
        throw new RuntimeException('atomic_test_write_failed');
      }
    } finally {if (is_file($tmp)) unlink($tmp);}
  }
}

