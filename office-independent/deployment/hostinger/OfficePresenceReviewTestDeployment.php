<?php
declare(strict_types=1);
namespace Brebo\Office\Deployment;
use RuntimeException;

/** Isolated read-only review deployment. No database or configuration writes. */
final class OfficePresenceReviewTestDeployment {
  public const ADD=[
    'public/office-onsite-presence-review.php',
    'src/Inzet/OnSitePresenceIntervalProjector.php',
    'src/Inzet/OnSitePresenceReviewRepository.php',
    'src/Inzet/OnSitePresenceReviewView.php'
  ];
  public const REPLACE=['public/office-onsite-project-map.php'];
  private const ROUTE="  '/office-test/inzet/aanwezigheid' => 'office-onsite-presence-review.php',";
  public function __construct(private string $base,private string $root,private string $router,
    private string $config,private array $files) {}
  public function run(string $mode):array {
    if (!in_array($mode,['--check','--apply','--status','--rollback'],true)) throw new RuntimeException('invalid_mode');
    if (array_keys($this->files)!==[...self::ADD,...self::REPLACE]) throw new RuntimeException('unexpected_files');
    if (realpath($this->base)!==$this->base || realpath($this->root)!==$this->root
      || !str_starts_with($this->root,$this->base.'/')
      || realpath($this->router)!==$this->router || realpath($this->config)!==$this->config)
      throw new RuntimeException('paths_unverified');
    $cfg=require $this->config;
    if (!is_array($cfg) || ($cfg['OFFICE_BASE_PATH']??'')!=='/office-test'
      || ($cfg['OFFICE_PRIVATE_ROOT']??'')!==$this->root
      || ($cfg['OFFICE_PUBLIC_HOST']??'')!=='brebobv.nl'
      || ($cfg['OFFICE_INTAKE_DB_USER']??'')!=='u213420663_office_test'
      || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',(string)($cfg['OFFICE_INTAKE_PDO_DSN']??'')))
      throw new RuntimeException('test_isolation_failed');
    $htaccess=dirname($this->router).'/.htaccess';
    if (!is_file($htaccess) || is_link($htaccess)
      || !preg_match('~^RewriteRule\\s+\\^inzet/aanwezigheid/\\?\\$\\s+index\\.php\\s+\\[END,QSA\\]\\s*$~m',(string)file_get_contents($htaccess)))
      throw new RuntimeException('presence_review_apache_rewrite_missing');
    $paths=[];
    foreach ($this->files as $path=>$source) {
      if (!is_string($source) || !str_starts_with($source,'<?php')) throw new RuntimeException('invalid_source');
      token_get_all($source,TOKEN_PARSE);
      $target=$this->root.'/'.$path;
      if (is_link($target) || is_link(dirname($target)) || realpath(dirname($target))!==dirname($target))
        throw new RuntimeException('unsafe_target:'.$path);
      $paths[$path]=$target;
    }
    $routerSource=(string)file_get_contents($this->router);
    $anchor="  '/office-test/inzet/projectkaart' => 'office-onsite-project-map.php',";
    $digest=substr(hash('sha256',json_encode(array_map('hash',[]))),0,0);
    $digest=substr(hash('sha256',json_encode(array_map(static fn(string $v):string=>hash('sha256',$v),$this->files),JSON_THROW_ON_ERROR)),0,12);
    $stateDir=$this->base.'/presence-review-'.$digest;
    $manifest=$stateDir.'/manifest.json';
    if (is_link($stateDir) || is_link($manifest)) throw new RuntimeException('unsafe_state');
    $write=static function(string $path,string $data):void {
      $tmp=dirname($path).'/.presence-review-'.bin2hex(random_bytes(8));
      try {
        if (file_put_contents($tmp,$data,LOCK_EX)!==strlen($data) || !chmod($tmp,0640) || !rename($tmp,$path))
          throw new RuntimeException('write_failed');
      } finally {if (is_file($tmp)) unlink($tmp);}
    };
    if (is_file($manifest)) {
      $meta=json_decode((string)file_get_contents($manifest),true,512,JSON_THROW_ON_ERROR);
      if (($meta['state']??'')!=='applied') throw new RuntimeException('deployment_requires_review');
      foreach ($this->files as $path=>$source) {
        if (!is_file($paths[$path]) || hash_file('sha256',$paths[$path])!==hash('sha256',$source))
          throw new RuntimeException('deployed_file_changed:'.$path);
      }
      if (hash_file('sha256',$this->router)!==$meta['router_new']) throw new RuntimeException('router_changed');
      if ($mode==='--rollback') {
        $write($this->router,(string)file_get_contents($stateDir.'/router.original'));
        foreach (self::REPLACE as $path) $write($paths[$path],(string)file_get_contents($stateDir.'/map.original'));
        foreach (self::ADD as $path) if (!unlink($paths[$path])) throw new RuntimeException('remove_failed');
        $meta['state']='rolled_back';$write($manifest,json_encode($meta,JSON_THROW_ON_ERROR));
        return ['status'=>'ROLLED_BACK','database'=>'unchanged','config'=>'unchanged'];
      }
      return ['status'=>'APPLIED','database'=>'unchanged','config'=>'unchanged'];
    }
    if ($mode==='--rollback') throw new RuntimeException('not_applied');
    if (str_contains($routerSource,self::ROUTE) || !str_contains($routerSource,$anchor))
      throw new RuntimeException('router_shape_changed');
    foreach (self::ADD as $path) if (file_exists($paths[$path])) throw new RuntimeException('target_exists:'.$path);
    foreach (self::REPLACE as $path) if (!is_file($paths[$path])) throw new RuntimeException('existing_map_missing');
    if ($mode==='--status') return ['status'=>'NOT_APPLIED'];
    if ($mode==='--check') return ['status'=>'READY_TO_APPLY','database'=>'unchanged','config'=>'unchanged'];
    if (!mkdir($stateDir,0700)) throw new RuntimeException('state_create_failed');
    $write($stateDir.'/router.original',$routerSource);
    $write($stateDir.'/map.original',(string)file_get_contents($paths[self::REPLACE[0]]));
    $patched=str_replace($anchor,$anchor."\n".self::ROUTE,$routerSource);
    token_get_all($patched,TOKEN_PARSE);
    $meta=['state'=>'preparing','router_new'=>hash('sha256',$patched)];
    $write($manifest,json_encode($meta,JSON_THROW_ON_ERROR));
    foreach ($this->files as $path=>$source) $write($paths[$path],$source);
    if (hash_file('sha256',$this->router)!==hash('sha256',$routerSource)) throw new RuntimeException('router_raced');
    $write($this->router,$patched);
    $meta['state']='applied';$write($manifest,json_encode($meta,JSON_THROW_ON_ERROR));
    return ['status'=>'APPLIED','database'=>'unchanged','config'=>'unchanged'];
  }
}
