<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Buildings/OfficeIntakeBuildingResolver.php';

use Brebo\Office\Buildings\OfficeIntakeBuildingResolver;

$dsn = getenv('OFFICE_INTAKE_INTEGRATION_DSN');
$user = getenv('OFFICE_INTAKE_INTEGRATION_USER');
$password = getenv('OFFICE_INTAKE_INTEGRATION_PASSWORD');
if (!is_string($dsn) || !preg_match('/(?:^|;)dbname=office_intake_ci(?:;|$)/D', $dsn)
    || !is_string($user) || !is_string($password)) {
    fwrite(STDERR, "ABORT: isolated office_intake_ci database required\n");
    exit(2);
}
$db = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'office_intake_ci') {
    throw new RuntimeException('wrong_database');
}
$db->exec('CREATE TABLE office_buildings (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, address_line VARCHAR(255), postal_code VARCHAR(32), city VARCHAR(128)) ENGINE=InnoDB');
$db->exec("INSERT INTO office_buildings (address_line, postal_code, city) VALUES
    ('Marktstraat 10', '1234 AB', 'Rotterdam'),
    ('Kerklaan 5', '2345CD', 'Utrecht'),
    ('Kerklaan 5', '2345 CD', 'Utrecht')");
$resolver = new OfficeIntakeBuildingResolver($db);
$linked = $resolver->resolve(' marktstraat   10 ', '1234ab', 'ROTTERDAM');
if ($linked['status'] !== 'linked' || $linked['building_id'] !== '1') {
    throw new RuntimeException('exact_match_failed');
}
$ambiguous = $resolver->resolve('Kerklaan 5', '2345 cd', 'Utrecht');
if ($ambiguous['status'] !== 'ambiguous' || $ambiguous['building_id'] !== null || $ambiguous['candidates'] !== 2) {
    throw new RuntimeException('ambiguous_match_was_linked');
}
foreach ([
    ['Marktstraat 11', '1234AB', 'Rotterdam'],
    ['Marktstraat 10', '1234AB', 'Amsterdam'],
    ['', '1234AB', 'Rotterdam'],
] as $address) {
    $result = $resolver->resolve(...$address);
    if ($result['status'] !== 'required' || $result['building_id'] !== null) {
        throw new RuntimeException('unsafe_building_match');
    }
}
if ((int) $db->query('SELECT COUNT(*) FROM office_buildings')->fetchColumn() !== 3) {
    throw new RuntimeException('resolver_mutated_buildings');
}
echo "OfficeIntakeBuildingResolverMySqlTest OK\n";
