<?php
declare(strict_types=1);

/**
 * Full trusted internal handoff integration on disposable MySQL 8.
 * Never uses live Office or Office-test credentials.
 */
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
] as $file) {
    require_once $root . $file;
}

use Brebo\Office\Intake\OfficeCanonicalIntakeSchema;
use Brebo\Office\Intake\OfficeCanonicalIntakeRepository;
use Brebo\Office\Intake\OfficeCanonicalIntakeBuildingHandoff;
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
$db->exec("INSERT INTO office_buildings (id, address_line, postal_code, city) VALUES
    (42, 'Marktstraat 10', '1234 AB', 'Rotterdam'),
    (43, 'Kerklaan 5', '2345CD', 'Utrecht'),
    (44, 'Kerklaan 5', '2345 CD', 'Utrecht')");
$db->exec('INSERT INTO office_crm_relations (id) VALUES (7)');
$handoff = new OfficeCanonicalIntakeBuildingHandoff(
    new OfficeIntakeBuildingResolver($db),
    new OfficeCanonicalIntakeRepository($db)
);
$website = [
    'source' => 'website',
    'request_id' => 'WEB-2026-001',
    'disciplines' => ['bouw', 'expertise'],
    'address' => ' Marktstraat  10 ',
    'postal_code' => '1234ab',
    'city' => 'ROTTERDAM',
    'relation_id' => 7,
];
$first = $handoff->register($website);
if ($first['request_id'] < 1 || $first['building_id'] !== '42'
    || $first['building_resolution'] !== 'linked') {
    throw new RuntimeException('website_handoff_failed');
}
$retry = $handoff->register($website);
if ($retry !== $first) {
    throw new RuntimeException('website_retry_not_idempotent');
}
$email = $handoff->register([
    'source' => 'email',
    'request_id' => 'MAIL-2026-001',
    'disciplines' => ['advies'],
    'address' => 'Kerklaan 5',
    'postal_code' => '2345CD',
    'city' => 'Utrecht',
]);
if ($email['building_id'] !== null || $email['building_resolution'] !== 'ambiguous'
    || $email['candidates'] !== 2) {
    throw new RuntimeException('ambiguous_building_auto_linked');
}
$missing = $handoff->register([
    'source' => 'email',
    'request_id' => 'MAIL-2026-002',
    'disciplines' => ['expertise'],
    'address' => 'Onbekend 9',
    'postal_code' => '9999ZZ',
    'city' => 'Amsterdam',
]);
if ($missing['building_id'] !== null || $missing['building_resolution'] !== 'required') {
    throw new RuntimeException('missing_building_auto_linked');
}
$counts = [
    'office_intake_requests' => 3,
    'office_intake_request_disciplines' => 4,
    'office_intake_events' => 3,
    'office_buildings' => 3,
];
foreach ($counts as $table => $expected) {
    if ((int) $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() !== $expected) {
        throw new RuntimeException('unexpected_count_' . $table);
    }
}
$rows = $db->query('SELECT source_request_id, building_id, building_resolution FROM office_intake_requests ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
if ((string) $rows[0]['building_id'] !== '42' || $rows[0]['building_resolution'] !== 'linked'
    || $rows[1]['building_id'] !== null || $rows[1]['building_resolution'] !== 'required'
    || $rows[2]['building_id'] !== null || $rows[2]['building_resolution'] !== 'required') {
    throw new RuntimeException('persisted_building_resolution_invalid');
}
echo "OfficeCanonicalIntakeHandoffMySqlTest OK\n";
