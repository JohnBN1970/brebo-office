<?php
declare(strict_types=1);

/** Fixed-scope Office-test rollout. No router/shared-adapter/database writes. */
final class OfficeInzetReviewTestDeployment {
  private const KEY='OFFICE_TEST_INZET_REVIEW_DECISIONS_SQLITE_PATH';
  private const PAGE='public/office-inzet-import-review.php';
  private const MAINTENANCE="<?php http_response_code(503); header('Cache-Control: no-store'); exit('Importcontrole wordt bijgewerkt.');\n";
  private const FILES=[self::PAGE,'src/Inzet/OfficeInzetImportReviewReadRepository.php','src/Inzet/OfficeInzetSourceReviewWorkbench.php','src/Inzet/OfficeInzetReviewDecisionRepository.php','src/Inzet/OfficeInzetReviewAccess.php'];
  public function __construct(private array $package,private ?Closure $step=null) {
    $paths=array_keys($package['files']??[]);$expected=self::FILES;sort($paths);sort($expected);
    if($paths!==$expected||count($package['shared']??[])!==5)throw new RuntimeException('PACKAGE_SCOPE_INVALID');
    foreach($package['files'] as $entry){$code=base64_decode($entry['base64'],true);if(!is_string($code)||!hash_equals($entry['sha256'],hash('sha256',$code)))throw new RuntimeException('PACKAGE_HASH_INVALID');token_get_all($code,TOKEN_PARSE);}
  }
  private static function inside(string $path,string $root): bool {
    $path=str_replace('\\','/',$path);$root=rtrim(str_replace('\\','/',$root),'/').'/';
    return PHP_OS_FAMILY==='Windows'?str_starts_with(strtolower($path),strtolower($root)):str_starts_with($path,$root);
  }
  private static function samePath(string $left,string $right): bool {
    $left=str_replace('\\','/',$left);$right=str_replace('\\','/',$right);
    return PHP_OS_FAMILY==='Windows'?strtolower($left)===strtolower($right):$left===$right;
  }
  private static function existing(string $path,string $root): string {
    $real=realpath($path);if($real===false||!is_file($real)||is_link($path)||!self::inside($real,$root))throw new RuntimeException('PRIVATE_FILE_BOUNDARY_REQUIRED');return $real;
  }
  private static function loadConfig(string $path): array {
    ob_start();try{$value=require $path;}finally{ob_end_clean();}if(!is_array($value))throw new RuntimeException('CONFIG_ARRAY_REQUIRED');return $value;
  }
  private static function checksum(string $path): ?string {return is_file($path)?hash_file('sha256',$path):null;}
  private static function atomic(string $path,string $content): void {
    $temp=tempnam(dirname($path),'review-stage-');if($temp===false)throw new RuntimeException('STAGING_FAILED');
    try{if(file_put_contents($temp,$content)!==strlen($content)||!chmod($temp,0600)||!rename($temp,$path))throw new RuntimeException('ATOMIC_PUBLICATION_FAILED');}finally{if(is_file($temp))unlink($temp);}
  }
  /** Replace one literal value while retaining all other config bytes. */
  private static function patchedConfig(string $code,string $old,string $new): string {
    $tokens=token_get_all($code,TOKEN_PARSE);$offset=0;$matches=[];
    $skip=static function(int $i)use($tokens):int{while(isset($tokens[$i])&&is_array($tokens[$i])&&in_array($tokens[$i][0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true))$i++;return $i;};
    foreach($tokens as $i=>$token){
      $text=is_array($token)?$token[1]:$token;
      if(is_array($token)&&$token[0]===T_CONSTANT_ENCAPSED_STRING&&in_array($text,["'".self::KEY."'",'"'.self::KEY.'"'],true)){
        $arrow=$skip($i+1);$value=$skip($arrow+1);$end=$skip($value+1);
        if(!isset($tokens[$arrow],$tokens[$value],$tokens[$end])||!is_array($tokens[$arrow])||$tokens[$arrow][0]!==T_DOUBLE_ARROW||!is_array($tokens[$value])||$tokens[$value][0]!==T_CONSTANT_ENCAPSED_STRING||!in_array($tokens[$end],[',',']',')'],true))throw new RuntimeException('CONFIG_LITERAL_VALUE_REQUIRED');
        $literal=$tokens[$value][1];$body=substr($literal,1,-1);$decoded=$literal[0]==="'"?str_replace(["\\'","\\\\"],["'","\\"],$body):stripcslashes($body);
        if($decoded!==$old)throw new RuntimeException('CONFIG_LITERAL_VALUE_MISMATCH');
        $start=$offset;for($j=$i;$j<$value;$j++)$start+=strlen(is_array($tokens[$j])?$tokens[$j][1]:$tokens[$j]);$matches[]=[$start,strlen($literal)];
      }
      $offset+=strlen($text);
    }
    if(count($matches)!==1)throw new RuntimeException('CONFIG_KEY_AMBIGUOUS');
    $result=substr_replace($code,var_export($new,true),$matches[0][0],$matches[0][1]);token_get_all($result,TOKEN_PARSE);return $result;
  }
  private static function snapshotRows(PDO $db,string $table): array {
    $key=$table==='inzet_review_decisions'?'issue_id':'id';$rows=$db->query('SELECT * FROM '.$table.' ORDER BY '.$key)->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$row)$row['reviewer_id']=(string)$row['reviewer_id'];unset($row);return $rows;
  }
  private static function readLock(string $path): PDO {
    $uri=str_replace(['%2F','%3A','%5C'],['/',':','/'],rawurlencode($path));$db=new PDO('sqlite:file:'.$uri.'?mode=ro',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA query_only=ON');$db->beginTransaction();$db->query('SELECT name FROM sqlite_master LIMIT 1')->fetchColumn();return $db;
  }
  public function check(string $base,string $web,string $configPath,string $copyHash): array {
    $base=realpath($base);$web=realpath($web);if($base===false||$web===false||self::inside($base,$web)||$base===$web)throw new RuntimeException('TEST_BOUNDARY_REQUIRED');
    $configPath=self::existing($configPath,$base);$config=self::loadConfig($configPath);
    if(($config['OFFICE_BASE_PATH']??null)!=='/office-test'||($config['OFFICE_PUBLIC_HOST']??null)!=='brebobv.nl'||($config['OFFICE_INTAKE_DB_USER']??null)!=='u213420663_office_test'||!preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$config['OFFICE_INTAKE_PDO_DSN']??'')||($config['OFFICE_TEST_INZET_IMPORT_REVIEW_ENABLED']??null)!=='enabled')throw new RuntimeException('OFFICE_TEST_CONFIG_REQUIRED');
    $root=realpath($config['OFFICE_PRIVATE_ROOT']??'');if($root===false||!is_dir($root)||!self::inside($root,$base))throw new RuntimeException('TEST_PRIVATE_RUNTIME_REQUIRED');
    $router=self::existing($web.'/office-test/index.php',$web);$tokens=token_get_all(file_get_contents($router),TOKEN_PARSE);$routerCode='';
    foreach($tokens as $token)if(!is_array($token)||!in_array($token[0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true))$routerCode.=is_array($token)?$token[1]:$token;
    $mapped=preg_match('~[\'"]/office-test/inzet/importcontrole[\'"]=>[\'"]office-inzet-import-review\.php[\'"]~',$routerCode)||preg_match('~if\(\$path===[\'"]/office-test/inzet/importcontrole[\'"]\)\{require(?:_once)?\$private\.[\'"]/public/office-inzet-import-review\.php[\'"];exit;\}~',$routerCode);
    if(!$mapped)throw new RuntimeException('EXISTING_REVIEW_ROUTE_REQUIRED');
    $literal=var_export($config['OFFICE_PRIVATE_ROOT'],true);
    $direct=str_contains($routerCode,'$private='.$literal.';');
    $rootVariable=str_contains($routerCode,'$root='.$literal.';')&&str_contains($routerCode,'$private=realpath($root);');
    $environment=str_contains($routerCode,"\$private=realpath(getenv('OFFICE_PRIVATE_ROOT'));" )||str_contains($routerCode,'$private=realpath(getenv("OFFICE_PRIVATE_ROOT"));');
    if(!$direct&&!$rootVariable&&!$environment)throw new RuntimeException('ROUTER_PRIVATE_ROOT_REVIEW_REQUIRED');
    foreach($this->package['shared'] as $relative=>$hash){$path=self::existing($root.'/'.$relative,$root);if(!hash_equals($hash,hash_file('sha256',$path)))throw new RuntimeException('SHARED_DEPENDENCY_MISMATCH:'.basename($relative));}
    $original=self::existing($config[self::KEY]??'',$base);$copy=self::existing(dirname($original).'/review-decisions-text-v1.sqlite',$base);
    if(!preg_match('/^[a-f0-9]{64}$/D',$copyHash)||!hash_equals($copyHash,hash_file('sha256',$copy)))throw new RuntimeException('PREPARED_COPY_HASH_MISMATCH');
    $source=self::existing($config['OFFICE_TEST_INZET_SQLITE_PATH']??'',$base);$newConfig=$config;$newConfig[self::KEY]=$copy;
    $report=OfficeInzetReviewPreflight::inspect($newConfig,$base,$web);if($report['status']!=='READY_FOR_REVIEW')throw new RuntimeException('PREPARED_COPY_PREFLIGHT_FAILED');
    $locks=[self::readLock($original),self::readLock($copy),self::readLock($source)];
    foreach(['inzet_review_decisions','inzet_review_decision_audit'] as $table)if(self::snapshotRows($locks[0],$table)!==self::snapshotRows($locks[1],$table))throw new RuntimeException('ORIGINAL_CHANGED_SINCE_COPY');
    foreach($locks as $db)if($db->query('PRAGMA journal_mode')->fetchColumn()!=='delete')throw new RuntimeException('OFFLINE_SNAPSHOT_REQUIRED');
    $sequence=static function(PDO $db):int{
      if($db->query("SELECT 1 FROM sqlite_master WHERE name='sqlite_sequence'")->fetchColumn()===false)return (int)$db->query('SELECT COALESCE(MAX(id),0) FROM inzet_review_decision_audit')->fetchColumn();
      return (int)$db->query("SELECT seq FROM sqlite_sequence WHERE name='inzet_review_decision_audit'")->fetchColumn();
    };
    if($sequence($locks[0])!==$sequence($locks[1])||!hash_equals($copyHash,hash_file('sha256',$copy)))throw new RuntimeException('ORIGINAL_CHANGED_SINCE_COPY');
    $code=file_get_contents($configPath);$newCode=self::patchedConfig($code,$config[self::KEY],$copy);$files=[];
    foreach($this->package['files'] as $relative=>$entry){$parent=realpath(dirname($root.'/'.$relative));if($parent===false||!self::inside($parent,$root)||is_link($root.'/'.$relative))throw new RuntimeException('RUNTIME_FILE_BOUNDARY_REQUIRED');$files[$relative]=['target'=>$root.'/'.$relative,'before'=>self::checksum($root.'/'.$relative),'after'=>$entry['sha256']];}
    $id=substr(hash('sha256',json_encode($this->package,JSON_THROW_ON_ERROR)),0,20);
    return ['base'=>$base,'root'=>$root,'web'=>$web,'config'=>$configPath,'config_before'=>hash('sha256',$code),'config_after'=>hash('sha256',$newCode),'config_new_code'=>$newCode,'router'=>$router,'router_hash'=>hash_file('sha256',$router),'files'=>$files,'locks'=>$locks,'snapshot'=>$base.'/review-test-rollout-'.$id,'source'=>$source,'source_hash'=>hash_file('sha256',$source),'original'=>$original,'original_hash'=>hash_file('sha256',$original),'copy'=>$copy,'copy_hash'=>$copyHash,'report'=>$report];
  }
  public function install(string $base,string $web,string $configPath,string $copyHash): array {
    $c=$this->check($base,$web,$configPath,$copyHash);if(is_dir($c['snapshot']))throw new RuntimeException('ROLLOUT_SNAPSHOT_ALREADY_EXISTS');if(!mkdir($c['snapshot'],0700))throw new RuntimeException('PRIVATE_BACKUP_DIRECTORY_REQUIRED');
    $manifest=$c;unset($manifest['locks'],$manifest['config_new_code'],$manifest['report']);$manifest['package']=$this->package;$manifest['state']='PREPARED';
    self::atomic($c['snapshot'].'/config.before.php',file_get_contents($c['config']));foreach($c['files'] as $relative=>$file)if($file['before']!==null)self::atomic($c['snapshot'].'/'.hash('sha256',$relative).'.before',file_get_contents($file['target']));
    $manifestPath=$c['snapshot'].'/manifest.json';self::atomic($manifestPath,json_encode($manifest,JSON_THROW_ON_ERROR));
    try{
      if(self::checksum($c['config'])!==$c['config_before']||self::checksum($c['router'])!==$c['router_hash'])throw new RuntimeException('RUNTIME_CHANGED_BEFORE_PUBLICATION');foreach($c['files'] as $file)if(self::checksum($file['target'])!==$file['before'])throw new RuntimeException('RUNTIME_CHANGED_BEFORE_PUBLICATION');
      self::atomic($c['files'][self::PAGE]['target'],self::MAINTENANCE);foreach($c['files'] as $relative=>$file)if($relative!==self::PAGE)self::atomic($file['target'],base64_decode($this->package['files'][$relative]['base64']));
      if($this->step!==null)($this->step)('private_files_published');
      if(self::checksum($c['config'])!==$c['config_before'])throw new RuntimeException('CONFIG_CHANGED_BEFORE_PUBLICATION');self::atomic($c['config'],$c['config_new_code']);if($this->step!==null)($this->step)('config_published');
      if(hash_file('sha256',$c['source'])!==$c['source_hash']||hash_file('sha256',$c['original'])!==$c['original_hash']||hash_file('sha256',$c['copy'])!==$c['copy_hash'])throw new RuntimeException('DATABASE_CHANGED_DURING_PUBLICATION');
      $manifest['state']='INSTALLED';self::atomic($manifestPath,json_encode($manifest,JSON_THROW_ON_ERROR));self::atomic($c['files'][self::PAGE]['target'],base64_decode($this->package['files'][self::PAGE]['base64']));
      return ['status'=>'OFFICE_TEST_REVIEW_ACTIVATED','runtime_files'=>5,'router_unchanged'=>true,'shared_dependencies_unchanged'=>true,'source_unchanged'=>true,'original_unchanged'=>true,'copy_unchanged'=>true,'snapshot_id'=>basename($c['snapshot']),'browser_acceptance'=>'REQUIRED'];
    }catch(Throwable $error){try{$this->rollback($base,$web,$manifestPath);}catch(Throwable){throw new RuntimeException('INSTALL_STOPPED_ROLLBACK_REVIEW_REQUIRED');}throw new RuntimeException('INSTALL_FAILED_AND_RESTORED');}
  }
  public function rollback(string $base,string $web,string $manifestPath): array {
    $base=realpath($base);$web=realpath($web);if($base===false||$web===false||$base===$web||self::inside($base,$web))throw new RuntimeException('TEST_BOUNDARY_REQUIRED');
    $manifestPath=self::existing($manifestPath,$base);$m=json_decode(file_get_contents($manifestPath),true,512,JSON_THROW_ON_ERROR);
    if($m['base']!==$base||$m['web']!==$web||$m['package']!==$this->package||!self::samePath(dirname($manifestPath),$m['snapshot']))throw new RuntimeException('ROLLBACK_MANIFEST_INVALID');if(self::checksum($m['router'])!==$m['router_hash'])throw new RuntimeException('ROUTER_CHANGED_AFTER_INSTALL');self::existing($m['config'],$base);
    if(!in_array(self::checksum($m['config']),[$m['config_before'],$m['config_after']],true))throw new RuntimeException('CONFIG_CHANGED_AFTER_INSTALL');$beforeCode=file_get_contents(dirname($manifestPath).'/config.before.php');if(hash('sha256',$beforeCode)!==$m['config_before'])throw new RuntimeException('BACKUP_HASH_INVALID');
    foreach($m['files'] as $relative=>$file){if($file['target']!==$m['root'].'/'.$relative||!self::inside($file['target'],$m['root'])||is_link($file['target']))throw new RuntimeException('ROLLBACK_FILE_BOUNDARY_REQUIRED');$allowed=[$file['before'],$file['after']];if($relative===self::PAGE)$allowed[]=hash('sha256',self::MAINTENANCE);if(!in_array(self::checksum($file['target']),$allowed,true))throw new RuntimeException('RUNTIME_CHANGED_AFTER_INSTALL');if($file['before']!==null&&self::checksum(dirname($manifestPath).'/'.hash('sha256',$relative).'.before')!==$file['before'])throw new RuntimeException('BACKUP_HASH_INVALID');}
    if(self::checksum($m['copy'])!==$m['copy_hash']||self::checksum($m['original'])!==$m['original_hash'])throw new RuntimeException('DECISIONS_CHANGED_AFTER_ACTIVATION_REVIEW_REQUIRED');
    self::atomic($m['files'][self::PAGE]['target'],self::MAINTENANCE);foreach($m['files'] as $relative=>$file)if($relative!==self::PAGE)$this->restoreFile($relative,$file,dirname($manifestPath));self::atomic($m['config'],$beforeCode);$this->restoreFile(self::PAGE,$m['files'][self::PAGE],dirname($manifestPath));$m['state']='ROLLED_BACK';self::atomic($manifestPath,json_encode($m,JSON_THROW_ON_ERROR));return ['status'=>'OFFICE_TEST_REVIEW_ROLLED_BACK','databases_retained'=>true];
  }
  private function restoreFile(string $relative,array $file,string $snapshot): void {if($file['before']===null){if(is_file($file['target']))unlink($file['target']);}else self::atomic($file['target'],file_get_contents($snapshot.'/'.hash('sha256',$relative).'.before'));}
  public static function cli(array $package): int {
    if(PHP_SAPI!=='cli'){http_response_code(404);return 1;}$o=getopt('',['check','install','rollback:','private-root:','document-root:','config:','copy-sha256:']);$base=$o['private-root']??'';$web=$o['document-root']??'';$config=$o['config']??'';$sha=$o['copy-sha256']??'';
    try{$self=new self($package);if(isset($o['rollback']))$result=$self->rollback($base,$web,$o['rollback']);elseif(isset($o['install']))$result=$self->install($base,$web,$config,$sha);else{$c=$self->check($base,$web,$config,$sha);$result=['status'=>'READY_FOR_OFFICE_TEST_REVIEW_INSTALL','runtime_files'=>5,'router_unchanged'=>true,'copy_verified'=>true,'database_writes'=>false];}echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";return 0;}
    catch(Throwable $error){$message=$error->getMessage();$code=preg_match('/^[A-Z_]+(?::[A-Za-z0-9_.]+)?$/D',$message)?$message:'DEPLOYMENT_INSPECTION_FAILED';echo json_encode(['status'=>'STOPPED','reason'=>$code],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";return 2;}
  }
}
