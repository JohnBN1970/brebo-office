<?php
declare(strict_types=1);
require __DIR__.'/fixtures/operational-review.php';
$top=sys_get_temp_dir().'/operational-route-'.bin2hex(random_bytes(8));
mkdir($top,0700);mkdir($top.'/private',0700);mkdir($top.'/public',0700);
try {
  $f=operationalFixture(new PDO('sqlite:'.$top.'/private/office.sqlite'),new PDO('sqlite:'.$top.'/private/operational.sqlite'));
  $map=['version'=>1,'mappings'=>[]];
  foreach ($f['people'] as $actor=>$person) $map['mappings'][]=['source_system'=>'office_operator','source_person_id'=>(string)$actor,'office_person_id'=>$person];
  file_put_contents($top.'/private/identities.json',json_encode($map,JSON_THROW_ON_ERROR));
  file_put_contents($top.'/private/source.sqlite','immutable source fixture');file_put_contents($top.'/private/decisions.sqlite','immutable decision fixture');
  $sourceHash=hash_file('sha256',$top.'/private/source.sqlite');$decisionHash=hash_file('sha256',$top.'/private/decisions.sqlite');
  $officeHash=hash_file('sha256',$top.'/private/office.sqlite');$initialHash=hash_file('sha256',$top.'/private/operational.sqlite');
  $wrapper=<<<'PHP'
<?php
$s=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
foreach($s['env'] as $key=>$value)putenv($key.'='.$value);
$_SERVER=$s['server'];$_GET=$s['get'];$_POST=$s['post'];$_COOKIE=['office_test_session'=>$s['token']];
register_shutdown_function(static function():void{fwrite(STDERR,'STATUS='.(http_response_code()?:200));});
require $s['endpoint'];
PHP;
  file_put_contents($top.'/wrapper.php',$wrapper);
  $run=static function(array $changes=[])use($top,$f):array {
    $s=['endpoint'=>dirname(__DIR__).'/public/office-inzet-operational-review.php','token'=>$f['tokens']['manager'],'get'=>[],'post'=>[],
      'server'=>['REQUEST_METHOD'=>'GET','HTTP_HOST'=>'office-test.example','HTTPS'=>'on','DOCUMENT_ROOT'=>$top.'/public','CONTENT_TYPE'=>'application/x-www-form-urlencoded','CONTENT_LENGTH'=>'1000','HTTP_ORIGIN'=>'https://office-test.example'],
      'env'=>['OFFICE_BASE_PATH'=>'/office-test','OFFICE_PUBLIC_HOST'=>'office-test.example','OFFICE_INTAKE_PDO_DSN'=>'sqlite:'.$top.'/private/office.sqlite','OFFICE_INTAKE_DB_USER'=>'','OFFICE_INTAKE_DB_PASSWORD'=>'',
        'OFFICE_TEST_INZET_OPERATIONAL_REVIEW_ENABLED'=>'enabled','OFFICE_TEST_INZET_OPERATIONAL_REVIEW_WRITE_ENABLED'=>'enabled','OFFICE_TEST_INZET_OPERATIONAL_SCHEMA_VERIFIED'=>'enabled','OFFICE_TEST_INZET_OPERATIONAL_IDENTITIES_VERIFIED'=>'enabled',
        'OFFICE_TEST_INZET_OPERATIONAL_DATA_ROOT'=>$top.'/private','OFFICE_TEST_INZET_OPERATIONAL_SQLITE_PATH'=>$top.'/private/operational.sqlite','OFFICE_TEST_INZET_OPERATIONAL_IDENTITY_MAP_PATH'=>$top.'/private/identities.json','OFFICE_TEST_INZET_OPERATIONAL_CSRF_SECRET'=>str_repeat('s',32),
        'OFFICE_TEST_INZET_SQLITE_PATH'=>$top.'/private/source.sqlite','OFFICE_TEST_INZET_REVIEW_DECISIONS_SQLITE_PATH'=>$top.'/private/decisions.sqlite']];
    $s=array_replace_recursive($s,$changes);file_put_contents($top.'/scenario.json',json_encode($s,JSON_THROW_ON_ERROR));
    $p=proc_open([PHP_BINARY,$top.'/wrapper.php',$top.'/scenario.json'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fclose($pipes[0]);$body=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);
    if($exit!==0 || !preg_match('/STATUS=(\d+)/',$err,$m))throw new RuntimeException('Endpoint process failed: '.$err.$body);
    return [(int)$m[1],$body];
  };
  [$status,$body]=$run();
  operationalAssert($status===200 && str_contains($body,'Accorderen') && !str_contains($body,'Project B'),'Authorized page rendered without cross-project data');
  preg_match_all('/<form method="post"[^>]*>(.*?)<\/form>/s',$body,$forms);
  $post=[];
  foreach($forms[1] as $form){if(!str_contains($form,'name="id" value="R1"'))continue;preg_match_all('/<input type="hidden" name="([^"]+)" value="([^"]*)">/',$form,$fields,PREG_SET_ORDER);foreach($fields as $field)$post[$field[1]]=html_entity_decode($field[2],ENT_QUOTES,'UTF-8');}
  operationalAssert(isset($post['csrf'],$post['snapshot']),'Actual rendered form supplies protected fields');
  $post+=['decision'=>'approved','reason'=>''];$request=['server'=>['REQUEST_METHOD'=>'POST'],'post'=>$post];
  operationalAssert($run(['token'=>'bad'])[0]===302,'Anonymous GET goes to login');
  operationalAssert($run(['token'=>$f['tokens']['viewer']])[0]===403,'Viewer denied');
  operationalAssert($run(['env'=>['OFFICE_BASE_PATH'=>'/office']])[0]===404,'Production mount denied');
  operationalAssert($run(['server'=>['REQUEST_METHOD'=>'DELETE']])[0]===405,'Unsupported method denied');
  operationalAssert($run(['env'=>['OFFICE_TEST_INZET_OPERATIONAL_REVIEW_ENABLED'=>'']])[0]===404,'Read feature flag required');
  operationalAssert($run(['env'=>['OFFICE_TEST_INZET_OPERATIONAL_IDENTITIES_VERIFIED'=>'']])[0]===503,'Verified mapping flag required');
  [$readOnlyStatus,$readOnlyBody]=$run(['env'=>['OFFICE_TEST_INZET_OPERATIONAL_REVIEW_WRITE_ENABLED'=>'']]);
  operationalAssert($readOnlyStatus===200 && !str_contains($readOnlyBody,'method="post"'),'Read-only mode has no write controls');
  foreach([
    ['token'=>'bad'],['token'=>$f['tokens']['viewer']],['token'=>$f['tokens']['worker']],
    ['post'=>['csrf'=>'wrong']],['post'=>['snapshot'=>str_repeat('0',64)]],
    ['server'=>['HTTP_ORIGIN'=>'https://other.example']],['server'=>['HTTP_ORIGIN'=>'null']],
    ['env'=>['OFFICE_TEST_INZET_OPERATIONAL_REVIEW_WRITE_ENABLED'=>'']],
  ] as $changes){$code=$run(array_replace_recursive($request,$changes))[0];operationalAssert(in_array($code,[401,403],true),'Unsafe POST rejected: '.$code);}
  operationalAssert($run(array_replace_recursive($request,['server'=>['CONTENT_LENGTH'=>'40000']]))[0]===400,'Oversized request denied');
  operationalAssert($run(array_replace_recursive($request,['post'=>['decision'=>'rejected']]))[0]===422,'Rejection needs explanation');
  operationalAssert($run(['env'=>['OFFICE_TEST_INZET_OPERATIONAL_SQLITE_PATH'=>$top.'/private/source.sqlite']])[0]===503,'Source alias refused');
  operationalAssert(hash_file('sha256',$top.'/private/operational.sqlite')===$initialHash,'GET and denied POST leave store unchanged');
  operationalAssert($run(array_replace_recursive($request,['server'=>['HTTP_ORIGIN'=>'null','HTTP_REFERER'=>'https://office-test.example/office-test/inzet/uren-beoordelen']]))[0]===303,'Opaque browser context requires exact same-origin referer and can save');
  [$status,$body]=$run(['get'=>['saved'=>'R1']]);
  operationalAssert($status===200 && str_contains($body,'Geaccordeerd') && str_contains($body,'Uren gecontroleerd') && str_contains($body,'De beoordeling is opgeslagen.'),'Saved state and reason appear after fresh GET');
  operationalAssert($run($request)[0]===409,'Stale browser replay cannot approve twice');
  operationalAssert((int)$f['db']->query("SELECT COUNT(*) FROM inzet_operational_audit WHERE entity_type='registration' AND entity_id='R1'")->fetchColumn()===2,'Exactly one new audit');
  operationalAssert(hash_file('sha256',$top.'/private/office.sqlite')===$officeHash,'Office authentication/ACL database unchanged');
  operationalAssert(hash_file('sha256',$top.'/private/source.sqlite')===$sourceHash && hash_file('sha256',$top.'/private/decisions.sqlite')===$decisionHash,'Import source and review decisions unchanged');
  echo "Operational endpoint: actual forms, auth, mount, flags, origin, CSRF/snapshot, read-only GET, atomic save, refresh and replay OK\n";
} finally {
  unset($run,$f);
  $real=realpath($top);$tmp=realpath(sys_get_temp_dir());
  if($real!==false && $tmp!==false && str_starts_with(str_replace('\\','/',$real),rtrim(str_replace('\\','/',$tmp),'/').'/operational-route-')){
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $file){if($file->isDir()&&!$file->isLink())rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($real);
  }
}
