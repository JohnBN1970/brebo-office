<?php
declare(strict_types=1);
foreach (['Intake/WebsiteCrmOperatorContext','Buildings/OfficeBuildingZoneRepository',
  'Inzet/OnSiteOfficeCircleCreateService','Inzet/OnSiteOfficeCircleUpdateService',
  'Inzet/OnSiteOfficeZoneFormToken','Inzet/OnSiteOfficeZonePostAction',
  'Inzet/OnSiteOfficeZoneUpdatePostAction'] as $class) require_once dirname(__DIR__).'/src/'.$class.'.php';
use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use Brebo\Office\Inzet\OnSiteOfficeZoneFormToken as Token;
use Brebo\Office\Inzet\OnSiteOfficeZonePostAction as CreateAction;
use Brebo\Office\Inzet\OnSiteOfficeCircleCreateService as CreateService;
use Brebo\Office\Inzet\OnSiteOfficeZoneUpdatePostAction as UpdateAction;
use Brebo\Office\Inzet\OnSiteOfficeCircleUpdateService as UpdateService;
function boundary(bool $ok,string $label): void {
  if (!$ok) throw new RuntimeException($label);
  echo "PASS: $label\n";
}
function boundaryReject(callable $run,string $message): bool {
  try { $run(); } catch (Throwable $e) { return $e->getMessage()===$message; }
  return false;
}
$db = new class extends PDO {
  public function __construct() {}
  public function inTransaction(): bool { throw new RuntimeException('database_reached'); }
};
$actor=new WebsiteCrmOperatorContext('manager-uuid','session-a',false);
$secret=str_repeat('s',32); $now=1793100000;
$token=Token::issue($actor,'17','1',$secret,$now);
boundary(strlen(Token::requestKey($token,$actor,'17','1',$secret,$now))===64,'valid token yields stable request identity');
foreach ([['other actor',new WebsiteCrmOperatorContext('other','session-a',false),'17','1',$now],
  ['other session',new WebsiteCrmOperatorContext('manager-uuid','session-b',false),'17','1',$now],
  ['other project',$actor,'18','1',$now],['other building',$actor,'17','2',$now],
  ['expired token',$actor,'17','1',$now+1800]] as [$label,$who,$project,$building,$time]) {
  boundary(boundaryReject(fn()=>Token::requestKey($token,$who,$project,$building,$secret,$time),'invalid_zone_form_token'),$label.' rejected');
}
$create=new CreateAction(new CreateService($db));
$post=['project_id'=>'17','building_id'=>'1','zone_name'=>'Test','latitude'=>'51.5','longitude'=>'4.3','radius_metres'=>'155.25','form_token'=>$token];
$run=fn(string $body,string $type='application/x-www-form-urlencoded')=>$create->handle('POST','brebobv.nl','brebobv.nl','https://brebobv.nl',$type,$body,$actor,$secret,$now);
$body=http_build_query($post);
boundary(boundaryReject(fn()=>$run($body),'database_reached'),'valid signed create reaches repository transaction');
boundary(boundaryReject(fn()=>$run($body.'&building_id=2'),'invalid_zone_form_fields'),'duplicate create field rejected before database');
boundary(boundaryReject(fn()=>$run($body.'&operator_id=other'),'invalid_zone_form_fields'),'operator injection rejected before database');
boundary(boundaryReject(fn()=>$run(str_repeat('a',4097)),'zone_payload_too_large'),'oversized create rejected before database');
boundary(boundaryReject(fn()=>$run($body,'application/json'),'zone_media_type_unsupported'),'JSON create rejected before database');
boundary(boundaryReject(fn()=>$run(http_build_query(array_replace($post,['form_token'=>'forged']))),'invalid_zone_form_token'),'forged create token rejected before database');
$update=new UpdateAction(new UpdateService($db));
$edit=$post+['zone_id'=>'1','revision'=>'1'];
$editBody=http_build_query($edit);
boundary(boundaryReject(fn()=>$update->handle('application/x-www-form-urlencoded',$editBody,$actor,$secret,$now),'database_reached'),'valid signed edit reaches repository transaction');
boundary(boundaryReject(fn()=>$update->handle('application/x-www-form-urlencoded',$editBody.'&revision=2',$actor,$secret,$now),'invalid_zone_update_fields'),'duplicate revision rejected before database');
boundary(boundaryReject(fn()=>$update->handle('application/x-www-form-urlencoded',http_build_query(array_replace($edit,['form_token'=>'forged'])),$actor,$secret,$now),'invalid_zone_form_token'),'forged edit token rejected before database');
