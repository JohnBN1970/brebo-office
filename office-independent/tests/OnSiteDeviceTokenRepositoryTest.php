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
$repository = new OnSiteDeviceTokenRepository($db);
$repository->migrate();
$validToken = str_repeat('a', 40);
$revokedToken = str_repeat('b', 40);
$repository->register($validToken, 'john.boon');
$repository->register($revokedToken, 'john.boon');
$repository->revoke($revokedToken);
if ($repository->resolve($validToken) !== 'john.boon') throw new RuntimeException('Valid device not resolved');
if ($repository->resolve($revokedToken) !== null) throw new RuntimeException('Revoked device accepted');
if ($repository->resolve(str_repeat('c', 40)) !== null) throw new RuntimeException('Unknown device accepted');
if ($repository->resolve('short') !== null) throw new RuntimeException('Short credential accepted');
if ($repository->resolve(str_repeat('x', 513)) !== null) throw new RuntimeException('Oversized credential accepted');
try {
  $repository->register('short', 'john.boon');
  throw new RuntimeException('Short credential registered');
} catch (InvalidArgumentException $expected) {
  // Invalid device credentials must never be persisted.
}
echo "OnSiteDeviceTokenRepositoryTest OK\n";
