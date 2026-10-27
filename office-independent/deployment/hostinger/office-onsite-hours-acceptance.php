<?php
declare(strict_types=1);
// Read-only installed-state checks plus rollback-only credential fixtures.
if(PHP_SAPI!=='cli')exit(1);
set_exception_handler(static function(Throwable $e):never{fwrite(STDERR,'ACCEPTANCE_STOP | '.get_class($e).' | '.substr($e->getMessage(),0,300).PHP_EOL);exit(1);});
$base='/home/u213420663/domains/brebobv.nl/office_calc_test';$config=require $base.'/config/config.php';
$root=realpath($config['OFFICE_PRIVATE_ROOT']??'');
if($root!==$base.'/release-office-test-20261018/private'||($config['OFFICE_BASE_PATH']??'')!=='/office-test'||($config['OFFICE_INTAKE_DB_USER']??'')!=='u213420663_office_test'||!preg_match('/^mysql:host=(?:localhost|127\.0\.0\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$config['OFFICE_INTAKE_PDO_DSN']??''))throw new RuntimeException('ISOLATION_REQUIRED');
$receipt=json_decode(file_get_contents($base.'/hours-approved-20261027-complete.json'),true,512,JSON_THROW_ON_ERROR);$fixture=$receipt['fixture'];
$manifest=json_decode(file_get_contents($receipt['backup'].'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
$db=new PDO($config['OFFICE_INTAKE_PDO_DSN'],$config['OFFICE_INTAKE_DB_USER'],$config['OFFICE_INTAKE_DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);$db->exec("SET time_zone='+00:00'");
foreach(['OnSiteHoursReviewReadiness','OnSiteSessionReviewReader','OnSitePresenceIntervalProjector','OnSiteDeviceTokenRepository','OnSiteBootstrapService','OnSiteBootstrapRepository','OnSiteHoursBootstrapProjection']as$name)require_once $root.'/src/Inzet/'.$name.'.php';
$checks=0;$check=static function(bool $condition,string $name)use(&$checks){if(!$condition)throw new RuntimeException('ACCEPTANCE_FAILED: '.$name);$checks++;echo 'PASS | '.$name.PHP_EOL;};
\Brebo\Office\Inzet\OnSiteHoursReviewReadiness::requireReady($db);$check(true,'installed schema readiness');
foreach($manifest['files']as$file)$check(hash_equals($file['new_sha256'],hash_file('sha256',$file['path'])),'installed source '.basename($file['path']));
$check(hash_file('sha256',$root.'/src/Inzet/OnSiteBootstrapService.php')==='decfe8d48b2c4bc647b46ec54a3abc96b56bcc37a77505426b5e4e4ef65325bd','original bootstrap service preserved');
$check(hash_file('sha256',$root.'/src/Inzet/OnSiteBootstrapRepository.php')==='d19e00673bed79aa9000e7070a4d0d2fceb4201491e0468d31d7bd79bb16204d','original project and zone authority preserved');
$q=$db->prepare('SELECT id,in_event_id,out_event_id,approval_status,reviewed_seconds,reason,reviewer_operator_id,request_key FROM office_onsite_hours_reviews WHERE person_id=?');$q->execute([$fixture['person_id']]);$reviews=$q->fetchAll(PDO::FETCH_ASSOC);
$check(count($reviews)===3,'exactly three synthetic reviews, no duplicate insert');
foreach(['approve'=>['approved',28800],'reject'=>['rejected',0],'correct'=>['approved',5400]]as$scenario=>[$status,$seconds]){$found=array_values(array_filter($reviews,static fn($row)=>$row['in_event_id']===$fixture['events'][$scenario]['in']));$check(count($found)===1&&$found[0]['approval_status']===$status&&(int)$found[0]['reviewed_seconds']===$seconds,'browser outcome '.$scenario);if($scenario!=='approve')$check(str_starts_with($found[0]['reason']??'','TEST - '),'audit reason '.$scenario);}
$denied=false;try{(new \Brebo\Office\Inzet\OnSiteSessionReviewReader($db))->forManager('TEST-NO-PROJECT-ACCESS',$fixture['project_id']);}catch(RuntimeException $e){$denied=$e->getMessage()==='hours_review_access_denied';}$check($denied,'unprivileged actor cannot read workbench');
require_once $root.'/src/Inzet/OnSiteSessionReviewWriter.php';
$approvedRow=array_values(array_filter($reviews,static fn($row)=>$row['in_event_id']===$fixture['events']['approve']['in']))[0];
$writer=new \Brebo\Office\Inzet\OnSiteSessionReviewWriter($db);
$retry=$writer->record($fixture['person_id'],$approvedRow['in_event_id'],$approvedRow['out_event_id'],'approved',null,$approvedRow['reviewer_operator_id'],null,$fixture['project_id'],$approvedRow['request_key']);
$check($retry['replayed']===true&&$retry['id']===(string)$approvedRow['id'],'exact browser approval retry reuses the same ledger receipt');
$conflict=false;try{$writer->record($fixture['person_id'],$approvedRow['in_event_id'],$approvedRow['out_event_id'],'rejected',null,$approvedRow['reviewer_operator_id'],'TEST - Conflicting retry',$fixture['project_id'],$approvedRow['request_key']);}catch(RuntimeException $e){$conflict=$e->getMessage()==='hours_review_request_conflict';}$check($conflict,'changed payload cannot reuse a completed request key');
$q->execute([$fixture['person_id']]);$check(count($q->fetchAll(PDO::FETCH_ASSOC))===3,'retry and conflict add no duplicate review');
$denied=false;try{(new \Brebo\Office\Inzet\OnSiteSessionReviewWriter($db))->record($fixture['person_id'],$fixture['events']['approve']['in'],$fixture['events']['approve']['out'],'approved',null,'TEST-NO-PROJECT-ACCESS',null,$fixture['project_id']);}catch(RuntimeException $e){$denied=$e->getMessage()==='hours_review_access_denied';}$check($denied,'unprivileged actor cannot write or retrieve a retry receipt');
require_once $root.'/src/Intake/WebsiteCrmOperatorContext.php';require_once $root.'/src/Inzet/OnSiteHoursReviewToken.php';
$actor=new \Brebo\Office\Intake\WebsiteCrmOperatorContext('TEST-actor','TEST-session',false);$ctx=[$fixture['project_id'],$fixture['person_id'],$fixture['events']['approve']['in'],$fixture['events']['approve']['out']];
$denied=false;try{\Brebo\Office\Inzet\OnSiteHoursReviewToken::requestKey('forged-token',$actor,$ctx,$config['OFFICE_TEST_ONSITE_HOURS_REVIEW_CSRF_SECRET'],time());}catch(InvalidArgumentException $e){$denied=true;}$check($denied,'unsigned review proof rejected');
$db->beginTransaction();
try{
 $device=bin2hex(random_bytes(32));$q=$db->prepare('INSERT INTO office_onsite_device_tokens (token_hash,person_id,expires_at,revoked_at) VALUES (?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 MINUTE),NULL)');$q->execute([hash('sha256',$device),$fixture['person_id']]);
 $bootstrap=new \Brebo\Office\Inzet\OnSiteBootstrapService(new \Brebo\Office\Inzet\OnSiteDeviceTokenRepository($db));$repository=new \Brebo\Office\Inzet\OnSiteBootstrapRepository($db);
 $original=$bootstrap->bootstrap($device,static fn(string $person):array=>$repository->forEmployee($person,true));
 $enriched=\Brebo\Office\Inzet\OnSiteHoursBootstrapProjection::enrich($original,$db,$device,true);
 $approved=(array)($enriched['body']['approved_events']??[]);
 $check($enriched['status']===200&&$enriched['body']['employee']['id']===$fixture['person_id'],'installed two-argument bootstrap and verified identity');
 $check($enriched['body']['projects']===$original['body']['projects'],'bootstrap projects/zones unchanged by approval enrichment');
 $expected=[$fixture['events']['approve']['in'],$fixture['events']['approve']['out'],$fixture['events']['correct']['in'],$fixture['events']['correct']['out']];sort($expected);$actual=array_keys($approved);sort($actual);$check($actual===$expected,'only approved evidence returned to its employee; rejection excluded');
 $db->prepare('INSERT INTO office_inzet_people (display_name,active) VALUES (?,1)')->execute(['TEST - Temporary isolation fixture']);$other=(string)$db->lastInsertId();$otherDevice=bin2hex(random_bytes(32));$q->execute([hash('sha256',$otherDevice),$other]);
 $otherOriginal=$bootstrap->bootstrap($otherDevice,static fn()=>[]);$otherResult=\Brebo\Office\Inzet\OnSiteHoursBootstrapProjection::enrich($otherOriginal,$db,$otherDevice,true);
 $check(json_encode($otherResult['body']['approved_events'])==='{}','another employee receives an empty object without leaked approvals');
 $mismatch=false;try{\Brebo\Office\Inzet\OnSiteHoursBootstrapProjection::enrich($original,$db,$otherDevice,true);}catch(RuntimeException $e){$mismatch=$e->getMessage()==='bootstrap_review_identity_mismatch';}$check($mismatch,'cross-employee bootstrap mismatch rejected');
}finally{if($db->inTransaction())$db->rollBack();}
$check((new \Brebo\Office\Inzet\OnSiteDeviceTokenRepository($db))->resolveEmployee($device)===null,'temporary device credentials rolled back');
echo 'INSTALLED_ACCEPTANCE_PASSED | '.$checks.' checks'.PHP_EOL;
