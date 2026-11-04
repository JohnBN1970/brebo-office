<?php
declare(strict_types=1);

/**
 * Isolated MySQL integration regression. Requires a dedicated disposable schema.
 * Never runs against an Office-test or production database.
 */
require_once dirname(__DIR__) . '/src/Intake/OfficeDisciplineCatalog.php';
require_once dirname(__DIR__) . '/src/Intake/OfficeProcessBlueprint.php';
require_once dirname(__DIR__) . '/src/Intake/OfficeIntakeProcessPlan.php';
require_once dirname(__DIR__) . '/src/Intake/OfficeCanonicalIntakeSchema.php';
require_once dirname(__DIR__) . '/src/Intake/OfficeCanonicalIntakeRepository.php';

use Brebo\Office\Intake\OfficeCanonicalIntakeRepository;
use Brebo\Office\Intake\OfficeCanonicalIntakeSchema;

$dsn = getenv('OFFICE_INTAKE_INTEGRATION_DSN');
$user = getenv('OFFICE_INTAKE_INTEGRATION_USER');
$password = getenv('OFFICE_INTAKE_INTEGRATION_PASSWORD');
if (!is_string($dsn) || !preg_match('/(?:^|;)dbname=(office_intake_ci)(?:;|$)/D', $dsn)
    || !is_string($user) || !is_string($password)) {
    fwrite(STDERR, "ABORT: dedicated office_intake_ci schema required\n");
    exit(2);
}
$db = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'office_intake_ci') {
    throw new RuntimeException('integration_scope_rejected');
}
$db->exec('CREATE TABLE office_buildings (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB');
$db->exec('CREATE TABLE office_crm_relations (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB');
foreach (OfficeCanonicalIntakeSchema::statements() as $sql) {
    $db->exec($sql);
}
$db->exec('INSERT INTO office_buildings (id) VALUES (42)');
$db->exec('INSERT INTO office_crm_relations (id) VALUES (7)');
$repo = new OfficeCanonicalIntakeRepository($db);
$payload = ['request_id' => 'REQ-2026-001', 'building_id' => '42',
    'disciplines' => ['bouw', 'expertise']];
$id = $repo->register('website', $payload, 7);
if ($id < 1) {
    throw new RuntimeException('registration_failed');
}
$repeat = $repo->register('website', $payload, 7);
if ($repeat !== $id) {
    throw new RuntimeException('idempotency_failed');
}
$assertCount = static function (string $table, int $expected) use ($db): void {
    $actual = (int) $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    if ($actual !== $expected) {
        throw new RuntimeException('unexpected_' . $table . '_count_' . $actual);
    }
};
$assertCount('office_intake_requests', 1);
$assertCount('office_intake_request_disciplines', 2);
$assertCount('office_intake_events', 1);

foreach ([
    [$payload, null, 'intake_retry_conflict'],
    [['request_id' => 'REQ-2026-001', 'building_id' => '42',
      'disciplines' => ['advies']], 7, 'intake_retry_disciplines_conflict'],
    [['request_id' => 'REQ-2026-001', 'building_id' => '41',
      'disciplines' => ['bouw', 'expertise']], 7, 'intake_retry_conflict'],
] as [$conflictPayload, $relation, $expectedMessage]) {
    try {
        $repo->register('website', $conflictPayload, $relation);
        throw new RuntimeException('conflict_was_accepted');
    } catch (DomainException $expected) {
        if ($expected->getMessage() !== $expectedMessage) {
            throw $expected;
        }
    }
}
$assertCount('office_intake_requests', 1);
$assertCount('office_intake_request_disciplines', 2);
$assertCount('office_intake_events', 1);

try {
    $repo->register('website', ['request_id' => 'REQ-2026-002',
        'building_id' => '999', 'disciplines' => ['bouw']], 7);
    throw new RuntimeException('invalid_foreign_key_accepted');
} catch (PDOException $expected) {
    // Parent building does not exist: transaction must be rolled back.
}
$assertCount('office_intake_requests', 1);
$assertCount('office_intake_events', 1);

$unresolved = $repo->register('email', [
    'request_id' => 'MAIL-2026-001', 'disciplines' => ['advies'],
]);
$statement = $db->prepare('SELECT building_id, building_resolution FROM office_intake_requests WHERE id = ?');
$statement->execute([$unresolved]);
$unresolvedRow = $statement->fetch(PDO::FETCH_ASSOC);
if (!is_array($unresolvedRow) || $unresolvedRow['building_id'] !== null
    || $unresolvedRow['building_resolution'] !== 'required') {
    throw new RuntimeException('unresolved_building_not_preserved');
}
$assertCount('office_intake_requests', 2);
$assertCount('office_intake_request_disciplines', 3);
$assertCount('office_intake_events', 2);
echo "OfficeCanonicalIntakeRepositoryMySqlTest OK\n";
