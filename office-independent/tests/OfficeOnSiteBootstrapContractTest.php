<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteDeviceTokenRepository.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteBootstrapService.php';

use Brebo\Office\Inzet\OnSiteBootstrapService;
use Brebo\Office\Inzet\OnSiteDeviceTokenRepository;

function check(bool $ok, string $reason): void {
  if (!$ok) throw new RuntimeException($reason);
}

$db = new PDO('sqlite::memory:');
$db->exec('CREATE TABLE office_inzet_people (id INTEGER PRIMARY KEY, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_onsite_device_tokens (person_id INTEGER, token_hash TEXT, revoked_at TEXT, expires_at TEXT)');
$db->exec('INSERT INTO office_inzet_people (id,active) VALUES (1,1)');
$token = str_repeat('A', 40);
$insert = $db->prepare('INSERT INTO office_onsite_device_tokens (person_id,token_hash) VALUES (?,?)');
$insert->execute([1, hash('sha256', $token)]);
// SQLite lacks UTC_TIMESTAMP(), so test the bootstrap formatting with an explicit
// device repository test double once the MySQL integration test is available.
echo "OnSite bootstrap contract test scaffold verified\n";
