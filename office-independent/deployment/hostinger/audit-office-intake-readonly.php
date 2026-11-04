<?php

declare(strict_types=1);

/**
 * Office-test intake discovery. CLI-only, SELECT/metadata only, no changes.
 * Prints schema metadata only: no credentials, customer data or row values.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (getenv('OFFICE_BASE_PATH') !== '/office-test') {
    fwrite(STDERR, "ABORT: OFFICE_BASE_PATH must be /office-test\n");
    exit(2);
}
$configPath = getenv('OFFICE_TEST_CONFIG_PATH');
if (!is_string($configPath) || $configPath !==
    '/home/u213420663/domains/brebobv.nl/office_calc_test/config/config.php'
    || !is_file($configPath)) {
    fwrite(STDERR, "ABORT: isolated Office-test config unavailable\\n");
    exit(2);
}
$config = require $configPath;
if (!is_array($config) || ($config['OFFICE_BASE_PATH'] ?? null) !== '/office-test') {
    fwrite(STDERR, "ABORT: invalid Office-test config\\n");
    exit(2);
}
$dsn = $config['OFFICE_INTAKE_PDO_DSN'] ?? null;
$user = $config['OFFICE_INTAKE_DB_USER'] ?? null;
$password = $config['OFFICE_INTAKE_DB_PASSWORD'] ?? null;
if (!is_string($dsn) ||
    !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D', $dsn)
    || $user !== 'u213420663_office_test'
    || !is_string($password) || $password === '') {
    fwrite(STDERR, "ABORT: Office-test database connection not configured\n");
    exit(2);
}
try {
    $db = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $schema = $db->query('SELECT DATABASE()')->fetchColumn();
    if (!is_string($schema) || $schema !== 'u213420663_office_test') {
        fwrite(STDERR, "ABORT: unexpected database\n");
        exit(2);
    }
    $db->exec('SET TRANSACTION READ ONLY');
    $db->beginTransaction();
    $targets = [
        'office_buildings',
        'office_project_buildings',
        'office_projects',
        'office_crm_relations',
        'office_crm_relation_roles',
        'office_intakes',
        'office_crm_leads',
        'office_intake_requests',
        'office_intake_request_disciplines',
        'office_requests',
        'office_opportunities',
        'office_crm_opportunities',
        'office_intake_events',
    ];
    $statement = $db->prepare(
        'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION'
    );
    foreach ($targets as $table) {
        $statement->execute([$schema, $table]);
        $columns = $statement->fetchAll(PDO::FETCH_COLUMN, 1);
        echo $table . ': ' . ($columns === [] ? 'ABSENT' : implode(', ', $columns)) . PHP_EOL;
    }
    $db->rollBack();
    echo "READ_ONLY_AUDIT_COMPLETE\n";
} catch (Throwable $exception) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, "ABORT: database audit failed (details suppressed)\n");
    exit(1);
}
