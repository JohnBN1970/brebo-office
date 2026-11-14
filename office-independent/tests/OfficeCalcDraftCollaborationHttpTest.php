<?php
declare(strict_types=1);
// Disposable CI database only. This file never uses Office runtime credentials.
if(getenv('CALC_COLLAB_TEST_DATABASE')!=='office_calc_collaboration_test')throw new RuntimeException('Disposable collaboration database required');
$dsn='mysql:host=127.0.0.1;dbname=office_calc_collaboration_test;charset=utf8mb4';$password=getenv('CALC_COLLAB_TEST_PASSWORD')?:'';
$db=new PDO($dsn,'root',$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
foreach(['OfficeCalcCrmDraftLineRepository','OfficeCalcDraftCollaboration']as$class)require_once __DIR__.'/../src/Calculation/'.$class.'.php';
use Brebo\Office\Calculation\{OfficeCalcCrmDraftLineRepository,OfficeCalcDraftCollaboration,OfficeCalcDraftConflict};
putenv('OFFICE_CALC_DRAFT_COLLABORATION_ENABLED=enabled');$repo=new OfficeCalcCrmDraftLineRepository($db);$collab=new OfficeCalcDraftCollaboration($db);$rights=static fn(PDO $connection,int $actor,int $opportunity):bool=>$opportunity===11&&$collab->canEdit($actor,1);
if(($argv[1]??'')==='--worker'){
 try{$repo->save(1,(int)$argv[2],'shared','material','Concurrent writer '.$argv[2],1,2500,null,$rights,(string)$argv[3],'Parallel save');echo 'SAVED';}
 catch(OfficeCalcDraftConflict){echo 'CONFLICT';}exit;
}
foreach([
 'CREATE TABLE office_operator_accounts(id VARCHAR(128) PRIMARY KEY,username VARCHAR(190),active TINYINT,can_edit_crm TINYINT)',
 'CREATE TABLE office_operator_sessions(token_hash CHAR(64) PRIMARY KEY,operator_id VARCHAR(128),expires_at DATETIME,revoked_at DATETIME NULL)',
 'CREATE TABLE office_crm_opportunities(id BIGINT PRIMARY KEY,revision INT,stage VARCHAR(40),status VARCHAR(32))',
 'CREATE TABLE office_calc_crm_drafts(id BIGINT PRIMARY KEY,opportunity_id BIGINT,crm_revision INT,project_id BIGINT NULL,created_by BIGINT)',
 'CREATE TABLE calculations(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,office_project_id BIGINT NULL,office_calculation_id BIGINT UNIQUE,code VARCHAR(64) UNIQUE,title VARCHAR(255),status VARCHAR(32) DEFAULT \'draft\')',
 'CREATE TABLE calculation_versions(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,calculation_id BIGINT,version_no INT,status VARCHAR(32) DEFAULT \'draft\',direct_cost DECIMAL(19,4) DEFAULT 0)',
 'CREATE TABLE calculation_lines(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,version_id BIGINT,parent_id BIGINT NULL,sort_order INT,line_type VARCHAR(32),code VARCHAR(64),description VARCHAR(500),unit VARCHAR(32),quantity DECIMAL(19,4),labour_unit_cost DECIMAL(19,4) DEFAULT 0,material_unit_cost DECIMAL(19,4) DEFAULT 0,equipment_unit_cost DECIMAL(19,4) DEFAULT 0,subcontracting_unit_cost DECIMAL(19,4) DEFAULT 0,other_unit_cost DECIMAL(19,4) DEFAULT 0)'
]as$sql)$db->exec($sql);
$db->exec(file_get_contents(__DIR__.'/../deployment/hostinger/sql/office-calc-draft-collaboration.sql'));
$db->exec("INSERT INTO office_operator_accounts VALUES('7','Owner Fixture',1,1),('8','Editor Fixture',1,1),('9','Other Fixture',1,1); INSERT INTO office_crm_opportunities VALUES(11,4,'proposal','open'); INSERT INTO office_calc_crm_drafts VALUES(1,11,4,NULL,7)");
$tokens=['owner'=>str_repeat('a',64),'editor'=>str_repeat('b',64),'other'=>str_repeat('c',64)];$q=$db->prepare('INSERT INTO office_operator_sessions VALUES(?,?,?,NULL)');foreach(['owner'=>'7','editor'=>'8','other'=>'9']as$label=>$id)$q->execute([hash('sha256',$tokens[$label]),$id,gmdate('Y-m-d H:i:s',time()+3600)]);
$root=sys_get_temp_dir().'/office-calc-collab-http-'.bin2hex(random_bytes(6));mkdir($root,0700);mkdir($root.'/public',0700);
$page=realpath(__DIR__.'/../public/office-calc-crm-workbench.php');file_put_contents($root.'/public/router.php',"<?php \$_SERVER['HTTPS']='on'; require ".var_export($page,true).";");
$env=getenv();foreach(['OFFICE_BASE_PATH'=>'/office-test','OFFICE_TEST_CRM_ENABLED'=>'enabled','OFFICE_CRM_AUTH_MODE'=>'session_only','OFFICE_CALC_CRM_DRAFT_LAUNCH_ENABLED'=>'enabled','OFFICE_CALC_CRM_SCHEMA_VERIFIED'=>'enabled','OFFICE_CALC_DRAFT_COLLABORATION_ENABLED'=>'enabled','OFFICE_PUBLIC_HOST'=>'office-calc-collab.test','OFFICE_INTAKE_PDO_DSN'=>$dsn,'OFFICE_INTAKE_DB_USER'=>'root','OFFICE_INTAKE_DB_PASSWORD'=>$password]as$key=>$value)$env[$key]=$value;
$port=random_int(18000,23000);$pipes=[];$server=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',$root.'/public',$root.'/public/router.php'],[['pipe','r'],['file',$root.'/server.log','a'],['file',$root.'/server.log','a']],$pipes,null,$env);if(!is_resource($server))throw new RuntimeException('HTTP fixture server failed');
$csrfCookies=[];
$request=static function(?string $actor,array $post=[],string $query='draft_id=1')use($port,$tokens,&$csrfCookies):array{
 $headers=['Host: office-calc-collab.test'];if($actor!==null){$headers[]='Cookie: office_test_session='.$tokens[$actor].(isset($csrfCookies[$actor])?'; office_project_csrf='.$csrfCookies[$actor]:'');}
 $c=curl_init('http://127.0.0.1:'.$port.'/office-test/calculatie?'.$query);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HEADERFUNCTION=>static function($handle,string $line)use($actor,&$csrfCookies):int{if($actor!==null&&preg_match('/^Set-Cookie: office_project_csrf=([^;]+)/i',$line,$m))$csrfCookies[$actor]=$m[1];return strlen($line);}]);
 if($post!==[])curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);$body=curl_exec($c);$status=(int)curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);return ['status'=>$status,'body'=>(string)$body];
};
$expect=static function(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);};$hidden=static function(string $body,string $name):string{if(!preg_match('/name="'.preg_quote($name,'/').'" value="([^"]*)"/',$body,$m))throw new RuntimeException('Missing form field '.$name);return html_entity_decode($m[1],ENT_QUOTES,'UTF-8');};
try{
 for($i=0;$i<100;$i++){if($request(null)['status']>0)break;usleep(100000);}
 $expect($request(null)['status']===403,'Anonymous denied');$first=$request('owner');$expect($first['status']===200,'Owner workspace');$csrf=$hidden($first['body'],'csrf');$revision=$hidden($first['body'],'expected_revision');
 $expect($request('editor')['status']===403,'No implicit editor rights');
 $expect($request('owner',['draft_id'=>1,'csrf'=>'wrong','action'=>'grant','operator_id'=>8,'expected_revision'=>$revision])['status']===403,'CSRF denied');
 $expect($request('owner',['draft_id'=>1,'csrf'=>$csrf,'action'=>'grant','operator_id'=>8,'expected_revision'=>$revision])['status']===303,'Owner grants editor');
 $owner=$request('owner');$editor=$request('editor');$expect($editor['status']===200,'Explicit editor workspace');$shared=$hidden($owner['body'],'expected_revision');
 $post=['draft_id'=>1,'csrf'=>$csrf,'action'=>'save_line','expected_revision'=>$shared,'line_id'=>'shared','description'=>'Montage','cost_type'=>'labor','quantity'=>'2','unit'=>'uur','unit_price_eur'=>'45,00','reason'=>'Basis montage'];
 $expect($request('owner',$post)['status']===303,'Owner save');
 $post['csrf']=$hidden($editor['body'],'csrf');$post['description']='Eigen materiaalpost';$post['unit_price_eur']='0,4050';$post['quantity']='12.5';$post['cost_type']='material';$post['unit']='st';
 $stale=$request('editor',$post);$expect($stale['status']===409&&str_contains($stale['body'],'value="Eigen materiaalpost"')&&str_contains($stale['body'],'value="0,4050"'),'Stale write retains own form values');
 $post['expected_revision']=$hidden($stale['body'],'expected_revision');$expect($request('editor',$post)['status']===303,'Explicit reviewed resave');
 $row=$db->query("SELECT quantity,material_unit_cost,unit FROM calculation_lines WHERE code='M:shared'")->fetch(PDO::FETCH_ASSOC);$expect($row['quantity']==='12.5000'&&$row['material_unit_cost']==='0.4050'&&$row['unit']==='st','Four-decimal prices and quantities persisted');
 $editor=$request('editor');$expect($request('editor',['draft_id'=>1,'csrf'=>$post['csrf'],'action'=>'checkpoint','expected_revision'=>$hidden($editor['body'],'expected_revision'),'reason'=>'Review kozijnstaat'])['status']===303,'Immutable checkpoint');$event=(int)$db->query("SELECT id FROM office_calc_crm_draft_changes WHERE event_kind='revision_saved'")->fetchColumn();
 $detail=$request('owner',[],'draft_id=1&change_id='.$event);$expect($detail['status']===200&&str_contains($detail['body'],'Editor Fixture')&&str_contains($detail['body'],'Eenheidskosten was'),'Author and checkpoint details rendered');
 // Two independent worker processes contend on the actual MySQL draft row lock.
 $fresh=$collab->read(1,7,$rights,$repo);$workers=[];foreach([7,8]as$id){$out=[];$proc=proc_open([PHP_BINARY,__FILE__,'--worker',(string)$id,$fresh['token']],[['pipe','r'],['pipe','w'],['pipe','w']],$out);if(!is_resource($proc))throw new RuntimeException('Worker failed');fclose($out[0]);$workers[]=[$proc,$out];}
 $results=[];foreach($workers as[$proc,$out]){$results[]=trim(stream_get_contents($out[1]));$error=stream_get_contents($out[2]);fclose($out[1]);fclose($out[2]);if(proc_close($proc)!==0)throw new RuntimeException('Concurrent worker failed');}sort($results);$expect($results===['CONFLICT','SAVED'],'Exactly one concurrent writer wins');
 $owner=$request('owner');$expect($request('owner',['draft_id'=>1,'csrf'=>$csrf,'action'=>'remove','operator_id'=>8,'expected_revision'=>$hidden($owner['body'],'expected_revision')])['status']===303,'Revoke editor');$expect($request('editor')['status']===403,'Revocation effective immediately');
 $expect($request('other',[],'draft_id=1&change_id='.$event)['status']===403,'History cannot leak to nonmember');
 echo "OFFICE_CALC_COLLAB_HTTP_MYSQL=PASS\nOFFICE_CALC_COLLAB_TWO_WRITERS=PASS\n";
}finally{
 proc_terminate($server);proc_close($server);$remove=static function(string $path)use(&$remove):void{foreach(scandir($path)?:[]as$name){if($name==='.'||$name==='..')continue;$child=$path.'/'.$name;if(is_dir($child))$remove($child);else unlink($child);}rmdir($path);};$remove($root);
}
