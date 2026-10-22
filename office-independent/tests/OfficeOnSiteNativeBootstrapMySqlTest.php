<?php
declare(strict_types=1);

// Isolated MySQL CI test: temporary tables shadow production-shaped names.
// No persistent tables or records are changed.
$dsn=getenv('OFFICE_INZET_TEST_DSN');
if (!is_string($dsn) || !str_contains($dsn,'dbname=office_inzet_ci')) {
  throw new RuntimeException('Dedicated MySQL CI database required');
}
$base=dirname(__DIR__).'/src/Inzet/';
foreach (['OnSiteDeviceTokenRepository.php','OnSiteBootstrapService.php','OnSiteNativeBootstrapRepository.php'] as $file) {
  require_once $base.$file;
}
$db=new PDO($dsn,(string)getenv('OFFICE_INZET_TEST_USER'),(string)getenv('OFFICE_INZET_TEST_PASSWORD'),[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false
]);
foreach ([
  'CREATE TEMPORARY TABLE office_inzet_people (id BIGINT PRIMARY KEY,active TINYINT NOT NULL)',
  'CREATE TEMPORARY TABLE office_inzet_project_team (person_id BIGINT NOT NULL,project_id BIGINT NOT NULL)',
  'CREATE TEMPORARY TABLE office_projects (id BIGINT PRIMARY KEY,title VARCHAR(255) NOT NULL)',
  'CREATE TEMPORARY TABLE office_project_buildings (project_id BIGINT NOT NULL,building_id BIGINT NOT NULL)',
  'CREATE TEMPORARY TABLE office_buildings (id BIGINT PRIMARY KEY,name VARCHAR(255) NOT NULL)',
  'CREATE TEMPORARY TABLE office_building_zones (id BIGINT PRIMARY KEY,building_id BIGINT NOT NULL,zone_name VARCHAR(160) NOT NULL,latitude DECIMAL(10,7) NOT NULL,longitude DECIMAL(10,7) NOT NULL,radius_metres DECIMAL(9,2) NOT NULL,active TINYINT NOT NULL)',
  'CREATE TEMPORARY TABLE office_onsite_device_tokens (token_hash CHAR(64) PRIMARY KEY,person_id BIGINT NOT NULL,revoked_at DATETIME NULL,expires_at DATETIME NULL)'
] as $sql) $db->exec($sql);
$db->exec("INSERT INTO office_inzet_people VALUES (101,1),(102,1),(103,0)");
$db->exec("INSERT INTO office_projects VALUES (17,'Testproject'),(18,'Ander project')");
$db->exec("INSERT INTO office_inzet_project_team VALUES (101,17),(102,18),(103,17)");
$db->exec("INSERT INTO office_buildings VALUES (9,'Testgebouw'),(10,'Ander gebouw')");
$db->exec("INSERT INTO office_project_buildings VALUES (17,9),(18,10)");
$db->exec("INSERT INTO office_building_zones VALUES (8,9,'Ingang',51.5000000,4.3000000,100,1),(9,9,'Uitgeschakeld',51.5,4.3,100,0),(10,10,'Niet toegewezen',51.5,4.3,100,1),(11,9,'Te groot',51.5,4.3,1500,1)");
$token=str_repeat('t',48);
$db->prepare('INSERT INTO office_onsite_device_tokens (token_hash,person_id) VALUES (?,101)')->execute([hash('sha256',$token)]);
$service=new \Brebo\Office\Inzet\OnSiteBootstrapService(new \Brebo\Office\Inzet\OnSiteDeviceTokenRepository($db));
$repo=new \Brebo\Office\Inzet\OnSiteNativeBootstrapRepository($db);
$load=static fn(string $id):array=>$repo->forEmployee($id);
$result=$service->bootstrap($token,$load);
if ($result['status']!==200 || count($result['body']['projects'])!==1
  || $result['body']['projects'][0]['id']!=='17'
  || $result['body']['projects'][0]['name']!=='Testproject'
  || count($result['body']['projects'][0]['zones'])!==1
  || $result['body']['projects'][0]['zones'][0]['id']!=='8') {
  throw new RuntimeException('Bootstrap leaked unauthorized, inactive or oversized zone');
}
$db->exec('UPDATE office_inzet_people SET active=0 WHERE id=101');
if ($service->bootstrap($token,$load)['status']!==401) throw new RuntimeException('Inactive employee accepted');
$db->exec('UPDATE office_inzet_people SET active=1 WHERE id=101');
$db->exec('UPDATE office_onsite_device_tokens SET revoked_at=UTC_TIMESTAMP()');
if ($service->bootstrap($token,$load)['status']!==401) throw new RuntimeException('Revoked device accepted');
echo "OnSite native bootstrap MySQL authorization OK\n";
