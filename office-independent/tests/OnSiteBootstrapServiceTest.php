<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Inzet/OnSiteDeviceTokenRepository.php';
require_once __DIR__ . '/../src/Inzet/OnSiteBootstrapService.php';
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$devices = new Brebo\Office\Inzet\OnSiteDeviceTokenRepository($db);
$devices->migrate();
$token = str_repeat('x', 40);
$devices->register($token, 'employee-1');
$service = new Brebo\Office\Inzet\OnSiteBootstrapService($devices);
$provider = static fn(string $id): array => [
  'employee'=>['id'=>$id,'display_name'=>'Test Employee','language'=>'nl'],
  'projects'=>[['id'=>'37','name'=>'Test Project','zones'=>[[
    'id'=>'100','name'=>'Test Zone','latitude'=>51.0,'longitude'=>4.0,'radius_metres'=>100,'active'=>true,
  ]]]],
];
if ($service->bootstrap(null, $provider)['status'] !== 401) throw new RuntimeException('Unauthenticated bootstrap accepted');
$result = $service->bootstrap($token, $provider);
if ($result['status'] !== 200 || $result['body']['projects'][0]['zones'][0]['radius_metres'] !== 100.0) throw new RuntimeException('Bootstrap contract mismatch');
$invalid = static fn(string $id): array => [
  'employee'=>['id'=>$id,'display_name'=>'Test Employee','language'=>'nl'],
  'projects'=>[['id'=>'37','name'=>'Test Project','zones'=>[[
    'id'=>'100','name'=>'Test Zone','latitude'=>91,'longitude'=>4,'radius_metres'=>100,'active'=>true,
  ]]]],
];
if ($service->bootstrap($token, $invalid)['status'] !== 503) throw new RuntimeException('Invalid geofence accepted');
$devices->revoke($token);
if ($service->bootstrap($token, $provider)['status'] !== 401) throw new RuntimeException('Revoked token accepted');
echo "OnSiteBootstrapServiceTest OK\n";
