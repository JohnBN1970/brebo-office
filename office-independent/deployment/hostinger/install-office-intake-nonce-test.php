<?php
declare(strict_types=1);

/** Explicit, CLI-only, additive Office-test replay nonce table installer. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
    || getenv('OFFICE_INTAKE_NONCE_INSTALL') !== 'explicit') {
    fwrite(STDERR, "ABORT: isolated test approval required\n");
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
require_once dirname(__DIR__, 2) . '/src/Intake/OfficeIntakeReplayGuard.php';
try {
    $db = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'u213420663_office_test') {
        throw new RuntimeException('database_not_office_test');
    }
    $table = 'office_intake_signed_nonces';
    $check = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );
    $check->execute(['u213420663_office_test', $table]);
    if ((int) $check->fetchColumn() !== 0) {
        throw new RuntimeException('nonce_table_already_present_reinspect_first');
    }
    foreach (['office_intake_requests', 'office_intake_events', 'office_intake_request_disciplines'] as $required) {
        $check->execute(['u213420663_office_test', $required]);
        if ((int) $check->fetchColumn() !== 1) {
            throw new RuntimeException('canonical_intake_schema_missing');
        }
    }
    echo "OFFICE_TEST_NONCE_PREFLIGHT_OK\n";
    $db->exec(\Brebo\Office\Intake\OfficeIntakeReplayGuard::schema());
    $columns = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );
    $columns->execute(['u213420663_office_test', $table]);
    if ((int) $columns->fetchColumn() !== 3) {
        throw new RuntimeException('nonce_table_verification_failed');
    }
    echo "OFFICE_TEST_NONCE_SCHEMA_INSTALL_COMPLETE\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "ABORT: nonce installation failed; inspect database before retry\n");
    exit(1);
}
