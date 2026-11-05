<?php
declare(strict_types=1);
/** Isolated full authenticated receiver test; no live database credentials. */
$root = dirname(__DIR__) . '/src';
foreach ([
 '/Intake/OfficeDisciplineCatalog.php',
 '/Intake/OfficeProcessBlueprint.php',
 '/Intake/OfficeIntakeProcessPlan.php',
 '/Intake/OfficeCanonicalIntakeSchema.php',
 '/Intake/OfficeCanonicalIntakeRepository.php',
 '/Intake/OfficeCanonicalIntakeAdapter.php',
 '/Buildings/OfficeIntakeBuildingResolver.php',
 '/Intake/OfficeCanonicalIntakeBuildingHandoff.php',
 '/Intake/OfficeIntakeSourceAuthenticator.php',
 '/Intake/OfficeIntakeReplayGuard.php',
 '/Intake/OfficeIntakeSecureReceiver.php',
] as $file) {
    require_once $root . $file;
}
use Brebo\Office\Intake\OfficeCanonicalIntakeSchema;
use Brebo\Office\Intake\OfficeCanonicalIntakeRepository;
use Brebo\Office\Intake\OfficeCanonicalIntakeBuildingHandoff;
use Brebo\Office\Intake\OfficeIntakeSourceAuthenticator;
use Brebo\Office\Intake\OfficeIntakeReplayGuard;
use Brebo\Office\Intake\OfficeIntakeSecureReceiver;
use Brebo\Office\Buildings\OfficeIntakeBuildingResolver;
$dsn = getenv('OFFICE_INTAKE_INTEGRATION_DSN');
$user = getenv('OFFICE_INTAKE_INTEGRATION_USER');
$pass = getenv('OFFICE_INTAKE_INTEGRATION_PASSWORD');
if (!is_string($dsn) || !preg_match('/(?:^|;)dbname=office_intake_ci(?:;|$)/D', $dsn)
 || !is_string($user) || !is_string($pass)) {
    throw new RuntimeException('isolated_mysql_required');
}
$db = new PDO($dsn, $user, $pass, [
 PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
 PDO::ATTR_EMULATE_PREPARES => false,
]);
if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'office_intake_ci') {
    throw new RuntimeException('wrong_database');
}
$db->exec('CREATE TABLE office_buildings (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, address_line VARCHAR(255), postal_code VARCHAR(32), city VARCHAR(128)) ENGINE=InnoDB');
$db->exec('CREATE TABLE office_crm_relations (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB');
foreach (OfficeCanonicalIntakeSchema::statements() as $sql) {
    $db->exec($sql);
}
$db->exec(OfficeIntakeReplayGuard::schema());
$db->exec("INSERT INTO office_buildings (id, address_line, postal_code, city) VALUES (42, 'Marktstraat 10', '1234 AB', 'Rotterdam')");
$key = str_repeat('s', 48);
$receiver = new OfficeIntakeSecureReceiver(
 new OfficeIntakeSourceAuthenticator(['website' => $key]),
 new OfficeIntakeReplayGuard($db),
 new OfficeCanonicalIntakeBuildingHandoff(
  new OfficeIntakeBuildingResolver($db),
  new OfficeCanonicalIntakeRepository($db)
 )
);
$now = 1793836800;
$timestamp = (string) $now;
$body = json_encode([
 'source' => 'website', 'request_id' => 'WEB-SIGNED-001',
 'disciplines' => ['bouw'], 'address' => 'Marktstraat 10',
 'postal_code' => '1234ab', 'city' => 'Rotterdam',
], JSON_THROW_ON_ERROR);
$sign = static fn (string $raw, string $nonce): string => 'sha256=' . hash_hmac('sha256', "website\n" . $timestamp . "\n" . $nonce . "\n" . $raw, $key);
$nonce = hash('sha256', 'delivery-1');
$first = $receiver->receive('website', $body, $timestamp, $sign($body, $nonce), $nonce, $now);
if ($first['request_id'] < 1 || (string) $first['building_id'] !== '42'
 || $first['building_resolution'] !== 'linked') {
    throw new RuntimeException('signed_registration_failed');
}
$expectRejected = static function (callable $attempt, string $reason): void {
    try {
        $attempt();
    } catch (DomainException | InvalidArgumentException $expected) {
        return;
    }
    throw new RuntimeException($reason);
};
$expectRejected(
 static fn () => $receiver->receive('website', $body, $timestamp, $sign($body, $nonce), $nonce, $now),
 'replay_was_accepted'
);
$expectRejected(
 static fn () => $receiver->receive('website', $body . ' ', $timestamp, $sign($body, hash('sha256', 'delivery-tampered')), hash('sha256', 'delivery-tampered'), $now),
 'tampered_payload_accepted'
);
$expectRejected(
 static fn () => $receiver->receive('website', $body, (string) ($now - 301), $sign($body, hash('sha256', 'delivery-expired')), hash('sha256', 'delivery-expired'), $now),
 'expired_payload_accepted'
);
$expectRejected(
 static fn () => $receiver->receive('website', $body, $timestamp, $sign($body, $nonce), hash('sha256', 'forged-nonce'), $now),
 'nonce_substitution_accepted'
);
$retry = $receiver->receive('website', $body, $timestamp, $sign($body, hash('sha256', 'delivery-2')), hash('sha256', 'delivery-2'), $now);
if ($retry !== $first) {
    throw new RuntimeException('fresh_nonce_retry_failed');
}
foreach ([
 'office_intake_requests' => 1,
 'office_intake_events' => 1,
 'office_intake_request_disciplines' => 1,
 'office_intake_signed_nonces' => 2,
] as $table => $expected) {
    if ((int) $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() !== $expected) {
        throw new RuntimeException('count_mismatch_' . $table);
    }
}
echo "OfficeIntakeSecureReceiverMySqlTest OK\n";
