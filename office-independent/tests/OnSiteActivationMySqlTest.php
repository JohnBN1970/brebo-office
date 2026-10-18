<?php
declare(strict_types=1);
$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:')) throw new RuntimeException('Isolated MySQL test DSN required');
$db = new PDO($dsn, getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$sql = file_get_contents(__DIR__ . '/../deployment/hostinger/sql/onsite-presence-mysql.sql');
if ($sql === false) throw new RuntimeException('Migration missing');
$sql = preg_replace('/^--.*$/m', '', $sql);
foreach (explode(';', $sql) as $statement) if (trim($statement) !== '') $db->exec($statement);
require_once __DIR__ . '/../src/Inzet/OnSiteDeviceTokenRepository.php';
require_once __DIR__ . '/../src/Inzet/OnSiteActivationService.php';
$employeeId = 'activation-test-'.bin2hex(random_bytes(6));
$db->prepare('INSERT INTO onsite_employees (employee_id,display_name,language,active) VALUES (?,?,?,1)')
  ->execute([$employeeId,'Activation Test','nl']);
$secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
$db->prepare('INSERT INTO onsite_activation_tokens (token_hash,employee_id,expires_at) VALUES (?,?,?)')
  ->execute([hash('sha256',$secret),$employeeId,(new DateTimeImmutable('+10 minutes'))->format('c')]);
$devices = new Brebo\Office\Inzet\OnSiteDeviceTokenRepository($db);
$service = new Brebo\Office\Inzet\OnSiteActivationService($db,$devices);
$first = $service->activate($secret);
if ($first['status'] !== 200 || !is_string($first['body']['device_token'] ?? null)
  || $devices->resolve($first['body']['device_token']) !== $employeeId) throw new RuntimeException('Activation failed');
if ($service->activate($secret)['status'] !== 401) throw new RuntimeException('Activation replay accepted');
$expired = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
$db->prepare('INSERT INTO onsite_activation_tokens (token_hash,employee_id,expires_at) VALUES (?,?,?)')
  ->execute([hash('sha256',$expired),$employeeId,(new DateTimeImmutable('-10 minutes'))->format('c')]);
if ($service->activate($expired)['status'] !== 401) throw new RuntimeException('Expired activation accepted');
echo "OnSiteActivationMySqlTest OK\n";
