<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Inzet/OnSiteDeviceTokenRepository.php';

use Brebo\Office\Inzet\OnSiteDeviceTokenRepository;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  echo "SKIP: pdo_sqlite unavailable\n";
  exit(0);
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE onsite_device_tokens (token_hash TEXT PRIMARY KEY, employee_id TEXT NOT NULL, revoked_at TEXT)');
$validToken = str_repeat('a', 40);
$revokedToken = str_repeat('b', 40);
$insert = $db->prepare('INSERT INTO onsite_device_tokens (token_hash, employee_id, revoked_at) VALUES (?,?,?)');
$insert->execute([hash('sha256', $validToken), 'john.boon', null]);
$insert->execute([hash('sha256', $revokedToken), 'john.boon', '2026-10-18T12:00:00Z']);
$repository = new OnSiteDeviceTokenRepository($db);
if ($repository->resolve($validToken) !== 'john.boon') throw new RuntimeException('Valid device not resolved');
if ($repository->resolve($revokedToken) !== null) throw new RuntimeException('Revoked device accepted');
if ($repository->resolve(str_repeat('c', 40)) !== null) throw new RuntimeException('Unknown device accepted');
if ($repository->resolve('short') !== null) throw new RuntimeException('Short credential accepted');
echo "OnSiteDeviceTokenRepositoryTest OK\n";
