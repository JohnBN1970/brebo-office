<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteActivationInvitationSchema.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteActivationInvitationIssuer.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteOfficeInvitationAction.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteActivationService.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteDeviceTokenRepository.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteDeviceTokenSchema.php';
$dsn = getenv('OFFICE_INZET_TEST_DSN');
if (!is_string($dsn) || !str_contains($dsn, 'dbname=office_inzet_ci')) throw new RuntimeException('Dedicated MySQL CI database required');
$db = new PDO($dsn, (string)getenv('OFFICE_INZET_TEST_USER'), (string)getenv('OFFICE_INZET_TEST_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('DROP TABLE IF EXISTS office_onsite_activation_invitations');
$db->exec('DROP TABLE IF EXISTS office_onsite_device_tokens');
$db->exec('INSERT INTO office_inzet_people(id,active) VALUES (201,1),(202,0)');
foreach (\Brebo\Office\Inzet\OnSiteDeviceTokenSchema::statements() as $sql) $db->exec($sql);
foreach (\Brebo\Office\Inzet\OnSiteActivationInvitationSchema::statements() as $sql) $db->exec($sql);
$issuer = new \Brebo\Office\Inzet\OnSiteActivationInvitationIssuer($db);
$action = new \Brebo\Office\Inzet\OnSiteOfficeInvitationAction($db, $issuer);
try { $action->issue('unknown-operator', 999, 201); throw new RuntimeException('Unauthorized invitation issued'); }
catch (RuntimeException $e) { if ($e->getMessage() === 'Unauthorized invitation issued') throw $e; }
// Authorization is enforced against real Office membership and project-team tables.
$db->exec("INSERT INTO office_operator_accounts(id,active) VALUES ('onsite-test-manager',1),('onsite-test-reader',1)");
$db->exec("INSERT INTO office_projects(id,code,title,status) VALUES (91001,'ONSITE-CI-1','OnSite test project','active')");
$db->exec("INSERT INTO office_project_memberships(project_id,operator_id,permission) VALUES
  (91001,'onsite-test-manager','manage'),(91001,'onsite-test-reader','read')");
$db->exec("INSERT INTO office_inzet_project_team(project_id,person_id) VALUES (91001,201)");
$authorized = $action->issue('onsite-test-manager', 91001, 201);
if (strlen($authorized['activation_token']) < 40) throw new RuntimeException('Manager invitation not issued');
foreach ([['onsite-test-reader',91001,201],['onsite-test-manager',91002,201],
  ['onsite-test-manager',91001,202]] as [$operatorId,$projectId,$personId]) {
  try {
    $action->issue($operatorId, $projectId, $personId);
    throw new RuntimeException('Unauthorized invitation issued for ' . $operatorId);
  } catch (RuntimeException $e) {
    if (str_starts_with($e->getMessage(), 'Unauthorized invitation issued for ')) throw $e;
  }
}
$generated = $issuer->issue(201);
if (strlen($generated['activation_token']) < 40) throw new RuntimeException('Issued invitation too short');
if ((int)$db->query('SELECT COUNT(*) FROM office_onsite_activation_invitations')->fetchColumn() !== 1) throw new RuntimeException('Invitation not persisted');
try { $issuer->issue(202); throw new RuntimeException('Inactive person invitation issued'); }
catch (RuntimeException $e) { if ($e->getMessage() === 'Inactive person invitation issued') throw $e; }
$issue = $db->prepare('INSERT INTO office_onsite_activation_invitations(token_hash,person_id,expires_at) VALUES (?, ?, ?)');
$token = str_repeat('A', 40);
$issue->execute([hash('sha256',$token),201,'2099-01-01 00:00:00']);
$issue->execute([hash('sha256',str_repeat('B',40)),201,'2000-01-01 00:00:00']);
$issue->execute([hash('sha256',str_repeat('C',40)),202,'2099-01-01 00:00:00']);
$service = new \Brebo\Office\Inzet\OnSiteActivationService($db, new \Brebo\Office\Inzet\OnSiteDeviceTokenRepository($db));
$issuedActivation = $service->activate($generated['activation_token']);
if ($issuedActivation['status'] !== 200) throw new RuntimeException('Issued invitation not redeemable');
$result = $service->activate($token);
if ($result['status'] !== 200 || !is_string($result['body']['device_token'] ?? null)) throw new RuntimeException('Valid activation failed');
$device = $result['body']['device_token'];
if (strlen($device) < 48 || $device === $token) throw new RuntimeException('Device token insufficient or reused invitation');
if ($service->activate('short')['status'] !== 400) throw new RuntimeException('Malformed invitation accepted');
$hashLookup = $db->prepare('SELECT COUNT(*) FROM office_onsite_device_tokens WHERE token_hash = ? AND person_id = ?');
$hashLookup->execute([hash('sha256', $device), 201]);
if ((int)$hashLookup->fetchColumn() !== 1) throw new RuntimeException('Device credential not hashed or linked to expected person');
$lookup = new \Brebo\Office\Inzet\OnSiteDeviceTokenRepository($db);
if ($lookup->resolveEmployee($device) !== '201') throw new RuntimeException('Device not linked to active Office person');
foreach ([$token,str_repeat('B',40),str_repeat('C',40)] as $rejected) {
  if ($service->activate($rejected)['status'] !== 401) throw new RuntimeException('Reused, expired or inactive invitation accepted');
}
$count = (int)$db->query('SELECT COUNT(*) FROM office_onsite_device_tokens')->fetchColumn();
if ($count !== 2) throw new RuntimeException('Unexpected additional device credentials');
echo "Office-native OnSite activation MySQL contract OK\n";
