<?php
declare(strict_types=1);
namespace Brebo\Office\Deployment;

use RuntimeException;

/**
 * Guarded update of an already enabled Office-test zone installation.
 * Only the existing map and zone repository may be replaced.
 * The router, configuration, database and other installed files are immutable.
 */
final class OfficeZoneTestUpdate {
  public const TARGETS = [
    'public/office-onsite-project-map.php',
    'src/Buildings/OfficeBuildingZoneRepository.php',
  ];

  public function __construct(
    private string $base,
    private string $root,
    private string $router,
    private string $config,
    private array $newFiles
  ) {}

  public function run(string $mode): array {
    if (!in_array($mode, ['--check', '--apply', '--status', '--rollback'], true)) {
      throw new RuntimeException('invalid_update_mode');
    }
    if (array_keys($this->newFiles) !== self::TARGETS) {
      throw new RuntimeException('unexpected_update_targets');
    }
    if (realpath($this->base) !== $this->base || realpath($this->root) !== $this->root
      || !str_starts_with($this->root, $this->base.'/')
      || realpath($this->router) !== $this->router
      || realpath($this->config) !== $this->config) {
      throw new RuntimeException('test_paths_not_verified');
    }
    foreach ($this->newFiles as $path => $source) {
      if (!is_string($source) || !str_starts_with($source, '<?php')) throw new RuntimeException('invalid_update_source');
      token_get_all($source, TOKEN_PARSE);
      if (realpath($this->root.'/'.$path) !== $this->root.'/'.$path) throw new RuntimeException('target_missing_or_symlink');
    }
    $stateDirs=glob($this->base.'/zone-deploy-*/state.json');
    if (!is_array($stateDirs) || count($stateDirs)!==1) throw new RuntimeException('original_install_state_ambiguous');
    $stateFile=$stateDirs[0];
    if (is_link($stateFile) || is_link(dirname($stateFile))) throw new RuntimeException('state_symlink');
    $state=json_decode((string)file_get_contents($stateFile),true,512,JSON_THROW_ON_ERROR);
    if (($state['state']??null)!=='enabled') throw new RuntimeException('original_install_not_enabled');
    if (!hash_equals((string)$state['router_installed'], (string)hash_file('sha256',$this->router))) {
      throw new RuntimeException('router_changed');
    }
    if (!hash_equals((string)$state['config_enabled'], (string)hash_file('sha256',$this->config))) {
      $this->verifyTestConfiguration();
    }
    $digest=hash('sha256',json_encode(array_map(static fn(string $source): string => hash('sha256',$source),$this->newFiles),JSON_THROW_ON_ERROR));
    $updateDir=$this->base.'/zone-update-'.substr($digest,0,12);
    if (is_link($updateDir)) throw new RuntimeException('update_state_symlink');
    $manifest=$updateDir.'/manifest.json';
    if ($mode==='--rollback') {
      if (!is_file($manifest)) throw new RuntimeException('update_not_applied');
      $meta=json_decode((string)file_get_contents($manifest),true,512,JSON_THROW_ON_ERROR);
      if (($meta['state']??'')!=='applied') throw new RuntimeException('update_not_applied');
      foreach (self::TARGETS as $path) {
        $file=$this->root.'/'.$path;
        if (hash_file('sha256',$file)!==$meta['new'][$path]) throw new RuntimeException('updated_file_changed:'.$path);
        if (hash_file('sha256',$updateDir.'/'.basename($path).'.original')!==$meta['old'][$path]) throw new RuntimeException('snapshot_changed');
      }
      foreach (self::TARGETS as $path) {
        $this->write($this->root.'/'.$path,(string)file_get_contents($updateDir.'/'.basename($path).'.original'));
      }
      $meta['state']='rolled_back';$this->write($manifest,json_encode($meta,JSON_THROW_ON_ERROR));
      return ['status'=>'ROLLED_BACK','database'=>'unchanged','config'=>'unchanged','router'=>'unchanged'];
    }
    if (is_file($manifest)) {
      $meta=json_decode((string)file_get_contents($manifest),true,512,JSON_THROW_ON_ERROR);
      if (($meta['state']??'')!=='applied') throw new RuntimeException('update_state_requires_review');
      foreach (self::TARGETS as $path) {
        if (hash_file('sha256',$this->root.'/'.$path)!==$meta['new'][$path]) throw new RuntimeException('updated_file_changed:'.$path);
      }
      return ['status'=>'APPLIED','files'=>count(self::TARGETS)];
    }
    foreach (self::TARGETS as $path) {
      if (hash_file('sha256',$this->root.'/'.$path)!==($state['files'][$path]??null)) {
        throw new RuntimeException('original_file_changed:'.$path);
      }
    }
    if ($mode==='--status') return ['status'=>'NOT_APPLIED'];
    if ($mode==='--check') return ['status'=>'READY_TO_UPDATE','files'=>count(self::TARGETS),'router'=>'unchanged','config'=>'unchanged','database'=>'unchanged'];
    if (!mkdir($updateDir,0700)) throw new RuntimeException('update_state_unavailable');
    $meta=['state'=>'preparing','old'=>[],'new'=>[]];
    foreach (self::TARGETS as $path) {
      $old=(string)file_get_contents($this->root.'/'.$path);
      $meta['old'][$path]=hash('sha256',$old);
      $meta['new'][$path]=hash('sha256',$this->newFiles[$path]);
      $this->write($updateDir.'/'.basename($path).'.original',$old);
    }
    $this->write($manifest,json_encode($meta,JSON_THROW_ON_ERROR));
    foreach (self::TARGETS as $path) {
      if (hash_file('sha256',$this->root.'/'.$path)!==$meta['old'][$path]) throw new RuntimeException('file_changed_during_update');
      $this->write($this->root.'/'.$path,$this->newFiles[$path]);
    }
    $meta['state']='applied';$this->write($manifest,json_encode($meta,JSON_THROW_ON_ERROR));
    return ['status'=>'APPLIED','files'=>count(self::TARGETS),'router'=>'unchanged','config'=>'unchanged','database'=>'unchanged'];
  }

  private function verifyTestConfiguration(): void {
    $settings=require $this->config;
    if (!is_array($settings)) throw new RuntimeException('test_configuration_not_array');
    $get=static function(string $key) use ($settings): ?string {
      $value=$settings[$key]??getenv($key);
      return is_string($value)?$value:null;
    };
    $dsn=$get('OFFICE_INTAKE_DB_DSN');
    if ($get('OFFICE_BASE_PATH')!=='/office-test'
      || $get('OFFICE_PRIVATE_ROOT')!==$this->root
      || $get('OFFICE_PUBLIC_HOST')!=='brebobv.nl'
      || $get('OFFICE_INTAKE_DB_USER')!=='u213420663_office_test'
      || !is_string($dsn)
      || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$dsn)) {
      throw new RuntimeException('test_configuration_identity_mismatch');
    }
  }

  private function write(string $path,string $content): void {
    $temp=dirname($path).'/.zone-update-'.bin2hex(random_bytes(10));
    try {
      $permissions=is_file($path)?(fileperms($path)&0777):0600;
      if (file_put_contents($temp,$content,LOCK_EX)!==strlen($content) || !chmod($temp,$permissions) || !rename($temp,$path)) {
        throw new RuntimeException('update_atomic_write_failed');
      }
    } finally {if (is_file($temp)) unlink($temp);}
  }
}
