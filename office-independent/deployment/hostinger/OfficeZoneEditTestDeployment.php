<?php
declare(strict_types=1);
namespace Brebo\Office\Deployment;
use RuntimeException;

/** Guarded extension of an enabled Office-test zone installation. Never touches production or DB. */
final class OfficeZoneEditTestDeployment {
  public const REPLACE = ['public/office-onsite-project-map.php','src/Buildings/OfficeBuildingZoneRepository.php'];
  public const ADD = ['public/office-onsite-zone-update.php','src/Inzet/OnSiteOfficeCircleUpdateService.php','src/Inzet/OnSiteOfficeZoneUpdatePostAction.php'];
  private const ROUTE = "  '/office-test/inzet/zone/bewerken' => 'office-onsite-zone-update.php',";
  public function __construct(private string $base,private string $root,private string $router,
    private string $config,private array $files) {}
  private function safeFile(string $path): string {
    if (!in_array($path,[...self::REPLACE,...self::ADD],true)) throw new RuntimeException('unexpected_zone_edit_path');
    $full=$this->root.'/'.$path;
    if (is_link($full) || is_link(dirname($full))) throw new RuntimeException('zone_edit_symlink');
    if (realpath(dirname($full))!==dirname($full)) throw new RuntimeException('zone_edit_parent_missing');
    return $full;
  }
  private function write(string $path,string $content): void {
    $tmp=dirname($path).'/.zone-edit-'.bin2hex(random_bytes(12));
    try {
      $mode=is_file($path)?(fileperms($path)&0777):0640;
      if (file_put_contents($tmp,$content,LOCK_EX)!==strlen($content)
        || !chmod($tmp,$mode) || !rename($tmp,$path)) throw new RuntimeException('zone_edit_write_failed');
    } finally {if (is_file($tmp)) unlink($tmp);}
  }
  public function run(string $mode): array {
    if (!in_array($mode,['--check','--apply','--status','--rollback'],true)) throw new RuntimeException('invalid_zone_edit_mode');
    if (array_keys($this->files)!==[...self::REPLACE,...self::ADD]) throw new RuntimeException('unexpected_zone_edit_files');
    if (realpath($this->base)!==$this->base || realpath($this->root)!==$this->root
      || !str_starts_with($this->root,$this->base.'/')
      || realpath($this->router)!==$this->router || realpath($this->config)!==$this->config)
      throw new RuntimeException('zone_edit_paths_unverified');
    foreach ($this->files as $path=>$source) {
      if (!is_string($source) || !str_starts_with($source,'<?php')) throw new RuntimeException('invalid_zone_edit_source');
      token_get_all($source,TOKEN_PARSE);
      $this->safeFile($path);
    }
    $states=glob($this->base.'/zone-deploy-*/state.json');
    if (!is_array($states) || count($states)!==1 || is_link($states[0])) throw new RuntimeException('zone_edit_install_state_ambiguous');
    $state=json_decode((string)file_get_contents($states[0]),true,512,JSON_THROW_ON_ERROR);
    if (($state['state']??'')!=='enabled') throw new RuntimeException('zone_edit_original_not_enabled');
    $cfg=require $this->config;
    if (!is_array($cfg) || ($cfg['OFFICE_BASE_PATH']??'')!=='/office-test'
      || ($cfg['OFFICE_PRIVATE_ROOT']??'')!==$this->root
      || ($cfg['OFFICE_PUBLIC_HOST']??'')!=='brebobv.nl'
      || ($cfg['OFFICE_INTAKE_DB_USER']??'')!=='u213420663_office_test'
      || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',(string)($cfg['OFFICE_INTAKE_PDO_DSN']??'')))
      throw new RuntimeException('zone_edit_not_isolated');
    if (($cfg['OFFICE_TEST_ZONE_WRITE_ENABLED']??'')!=='enabled'
      || ($cfg['OFFICE_TEST_BUILDING_ZONE_SCHEMA_VERIFIED']??'')!=='enabled') throw new RuntimeException('zone_edit_gate_disabled');
    $routerSource=(string)file_get_contents($this->router);
    $routePresent=str_contains($routerSource,self::ROUTE);
    $digest=substr(hash('sha256',json_encode(array_map(static fn(string $v):string=>hash('sha256',$v),$this->files),JSON_THROW_ON_ERROR)),0,12);
    $dir=$this->base.'/zone-edit-'.$digest;
    $manifest=$dir.'/manifest.json';
    if (is_link($dir) || is_link($manifest)) throw new RuntimeException('zone_edit_state_symlink');
    if (is_file($manifest)) {
      $meta=json_decode((string)file_get_contents($manifest),true,512,JSON_THROW_ON_ERROR);
      if (($meta['state']??'')!=='applied') throw new RuntimeException('zone_edit_state_requires_review');
      foreach ($this->files as $path=>$source) if (!is_file($this->safeFile($path)) || hash_file('sha256',$this->safeFile($path))!==hash('sha256',$source))
        throw new RuntimeException('zone_edit_file_changed:'.$path);
      if (hash_file('sha256',$this->router)!==$meta['router_new']) throw new RuntimeException('zone_edit_router_changed');
      if ($mode==='--rollback') {
        foreach (self::REPLACE as $path) {
          $snapshot=$dir.'/'.str_replace('/','__',$path).'.original';
          if (hash_file('sha256',$snapshot)!==$meta['old'][$path]) throw new RuntimeException('zone_edit_snapshot_changed');
        }
        if (hash_file('sha256',$dir.'/router.original')!==$meta['router_old']) throw new RuntimeException('zone_edit_router_snapshot_changed');
        // Revoke the route first; no request can enter while files are restored.
        $this->write($this->router,(string)file_get_contents($dir.'/router.original'));
        foreach (self::REPLACE as $path) $this->write($this->safeFile($path),(string)file_get_contents($dir.'/'.str_replace('/','__',$path).'.original'));
        foreach (self::ADD as $path) if (!unlink($this->safeFile($path))) throw new RuntimeException('zone_edit_remove_failed');
        $meta['state']='rolled_back';$this->write($manifest,json_encode($meta,JSON_THROW_ON_ERROR));
        return ['status'=>'ROLLED_BACK','database'=>'unchanged','config'=>'unchanged'];
      }
      return ['status'=>'APPLIED','files'=>count($this->files),'database'=>'unchanged'];
    }
    if ($mode==='--rollback') throw new RuntimeException('zone_edit_not_applied');
    if ($routePresent || !str_contains($routerSource,"'/office-test/inzet/zone/aanmaken' => 'office-onsite-zone-create.php',"))
      throw new RuntimeException('zone_edit_router_shape_changed');
    foreach (self::ADD as $path) if (file_exists($this->safeFile($path))) throw new RuntimeException('zone_edit_target_exists:'.$path);
    foreach (self::REPLACE as $path) {
      $target=$this->safeFile($path);
      if (!is_file($target) || !is_readable($target)) throw new RuntimeException('zone_edit_original_missing');
    }
    if ($mode==='--status') return ['status'=>'NOT_APPLIED'];
    if ($mode==='--check') return ['status'=>'READY_TO_APPLY','files'=>count($this->files),'database'=>'unchanged','config'=>'unchanged'];
    if (!mkdir($dir,0700)) throw new RuntimeException('zone_edit_state_create_failed');
    $meta=['state'=>'preparing','old'=>[],'new'=>[],'router_old'=>hash('sha256',$routerSource)];
    foreach (self::REPLACE as $path) {
      $source=(string)file_get_contents($this->safeFile($path));
      $meta['old'][$path]=hash('sha256',$source);
      $this->write($dir.'/'.str_replace('/','__',$path).'.original',$source);
    }
    $this->write($dir.'/router.original',$routerSource);
    $patched=str_replace("  '/office-test/inzet/zone/aanmaken' => 'office-onsite-zone-create.php',",
      "  '/office-test/inzet/zone/aanmaken' => 'office-onsite-zone-create.php',\n".self::ROUTE,$routerSource);
    token_get_all($patched,TOKEN_PARSE);
    $meta['router_new']=hash('sha256',$patched);
    foreach ($this->files as $path=>$source) $meta['new'][$path]=hash('sha256',$source);
    $this->write($manifest,json_encode($meta,JSON_THROW_ON_ERROR));
    foreach ($this->files as $path=>$source) $this->write($this->safeFile($path),$source);
    if (hash_file('sha256',$this->router)!==$meta['router_old']) throw new RuntimeException('zone_edit_router_raced');
    $this->write($this->router,$patched);
    $meta['state']='applied';$this->write($manifest,json_encode($meta,JSON_THROW_ON_ERROR));
    return ['status'=>'APPLIED','files'=>count($this->files),'database'=>'unchanged','config'=>'unchanged'];
  }
}
