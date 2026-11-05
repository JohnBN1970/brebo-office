<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Intake/OfficeIntakeReplayGuard.php';

use Brebo\Office\Intake\OfficeIntakeReplayGuard;

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
$db->exec(OfficeIntakeReplayGuard::schema());
$guard = new OfficeIntakeReplayGuard($db);
$nonce = hash('sha256', 'unique-message-1');
$guard->reserve('website', $nonce);
try {
    $guard->reserve('website', $nonce);
    throw new RuntimeException('replay_accepted');
} catch (DomainException $expected) {
    if ($expected->getMessage() !== 'intake_message_replayed') {
        throw $expected;
    }
}
$guard->reserve('email', $nonce);
$guard->reserve('website', hash('sha256', 'unique-message-2'));
foreach ([
    ['website', 'bad'],
    ['invalid source', $nonce],
] as [$source, $invalid]) {
    try {
        $guard->reserve($source, $invalid);
        throw new RuntimeException('invalid_nonce_accepted');
    } catch (InvalidArgumentException $expected) {
    }
}
if ((int) $db->query('SELECT COUNT(*) FROM office_intake_signed_nonces')->fetchColumn() !== 3) {
    throw new RuntimeException('unexpected_nonce_count');
}
echo "OfficeIntakeReplayGuardMySqlTest OK\n";
