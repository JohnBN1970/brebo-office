<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Crm/OfficeCrmFunnel.php';
require_once dirname(__DIR__).'/src/Crm/OfficeCrmOpportunityPolicy.php';
require_once dirname(__DIR__).'/src/Crm/OfficeCrmOpportunityRepository.php';
require_once dirname(__DIR__).'/src/Crm/OfficeCrmRelationPolicy.php';
use Brebo\Office\Crm\OfficeCrmOpportunityPolicy as Policy;
use Brebo\Office\Crm\OfficeCrmOpportunityRepository as Repository;
use Brebo\Office\Crm\OfficeCrmRelationPolicy as RelationPolicy;
if (!extension_loaded('mbstring')) throw new RuntimeException('mbstring required');
$ok=static function(bool $condition,string $message):void {if(!$condition)throw new RuntimeException($message);};
$reject=static function(callable $f,string $message):void {try{$f();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Expected rejection: '.$message);};
$details=static fn(string $title,string $description=''):array=>Policy::details(['relation_id'=>'1','title'=>$title,'description'=>$description]);
$activity=static fn(string $title):array=>Policy::activity(['activity_type'=>'call','title'=>$title,'scheduled_at'=>'2026-11-12T09:30']);
foreach(['é','😀'] as $symbol) {
    $ok($details(str_repeat($symbol,255))['title']===str_repeat($symbol,255),'title exact boundary');
    $reject(fn()=>$details(str_repeat($symbol,256)),'title overflow');
    $ok($details('Ok',str_repeat($symbol,12000))['description']===str_repeat($symbol,12000),'description exact boundary');
    $reject(fn()=>$details('Ok',str_repeat($symbol,12001)),'description overflow');
    $ok($activity(str_repeat($symbol,255))['title']===str_repeat($symbol,255),'activity exact boundary');
    $reject(fn()=>$activity(str_repeat($symbol,256)),'activity overflow');
    $ok(Policy::transition('proposal','won',str_repeat($symbol,4000))['stage']==='won','note exact boundary');
    $reject(fn()=>Policy::transition('proposal','won',str_repeat($symbol,4001)),'note overflow');
    $ok(RelationPolicy::validate('organization',str_repeat($symbol,255),['prospect'])['display_name']===str_repeat($symbol,255),'relation exact boundary');
    $reject(fn()=>RelationPolicy::validate('organization',str_repeat($symbol,256),['prospect']),'relation overflow');
}
$reject(fn()=>$details("\xC3\x28"),'invalid UTF-8 title');
$reject(fn()=>Policy::transition('proposal','won',"\xC3\x28"),'invalid UTF-8 note');
$reject(fn()=>RelationPolicy::validate('organization',"\xC3\x28",['prospect']),'invalid UTF-8 relation');
if(!in_array('sqlite',PDO::getAvailableDrivers(),true))throw new RuntimeException('SQLite required');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_operator_accounts(id VARCHAR(128) PRIMARY KEY, active INTEGER, can_edit_crm INTEGER)');
$db->exec("INSERT INTO office_operator_accounts VALUES('editor',1,1)");
$db->exec('CREATE TABLE office_crm_opportunities(id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(255), stage VARCHAR(32), currency CHAR(3), description TEXT, source VARCHAR(128))');
$db->exec('CREATE TABLE office_crm_opportunity_activities(id INTEGER PRIMARY KEY AUTOINCREMENT, opportunity_id INTEGER, activity_type VARCHAR(32), title VARCHAR(255), created_by VARCHAR(128), request_key CHAR(32), request_hash CHAR(64), detail_json TEXT, UNIQUE(created_by,request_key))');
$repo=new Repository($db);
$key=0;
$create=static function(string $title,string $description,string $source)use($repo,&$key):int {
    ++$key;
    return $repo->createLead('editor',$title,$description,$source,str_pad(dechex($key),32,'0',STR_PAD_LEFT));
};
foreach(['é','😀'] as $symbol) {
    $id=$create(str_repeat($symbol,255),str_repeat($symbol,12000),str_repeat($symbol,128));
    $stmt=$db->prepare('SELECT title,description,source FROM office_crm_opportunities WHERE id=?');
    $stmt->execute([$id]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    $ok($row['title']===str_repeat($symbol,255)&&$row['description']===str_repeat($symbol,12000)&&$row['source']===str_repeat($symbol,128),'stored characters unchanged');
    $reject(fn()=>$create(str_repeat($symbol,256),'',''),'lead title overflow');
    $reject(fn()=>$create('OK',str_repeat($symbol,12001),''),'lead description overflow');
    $reject(fn()=>$create('OK','',str_repeat($symbol,129)),'lead source overflow');
}
$reject(fn()=>$create("\xC3\x28",'',''),'invalid UTF-8 lead');
$ok((int)$db->query('SELECT COUNT(*) FROM office_crm_opportunities')->fetchColumn()===2,'rejected inputs created no records');
echo "CRM_UTF8_VALIDATION_OK\n";
