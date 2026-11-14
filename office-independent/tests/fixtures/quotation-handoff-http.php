<?php
declare(strict_types=1);
if(!$mysql)throw new RuntimeException('HTTP fixture requires disposable MySQL');
$root=sys_get_temp_dir().'/quotation-handoff-'.bin2hex(random_bytes(6));mkdir($root,0700);mkdir($root.'/sessions',0700);
$port=random_int(21000,28000);$host='127.0.0.1:'.$port;
$router='<?php $_SERVER["HTTPS"]="on"; require '.var_export(dirname(__DIR__,2).'/public/office-quotation-handoff.php',true).';';file_put_contents($root.'/router.php',$router);
$token=str_repeat('t',40);$q=$db->prepare('INSERT INTO office_operator_sessions VALUES(?,?,?,NULL)');$q->execute([hash('sha256',$token),'7',gmdate('Y-m-d H:i:s',time()+3600)]);
$env=array_merge(getenv(),['OFFICE_BASE_PATH'=>'/office-test','OFFICE_QUOTATION_BUDGET_HANDOFF_ENABLED'=>'enabled','OFFICE_CRM_AUTH_MODE'=>'session_only','OFFICE_PUBLIC_HOST'=>$host,'OFFICE_INTAKE_PDO_DSN'=>'mysql:host=127.0.0.1;dbname=office_quotation_handoff_test','OFFICE_INTAKE_DB_USER'=>'root','OFFICE_INTAKE_DB_PASSWORD'=>'handoff-test-only']);
$pipes=[];$server=proc_open([PHP_BINARY,'-d','session.save_path='.$root.'/sessions','-S',$host,$root.'/router.php'],[['pipe','r'],['file',$root.'/server.log','a'],['file',$root.'/server.log','a']],$pipes,null,$env);if(!is_resource($server))throw new RuntimeException('HTTP server failed');fclose($pipes[0]);$cookies=[];
$request=static function(bool $signed,?array $post=null)use($host,$token,&$cookies):array{$ch=curl_init('http://'.$host.'/office-test/crm/offertes/overdracht');$headers=['Cookie: '.($signed?'office_test_session='.$token.'; ':'').implode('; ',$cookies)];$responseHeaders=[];curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>5,CURLOPT_HTTPHEADER=>$headers,CURLOPT_HEADERFUNCTION=>static function($ch,$line)use(&$cookies,&$responseHeaders){$responseHeaders[]=$line;if(preg_match('/^Set-Cookie:\s*([^;]+)/i',$line,$m)){$key=explode('=',$m[1],2)[0];$cookies[$key]=$m[1];}return strlen($line);}]);if($post!==null)curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);$body=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);return ['status'=>$status,'body'=>$body,'headers'=>$responseHeaders];};
try{
 for($i=0;$i<100;$i++){if($request(false)['status']>0)break;usleep(100000);}
 handoffAssert($request(false)['status']===302,'Anonymous GET not rejected');handoffAssert($request(false,[])['status']===403,'Anonymous POST not rejected');$page=$request(true);handoffAssert($page['status']===200,'Authorized GET failed');
 if(!preg_match('/name="csrf" value="([^"]+)"/',$page['body'],$m))throw new RuntimeException('CSRF missing');$csrf=$m[1];
 $post=['csrf'=>'wrong','quotation_id'=>$id,'quotation_version'=>1,'cost_hash'=>$hash,'evidence_document_id'=>51,'confirmed'=>'1'];handoffAssert($request(true,$post)['status']===403,'Wrong CSRF accepted');$post['csrf']=$csrf;
 $spoof=$post;$spoof['unit_price']='0';handoffAssert($request(true,$spoof)['status']===400,'Browser financial fields accepted');
 $stale=$post;$stale['quotation_version']=2;handoffAssert($request(true,$stale)['status']===409,'Stale HTTP version accepted');handoffAssert((int)$db->query('SELECT COUNT(*) FROM office_projects')->fetchColumn()===0,'Rejected HTTP writes created project');
 handoffAssert($request(true,$post)['status']===303,'HTTP acceptance and transfer failed');$page=$request(true);preg_match('/name="csrf" value="([^"]+)"/',$page['body'],$m);$post['csrf']=$m[1];$post['evidence_document_id']='';handoffAssert($request(true,$post)['status']===303,'Idempotent HTTP replay failed');
 echo "QUOTATION_BUDGET_AUTHENTICATED_HTTP=PASS\n";
}finally{proc_terminate($server);proc_close($server);$remove=static function(string$p)use(&$remove):void{foreach(scandir($p)as$n){if($n==='.'||$n==='..')continue;$c=$p.'/'.$n;if(is_dir($c))$remove($c);else unlink($c);}rmdir($p);};$remove($root);}
