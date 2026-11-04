<?php
declare(strict_types=1);

/**
 * CLI-only explicit additive installer for the isolated Office-test database.
 * Operates only after manual workflow_dispatch and verified scope/structure.
 * CREATE TABLE statements are additive but DDL cannot be rolled back in MySQL.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
    || getenv('OFFICE_INTAKE_SCHEMA_INSTALL') !== 'explicit') {
    fwrite(STDERR, "ABORT: explicit isolated test approval required\n");
    exit(2);
}
$root = '/home/u213420663/domains/brebobv.nl/office_calc_test';
$configPath = getenv('OFFICE_TEST_CONFIG_PATH');
if ($configPath !== $root . '/config/config.php' || !is_file($configPath)) {
    fwrite(STDERR, "ABORT: isolated config missing\n");
    exit(2);
}
$config = require $configPath;
if (!is_array($config) || ($config['OFFICE_BASE_PATH'] ?? null) !== '/office-test') {
    fwrite(STDERR, "ABORT: wrong environment\n");
    exit(2);
}
$dsn = $config['OFFICE_INTAKE_PDO_DSN'] ?? null;
$user = $config['OFFICE_INTAKE_DB_USER'] ?? null;
$password = $config['OFFICE_INTAKE_DB_PASSWORD'] ?? null;
if (!is_string($dsn)
    || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D', $dsn)
    || $user !== 'u213420663_office_test'
    || !is_string($password) || $password === '') {
    fwrite(STDERR, "ABORT: database scope mismatch\n");
    exit(2);
}
require_once dirname(__DIR__, 2) . '/src/Intake/OfficeCanonicalIntakeSchema.php';

try {
    $db = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'u213420663_office_test') {
        throw new RuntimeException('database_not_office_test');
    }
    // Validate all FK targets before the first DDL. No attempt to modify existing tables.
    $typeStmt = $db->prepare(
        "SELECT TABLE_NAME, COLUMN_TYPE, COLUMN_KEY, EXTRA, IS_NULLABLE "
        . "FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? "
        . "AND TABLE_NAME IN ('office_buildings', 'office_crm_relations') "
        . "AND COLUMN_NAME = 'id'"
    );
    $typeStmt->execute(['u213420663_office_test']);
    $types = [];
    foreach ($typeStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $types[$row['TABLE_NAME']] = $row;
    }
    foreach (['office_buildings', 'office_crm_relations'] as $table) {
        $row = $types[$table] ?? null;
        if (!is_array($row) || !preg_match('/^bigint(?:\\(20\\))? unsigned$/i', (string) $row['COLUMN_TYPE'])
            || $row['COLUMN_KEY'] !== 'PRI' || $row['IS_NULLABLE'] !== 'NO') {
            throw new RuntimeException('fk_target_unverified');
        }
    }
    $expected = [
        'office_intake_requests',
        'office_intake_request_disciplines',
        'office_intake_events',
    ];
    $lookup = $db->prepare(
        'SELECT TABLE_NAME FROM information_schema.TABLES '
        . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );
    foreach ($expected as $table) {
        $lookup->execute(['u213420663_office_test', $table]);
        if ($lookup->fetchColumn() !== false) {
            throw new RuntimeException('intake_table_already_present_reinspect_first');
        }
    }
    echo "OFFICE_TEST_INSTALL_PREFLIGHT_OK\n";
    foreach (\Brebo\Office\Intake\OfficeCanonicalIntakeSchema::statements() as $statement) {
        $db->exec($statement);
    }
    $columns = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );
    foreach ($expected as $table) {
        $columns->execute(['u213420663_office_test', $table]);
        $count = (int) $columns->fetchColumn();
        if ($count < 4) {
            throw new RuntimeException('created_table_verification_failed');
        }
        echo $table . ': PRESENT (' . $count . " columns)\n";
    }
    echo "OFFICE_TEST_INTAKE_SCHEMA_INSTALL_COMPLETE\n";
} catch (Throwable $exception) {
    // MySQL DDL auto-commits; a partial installation is deliberately not retried.
    fwrite(STDERR, "ABORT: installation failed; inspect partial state before retry\n");
    exit(1);
}
