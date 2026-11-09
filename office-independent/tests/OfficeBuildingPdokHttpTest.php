<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingPdokSchemaMigration.php';
use Brebo\Office\Buildings\OfficeBuildingPdokSchemaMigration as Migration;
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$dsn=getenv('OFFICE_PDOK_TEST_DSN');
if ($dsn!=='mysql:host=127.0.0.1;port=3306;dbname=office_pdok_ci;charset=utf8mb4') throw new RuntimeException('isolated_ci_database_required');
$db=new PDO($dsn,'root','test-root-only',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('ALTER TABLE office_operator_accounts ADD COLUMN can_edit_crm TINYINT DEFAULT 0');
$db->exec('CREATE TABLE office_operator_sessions (token_hash CHAR(64) PRIMARY KEY, operator_id VARCHAR(128), expires_at DATETIME, revoked_at DATETIME NULL)');
$secret=str_repeat('a',64); $reader=str_repeat('b',64);
$insert=$db->prepare('INSERT INTO office_operator_sessions VALUES (?, ?, ?, NULL)');
$insert->execute([hash('sha256',$secret),'manager',gmdate('Y-m-d H:i:s',time()+3600)]);
$insert->execute([hash('sha256',$reader),'reader',gmdate('Y-m-d H:i:s',time()+3600)]);
$tmp=sys_get_temp_dir().'/pdok-http-'.bin2hex(random_bytes(8));
mkdir($tmp,0700);
$page=dirname(__DIR__).'/public/office-buildings.php';
// Simulate TLS termination only inside the disposable CI router. No application
// bypass or test hook is introduced in the real route.
$router='<?php $_SERVER["HTTPS"]="on"; putenv("OFFICE_BUILDING_PDOK_SAVE_ENABLED=". (is_file('.var_export($tmp.'/enabled',true).') ? "enabled" : "")); require '.var_export($page,true).';';
file_put_contents($tmp.'/router.php',$router);
$socket=stream_socket_server('tcp://127.0.0.1:0',$error,$message);
check(is_resource($socket),'test port unavailable');
$address=stream_socket_get_name($socket,false); fclose($socket);
$environment=array_merge(getenv(),[
  'OFFICE_NATIVE_PROJECTS_ENABLED'=>'enabled','OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED'=>'enabled',
  'OFFICE_PUBLIC_HOST'=>$address,'OFFICE_BASE_PATH'=>'/office-test',
  'OFFICE_INTAKE_PDO_DSN'=>$dsn,'OFFICE_INTAKE_DB_USER'=>'root','OFFICE_INTAKE_DB_PASSWORD'=>'test-root-only',
]);
$process=proc_open([PHP_BINARY,'-S',$address,$tmp.'/router.php'],[0=>['pipe','r'],1=>['file',$tmp.'/server.log','a'],2=>['file',$tmp.'/server.log','a']],$pipes,null,$environment);
check(is_resource($process),'test server failed');
try {
  for ($i=0;$i<100;$i++) {
    $probe=@stream_socket_client('tcp://'.$address,$error,$message,0.1);
    if (is_resource($probe)) { fclose($probe); break; }
    usleep(50000);
  }
  $request=static function(string $path, string $cookie, string $method='GET', array $post=[]) use ($address): array {
    $headers="Cookie: office_test_session=$cookie\r\nOrigin: https://$address\r\nContent-Type: application/x-www-form-urlencoded\r\n";
    $context=stream_context_create(['http'=>['method'=>$method,'header'=>$headers,'content'=>http_build_query($post),'ignore_errors'=>true,'follow_location'=>0,'timeout'=>10]]);
    $body=file_get_contents('http://'.$address.'/office-test/gebouwen'.$path,false,$context);
    preg_match('/ ([0-9]{3}) /',$http_response_header[0]??'',$m);
    return [(int)($m[1]??0),$body];
  };
  [$code,$body]=$request('',$secret);
  check($code===200 && str_contains($body,'Existing A'),'listing broken');
  [$code,$body]=$request('?id=1',$secret);
  check($code===200 && str_contains($body,'Actieve zones</dt><dd>4</dd>') && str_contains($body,'0363200003761447'),'detail/zone or saved location broken');
  [$code]=$request('?id=1',$secret,'POST',['action'=>'save_bag']);
  check($code===403,'default-off POST accepted');
  file_put_contents($tmp.'/enabled','enabled');
  [$code]=$request('?id=1',$secret,'POST',['action'=>'save_bag','csrf'=>'bad','pdok_id'=>'adr-fixture']);
  check($code===403,'route accepted invalid CSRF');
  [$code]=$request('?id=1',$reader,'POST',['action'=>'save_bag']);
  check($code===403,'route accepted reader write');
  [$code]=$request('?id=2',$secret);
  check($code===404,'unlinked building visible');
  // Only disposable CI schema is changed to reproduce Office-test before DDL.
  $db->exec('ALTER TABLE office_buildings DROP COLUMN latitude, DROP COLUMN longitude, DROP COLUMN bag_id');
  [$code,$body]=$request('?id=1&pdok_q=x',$secret);
  check($code===200 && str_contains($body,'latitude, longitude, bag_id') && str_contains($body,'Actieve zones</dt><dd>4</dd>'),'pre-migration read fallback broken');
  check(!str_contains($body,'Deze BAG-locatie gebruiken'),'unready schema offers write');
  Migration::apply($db);
  [$code,$body]=$request('?id=1',$reader);
  check($code===200 && str_contains($body,'Actieve zones</dt><dd>4</dd>'),'reader access lost after migration');
  echo "PDOK_HTTP_READ_AND_FAIL_CLOSED_OK\n";
} finally {
  proc_terminate($process); proc_close($process);
  foreach (glob($tmp.'/*') as $file) unlink($file);
  rmdir($tmp);
}
