<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteActivationInvitationSchema.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteActivationService.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteDeviceTokenRepository.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteDeviceTokenSchema.php';
$dsn = getenv('OFFICE_INZET_TEST_DSN');
if (!is_string($dsn) || !str_contains($dsn, 'dbname=office_inzet_ci')) throw new RuntimeException('Dedicated MySQL CI database required');
$db = new PDO($dsn, (string)getenv('OFFICE_INZET_TEST_USER'), (string)getenv('OFFICE_INZET_TEST_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('DROP TABLE IF EXISTS office_onsite_activation_invitations');
$db->exec('DROP TABLE IF EXISTS office_onsite_device_tokens');
$db->exec('DROP TABLE IF EXISTS office_inzet_people');
$db->exec('CREATE TABLE office_inzet_people (id BIGINT UNSIGNED NOT NULL PRIMARY KEY, active TINYINT(1) NOT NULL) ENGINE=InnoDB');
$db->exec('INSERT INTO office_inzet_people(id,active) VALUES (1,1),(2,0)');
foreach (\Brebo\Office\Inzet\OnSiteDeviceTokenSchema::statements() as $sql) $db->exec($sql);
foreach (\Brebo\Office\Inzet\OnSiteActivationInvitationSchema::statements() as $sql) $db->exec($sql);
$issue = $db->prepare('INSERT INTO office_onsite_activation_invitations(token_hash,person_id,expires_at) VALUES (?, ?, ?)');
$token = str_repeat('A', 40);
$issue->execute([hash('sha256',$token),1,'2099-01-01 00:00:00']);
$issue->execute([hash('sha256',str_repeat('B',40)),1,'2000-01-01 00:00:00']);
$issue->execute([hash('sha256',str_repeat('C',40)),2,'2099-01-01 00:00:00']);
$service = new \Brebo\Office\Inzet\OnSiteActivationService($db, new \Brebo\Office\Inzet\OnSiteDeviceTokenRepository($db));
$result = $service->activate($token);
if ($result['status'] !== 200 || !is_string($result['body']['device_token'] ?? null)) throw new RuntimeException('Valid activation failed');
$device = $result['body']['device_token'];
$lookup = new \Brebo\Office\Inzet\OnSiteDeviceTokenRepository($db);
if ($lookup->resolveEmployee($device) !== '1') throw new RuntimeException('Device not linked to active Office person');
foreach ([$token,str_repeat('B',40),str_repeat('C',40)] as $rejected) {
  if ($service->activate($rejected)['status'] !== 401) throw new RuntimeException('Reused, expired or inactive invitation accepted');
}
$count = (int)$db->query('SELECT COUNT(*) FROM office_onsite_device_tokens')->fetchColumn();
if ($count !== 1) throw new RuntimeException('Unexpected additional device credentials');
echo "Office-native OnSite activation MySQL contract OK\n";
