<?php
declare(strict_types=1);

// Dedicated, empty CI database. No server data or credentials are imported.
$dsn = getenv('OFFICE_ONSITE_FLOW_CI_DSN');
if ($dsn !== 'mysql:host=127.0.0.1;port=3306;dbname=office_onsite_flow_ci;charset=utf8mb4') {
  throw new RuntimeException('Dedicated flow CI database required');
}
spl_autoload_register(static function (string $class): void {
  $prefix = 'Brebo\\Office\\';
  if (str_starts_with($class, $prefix)) {
    $file = dirname(__DIR__).'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if (is_file($file)) require_once $file;
  }
});
use Brebo\Office\Inzet\{OfficeInzetSchema,OfficeInzetIdentityMappingGuard,OnSiteActivationIdentityBridge,
  OnSiteActivationInvitationSchema,OnSiteActivationInvitationIssuer,OnSiteOfficeInvitationAction,
  OnSiteActivationService,OnSiteDeviceTokenRepository,OnSiteDeviceTokenSchema,OnSiteEventInboxSchema,
  OnSitePresenceApplication,OnSiteBootstrapService,OnSiteBootstrapRepository,OnSiteBuildingZoneAccess,
  OnSitePresenceReviewRepository,OnSiteOfficeDeviceRevocationAction};
use Brebo\Office\Buildings\{OfficeBuildingSchema,OfficeBuildingZoneSchema,OfficeBuildingZoneRepository};
$db = new PDO($dsn,(string)getenv('OFFICE_INZET_TEST_USER'),(string)getenv('OFFICE_INZET_TEST_PASSWORD'),[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false
]);
if (($argv[1] ?? '') === '--redeem') {
  $token = trim((string)stream_get_contents(STDIN));
  $result = (new OnSiteActivationService($db,new OnSiteDeviceTokenRepository($db)))->activate($token);
  echo json_encode(['status'=>$result['status']],JSON_THROW_ON_ERROR);
  exit;
}
$assert = static function (bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); };
$denied = static function (Closure $call): void {
  try { $call(); } catch (RuntimeException|InvalidArgumentException $e) { return; }
  throw new RuntimeException('Unauthorized operation succeeded');
};
$db->exec("CREATE TABLE office_operator_accounts(id VARCHAR(128) PRIMARY KEY,active TINYINT NOT NULL) ENGINE=InnoDB");
$db->exec("CREATE TABLE office_projects(id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB");
$db->exec("CREATE TABLE office_project_memberships(project_id BIGINT UNSIGNED,operator_id VARCHAR(128),permission VARCHAR(16),PRIMARY KEY(project_id,operator_id)) ENGINE=InnoDB");
foreach ([OfficeInzetSchema::class,OfficeBuildingSchema::class,OfficeBuildingZoneSchema::class,
  OnSiteDeviceTokenSchema::class,OnSiteEventInboxSchema::class,OnSiteActivationInvitationSchema::class] as $schema) {
  foreach ($schema::statements() as $sql) $db->exec($sql);
}
$db->exec("INSERT INTO office_operator_accounts VALUES ('john:office',1),('reader',1),('inactive',0)");
$db->exec("INSERT INTO office_projects VALUES (17),(18)");
$db->exec("INSERT INTO office_project_memberships VALUES (17,'john:office','manage'),(17,'reader','read'),(17,'inactive','manage')");
$db->exec("INSERT INTO office_inzet_people(id,display_name,active) VALUES (101,'Fictieve medewerker',1),(42,'Andere medewerker',1),(102,'Inactief',0)");
$db->exec("INSERT INTO office_inzet_project_team(project_id,person_id) VALUES (17,101),(18,101),(17,42),(17,102)");
$db->exec("INSERT INTO office_buildings(id,code,name) VALUES (1,'FLOW-CI','Fictief gebouw')");
$db->exec("INSERT INTO office_project_buildings(project_id,building_id) VALUES (17,1)");
$db->exec("INSERT INTO office_building_zones(id,building_id,zone_name,latitude,longitude,radius_metres) VALUES (8,1,'Poort',51.5,4.3,1500)");
$map = OfficeInzetIdentityMappingGuard::validate([
  ['source_system'=>'drupal','source_person_id'=>'42','office_person_id'=>101],
]);
$person = OnSiteActivationIdentityBridge::officePerson($map,['uid'=>42,'created'=>time()-60],time());
$assert($person===101 && OnSiteActivationIdentityBridge::verifiedPerson($map,101)===101,'Source UID became Office person ID');
$denied(static fn()=>OnSiteActivationIdentityBridge::verifiedPerson($map,42));
$denied(static fn()=>OnSiteActivationIdentityBridge::officePerson($map,['uid'=>101,'created'=>time()-60],time()));
$issuer = new OnSiteActivationInvitationIssuer($db);
$action = new OnSiteOfficeInvitationAction($db,$issuer);
foreach ([['reader',17,101],['inactive',17,101],['unknown',17,101],['john:office',18,101],['john:office',17,102]] as $args) {
  $denied(static fn()=>$action->issue(...$args));
}
$service = new OnSiteActivationService($db,new OnSiteDeviceTokenRepository($db));
$first = $action->issue('john:office',17,$person);
$second = $action->issue('john:office',17,$person);
$assert($service->activate($first['activation_token'])['status']===401,'Superseded code remained valid');
// Removal from invitation project cannot be bypassed by membership of another project.
$db->exec('DELETE FROM office_inzet_project_team WHERE person_id=101 AND project_id=17');
$assert($service->activate($second['activation_token'])['status']===401,'Removed project assignment accepted');
$db->exec('INSERT INTO office_inzet_project_team(project_id,person_id) VALUES (17,101)');
$expiry = $action->issue('john:office',17,$person);
$db->prepare('UPDATE office_onsite_activation_invitations SET expires_at=? WHERE token_hash=?')
  ->execute(['2000-01-01 00:00:00',hash('sha256',$expiry['activation_token'])]);
$assert($service->activate($expiry['activation_token'])['status']===401,'Expired invitation accepted');
$invitation = $action->issue('john:office',17,$person);
$activated = $service->activate($invitation['activation_token']);
$assert($activated['status']===200,'Activation failed');
$device = $activated['body']['device_token'];
$assert($service->activate($invitation['activation_token'])['status']===401,'Single-use code replay accepted');
$assert((int)$db->query('SELECT COUNT(*) FROM office_onsite_device_tokens')->fetchColumn()===1,'Duplicate device');
$assert((int)$db->query('SELECT COUNT(*) FROM office_inzet_people')->fetchColumn()===3,'Employee was provisioned');
$assert($db->query('SELECT token_hash FROM office_onsite_device_tokens')->fetchColumn()===hash('sha256',$device),'Unhashed credential');
$devices = new OnSiteDeviceTokenRepository($db);
$zones = new OnSiteBuildingZoneAccess(new OfficeBuildingZoneRepository($db),static fn(string $id):bool=>in_array($id,['17','18'],true));
$bootstrapRepo = new OnSiteBootstrapRepository($db);
$bootstrap = (new OnSiteBootstrapService($devices))->bootstrap($device,static fn(string $id):array=>$bootstrapRepo->forEmployee($id,true));
$assert($bootstrap['status']===200 && count($bootstrap['body']['projects'])===1
  && $bootstrap['body']['projects'][0]['id']==='17'
  && $bootstrap['body']['projects'][0]['zones'][0]['id']==='8'
  && $bootstrap['body']['projects'][0]['zones'][0]['radius_metres']===1500.0,'Native bootstrap and presence zone contracts differ');
$app = new OnSitePresenceApplication($db,$zones->callback());
$event = ['project_id'=>'17','zone_id'=>'8','kind'=>'in','occurred_at'=>'2026-10-23T08:00:00Z'];
$id = '550e8400-e29b-41d4-a716-446655440000'; // Actual iPhone UUID envelope.
$submit = static fn(string $token,string $eventId,array $e):array=>$app->submit(['event_id'=>$eventId,'event'=>$e],$token);
$assert($submit($device,$id,$event)['status']===202,'Authenticated arrival failed');
$assert($submit($device,$id,$event)['body']['duplicate']===true,'Retry not idempotent');
$assert($submit($device,$id,array_replace($event,['kind'=>'out']))['status']===409,'Conflicting replay did not return 409');
$otherInvitation=$action->issue('john:office',17,42);
$otherDevice=$service->activate($otherInvitation['activation_token'])['body']['device_token'];
$assert($submit($otherDevice,$id,$event)['status']===409,'Cross-person replay accepted');
foreach ([['project_id'=>'18'],['zone_id'=>'9']] as $change) {
  $assert($submit($device,'550e8400-e29b-41d4-a716-446655440001',array_replace($event,$change))['status']===422,'Unauthorized project/zone accepted');
}
$assert($submit($device,'550e8400-e29b-41d4-a716-446655440001',$event+['employee_id'=>'42'])['status']===400,'Spoofed employee accepted');
$assert($submit(str_repeat('z',48),$id,$event)['status']===401,'Unknown token accepted');
$db->exec('UPDATE office_building_zones SET active=0 WHERE id=8');
$assert($bootstrapRepo->forEmployee('101',true)===[],'Disabled zone exposed by bootstrap');
$assert($submit($device,'550e8400-e29b-41d4-a716-446655440001',$event)['status']===422,'Disabled zone accepted');
$db->exec('UPDATE office_building_zones SET active=1 WHERE id=8');
$departure=array_replace($event,['kind'=>'out','occurred_at'=>'2026-10-23T09:00:00Z']);
$assert($submit($device,'550e8400-e29b-41d4-a716-446655440002',$departure)['status']===202,'Departure failed');
$review=new OnSitePresenceReviewRepository($db);
$evidence=$review->forManager('john:office','17');
$assert(count($evidence['intervals'])===1 && $evidence['intervals'][0]['status']==='evidence_only'
  && $evidence['intervals'][0]['person_id']==='101' && $evidence['intervals'][0]['elapsed_seconds']===3600,'Evidence projection failed');
$assert($review->forManager('reader','17')===['intervals'=>[],'exceptions'=>[]],'Reader received evidence');
$db->exec('UPDATE office_inzet_people SET active=0 WHERE id=101');
$assert($submit($device,$id,$event)['status']===401,'Inactive employee accepted');
$db->exec('UPDATE office_inzet_people SET active=1 WHERE id=101');
$db->exec("UPDATE office_onsite_device_tokens SET expires_at='2000-01-01' WHERE person_id=101");
$assert($submit($device,$id,$event)['status']===401,'Expired device accepted');
$db->exec('UPDATE office_onsite_device_tokens SET expires_at=NULL WHERE person_id=101');
$assert((new OnSiteOfficeDeviceRevocationAction($db))->revoke('john:office',17,101,hash('sha256',$device)),'Revocation failed');
$assert($submit($device,$id,$event)['status']===401,'Revoked device accepted');
$assert((int)$db->query('SELECT COUNT(*) FROM office_onsite_event_inbox')->fetchColumn()===2,'Rejected events persisted');
echo "Verified source -> existing Office person -> invitation/reissue -> device -> native bootstrap -> inbox -> evidence and negative cases OK\n";

// Two independent connections race to redeem one invitation. Exactly one wins.
$concurrent=$action->issue('john:office',17,101);
$children=[];
for ($i=0;$i<2;$i++) {
  $process=proc_open([PHP_BINARY,__FILE__,'--redeem'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
  if (!is_resource($process)) throw new RuntimeException('Redemption worker unavailable');
  fwrite($pipes[0],$concurrent['activation_token']); fclose($pipes[0]);
  $children[]=[$process,$pipes];
}
$statuses=[];
foreach ($children as [$process,$pipes]) {
  $response=stream_get_contents($pipes[1]); $errors=stream_get_contents($pipes[2]);
  fclose($pipes[1]);fclose($pipes[2]);
  $assert(proc_close($process)===0 && $errors==='','Concurrent activation worker failed');
  $statuses[]=json_decode($response,true,8,JSON_THROW_ON_ERROR)['status'];
}
sort($statuses);
$assert($statuses===[200,401],'Concurrent invitation redeemed more than once');
$assert((int)$db->query('SELECT COUNT(*) FROM office_onsite_device_tokens')->fetchColumn()===3,'Concurrent activation issued extra credentials');
echo "Concurrent single-use invitation redemption OK\n";
