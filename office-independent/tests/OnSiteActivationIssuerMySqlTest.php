<?php
declare(strict_types=1);
$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:')) throw new RuntimeException('Isolated MySQL required');
$db = new PDO($dsn, getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$sql = file_get_contents(__DIR__.'/../deployment/hostinger/sql/onsite-presence-mysql.sql');
if ($sql === false) throw new RuntimeException('Missing schema');
foreach (explode(';',preg_replace('/^--.*$/m','',$sql)) as $statement) if (trim($statement)!=='') $db->exec($statement);
require_once __DIR__.'/../src/Inzet/OnSiteActivationIssuer.php';
require_once __DIR__.'/../src/Inzet/OnSiteActivationService.php';
require_once __DIR__.'/../src/Inzet/OnSiteDeviceTokenRepository.php';
$id = 'issuer-test-'.bin2hex(random_bytes(5));
$db->prepare('INSERT INTO onsite_employees (employee_id,display_name,language,active) VALUES (?,?,?,1)')
  ->execute([$id,'Test','nl']);
$issuer = new Brebo\Office\Inzet\OnSiteActivationIssuer($db);
try { $issuer->issue($id,false); throw new RuntimeException('Unauthorized issuance accepted'); }
catch (RuntimeException $e) { if ($e->getMessage() === 'Unauthorized issuance accepted') throw $e; }
$issued = $issuer->issue($id,true);
if (!preg_match('/^[A-Za-z0-9_-]{32,128}$/D',$issued['activation_token'])) throw new RuntimeException('Invalid token format');
$redeemer = new Brebo\Office\Inzet\OnSiteActivationService($db,new Brebo\Office\Inzet\OnSiteDeviceTokenRepository($db));
if ($redeemer->activate($issued['activation_token'])['status'] !== 200) throw new RuntimeException('Issued token cannot activate');
echo "OnSiteActivationIssuerMySqlTest OK\n";
