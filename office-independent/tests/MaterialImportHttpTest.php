<?php
declare(strict_types=1);
// Explicit disposable CI database. Never use application runtime credentials here.
if(getenv('MATERIAL_TEST_DATABASE')!=='office_material_import_test')throw new RuntimeException('Disposable test database required');
$dsn='mysql:host=127.0.0.1;dbname=office_material_import_test;charset=utf8mb4';
$db=new PDO($dsn,'root',getenv('MATERIAL_TEST_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
foreach([
 'CREATE TABLE office_operator_accounts(id VARCHAR(128) PRIMARY KEY,username VARCHAR(190),can_edit_crm TINYINT,active TINYINT)',
 'CREATE TABLE office_operator_sessions(token_hash CHAR(64) PRIMARY KEY,operator_id VARCHAR(128),expires_at DATETIME,revoked_at DATETIME NULL)',
 'CREATE TABLE office_module_permissions(operator_id VARCHAR(128),module VARCHAR(32),permission VARCHAR(32))',
 'CREATE TABLE brebo_supplier(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(255),active TINYINT)',
 'CREATE TABLE brebo_article(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,code VARCHAR(64) UNIQUE,description VARCHAR(512),search_text TEXT,base_unit VARCHAR(16),cost_category VARCHAR(32),active TINYINT,created INT,changed INT)',
 'CREATE TABLE brebo_supplier_article(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,article_id INT,supplier_id INT,supplier_article_no VARCHAR(128),order_unit VARCHAR(16),use_unit VARCHAR(16),conversion_factor DECIMAL(18,6),minimum_order DECIMAL(18,4),active TINYINT,UNIQUE KEY supplier_code(supplier_id,supplier_article_no))',
 'CREATE TABLE brebo_catalog_import(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,supplier_id INT,standard VARCHAR(32),source_name VARCHAR(255),source_hash CHAR(64) UNIQUE,price_date VARCHAR(10),status VARCHAR(32),record_count INT,created INT)',
 'CREATE TABLE brebo_article_price(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,supplier_article_id INT,catalog_import_id INT,valid_from VARCHAR(10),valid_until VARCHAR(10) NULL,quantity_from DECIMAL(18,4),net_price DECIMAL(18,4),currency CHAR(3))'
]as$sql)$db->exec($sql);
$db->exec(file_get_contents(__DIR__.'/../deployment/hostinger/sql/material-import-review-ledger.sql'));
require_once __DIR__.'/../src/Documents/OfficeDocumentRegistry.php';
foreach(\Brebo\Office\Documents\OfficeDocumentRegistry::schema()as$sql)$db->exec($sql);
$db->exec("INSERT INTO office_operator_accounts VALUES('alice','alice-test',0,1),('bob','bob-test',0,1); INSERT INTO office_module_permissions VALUES('alice','materials','read'),('alice','materials','import'),('bob','materials','read'); INSERT INTO brebo_supplier(name,active)VALUES('Leverancier A',1)");
$alice=str_repeat('a',64);$bob=str_repeat('b',64);$q=$db->prepare('INSERT INTO office_operator_sessions VALUES(?,?,?,NULL)');
foreach(['alice'=>$alice,'bob'=>$bob]as$id=>$token)$q->execute([hash('sha256',$token),$id,gmdate('Y-m-d H:i:s',time()+3600)]);
$root=sys_get_temp_dir().'/office-material-http-'.bin2hex(random_bytes(8));mkdir($root,0700);mkdir($root.'/public',0700);mkdir($root.'/storage',0700);
$page=realpath(__DIR__.'/../public/office-materials.php');
file_put_contents($root.'/public/router.php',"<?php \$_SERVER['HTTPS']='on'; require ".var_export($page,true).";");
$env=getenv();foreach(['OFFICE_BASE_PATH'=>'/office-test','OFFICE_MATERIALS_ENABLED'=>'enabled','OFFICE_PUBLIC_HOST'=>'office-materials.test','OFFICE_INTAKE_PDO_DSN'=>$dsn,'OFFICE_INTAKE_DB_USER'=>'root','OFFICE_INTAKE_DB_PASSWORD'=>getenv('MATERIAL_TEST_PASSWORD')?:'','OFFICE_MATERIALS_CANONICAL_SCHEMA_VERIFIED'=>'enabled','OFFICE_MATERIALS_CANONICAL_PDO_DSN'=>$dsn,'OFFICE_MATERIALS_CANONICAL_DB_USER'=>'root','OFFICE_MATERIALS_CANONICAL_DB_PASSWORD'=>getenv('MATERIAL_TEST_PASSWORD')?:'','OFFICE_DOCUMENT_STORAGE_ROOT'=>$root.'/storage']as$key=>$value)$env[$key]=$value;
putenv('OFFICE_BASE_PATH=/office-test');require_once __DIR__.'/../src/Intake/OfficeMount.php';$sessionName=\Brebo\Office\Intake\OfficeMount::cookieName('session');
$port=random_int(18000,22000);$pipes=[];$process=proc_open([PHP_BINARY,'-S','127.0.0.1:'.$port,'-t',$root.'/public',$root.'/public/router.php'],[['pipe','r'],['file',$root.'/server.log','a'],['file',$root.'/server.log','a']],$pipes,null,$env);
if(!is_resource($process))throw new RuntimeException('PHP test server could not start');
$cookies=[];
$request=static function(?string $token,array $post=[])use($port,$sessionName,&$cookies):array{
 $headers=['Host: office-materials.test'];$jar=$cookies;if($token!==null)$jar[$sessionName]=$token;
 if($jar!==[])$headers[]='Cookie: '.implode('; ',array_map(static fn(string $k,string $v):string=>$k.'='.$v,array_keys($jar),array_values($jar)));
 $curl=curl_init('http://127.0.0.1:'.$port.'/office-test/materialen');
 curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HEADERFUNCTION=>static function($handle,string $line)use(&$cookies):int{if(preg_match('/^Set-Cookie:\s*([^=;]+)=([^;]*)/i',$line,$m))$cookies[$m[1]]=$m[2];return strlen($line);}]);
 if($post!==[])curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);
 $body=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);return ['status'=>$status,'body'=>(string)$body];
};
$expect=static function(bool $yes,string $message):void{if(!$yes)throw new RuntimeException($message);};
$hidden=static function(string $body,string $name):string{if(!preg_match('/name="'.preg_quote($name,'/').'" value="([^"]+)"/',$body,$m))throw new RuntimeException('Hidden field not found: '.$name);return html_entity_decode($m[1],ENT_QUOTES,'UTF-8');};
try{
 for($i=0;$i<100;$i++){if($request(null)['status']>0)break;usleep(100000);}
 $expect($request(null)['status']===302,'anonymous request must redirect to Office login');
 $first=$request($alice);$expect($first['status']===200,'authorized import screen');$csrf=$hidden($first['body'],'csrf');
 $expect($request($alice,['action'=>'preview','csrf'=>'wrong'])['status']===403,'invalid CSRF rejected');
 $preview=$request($alice,['action'=>'preview','csrf'=>$csrf,'source_mode'=>'text','source_text'=>"Leverancier;Artikelcode;Omschrijving;Prijs;Eenheid\nLeverancier A;000123;Schroeven;45,00;st"]);
 $expect($preview['status']===200&&str_contains($preview['body'],'000123'),'text becomes editable review');$review=$hidden($preview['body'],'review_token');
 $publish=['action'=>'publish','csrf'=>$csrf,'review_token'=>$review,'visible_rows'=>'0','supplier_id'=>'1','currency'=>'EUR','price_date'=>'2026-11-13','number_format'=>'nl','price_is_ex_vat'=>'1','rows'=>[['selected'=>'1','supplier'=>'Leverancier A','article_code'=>'000123','description'=>'Schroeven','price'=>'45,00','price_quantity'=>'100','unit'=>'st','discount_percentage'=>'10']]];
 $saved=$request($alice,$publish);$expect($saved['status']===200&&str_contains($saved['body'],'Materialen en prijsversies opgeslagen'),'review published');
 $expect((string)$db->query('SELECT net_price FROM brebo_article_price')->fetchColumn()==='0.4050','normalized price persisted through real HTTP route');
 $expect(str_contains($saved['body'],'0,4050')&&str_contains($saved['body'],'alice-test'),'saved price and approving person visible in shared catalogue');
 $expect((string)$db->query('SELECT actor_id FROM brebo_material_import_reviews')->fetchColumn()==='alice','authenticated author persisted');
 $expect((int)$db->query('SELECT COUNT(*) FROM office_document_originals')->fetchColumn()===1,'one immutable original');
 $repeated=$request($alice,$publish);$expect($repeated['status']===422,'consumed review cannot be resubmitted');$expect((int)$db->query('SELECT COUNT(*) FROM brebo_article_price')->fetchColumn()===1,'HTTP retry cannot duplicate prices');
 $bobPage=$request($bob);$expect($bobPage['status']===200,'read-only user can open screen');$expect($request($bob,['action'=>'preview','csrf'=>$csrf,'source_mode'=>'text','source_text'=>'x'])['status']===403,'read-only user cannot import');
 echo "MATERIAL_HTTP_MYSQL=PASS\n";
}finally{
 proc_terminate($process);proc_close($process);
 $remove=static function(string $path)use(&$remove):void{foreach(scandir($path)?:[]as$name){if($name==='.'||$name==='..')continue;$child=$path.'/'.$name;if(is_dir($child))$remove($child);else unlink($child);}rmdir($path);};$remove($root);
}
