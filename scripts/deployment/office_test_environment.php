<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
function officeTestEnvironment(): array {
    $base = getenv('HOME') . '/domains/brebobv.nl/office_calc_test';
    $path = $base . '/config/config.php';
    if (is_link($base) || is_link(dirname($path)) || is_link($path)) throw new RuntimeException('TEST_CONFIG_SYMLINK');
    $config = require $path;
    if (!is_array($config) || ($config['OFFICE_BASE_PATH'] ?? '') !== '/office-test'
        || !preg_match('/^mysql:host=(localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D', $config['OFFICE_INTAKE_PDO_DSN'] ?? '')
        || ($config['OFFICE_INTAKE_DB_USER'] ?? '') !== 'u213420663_office_test'
        || ($config['OFFICE_PUBLIC_HOST'] ?? '') !== 'brebobv.nl') throw new RuntimeException('ISOLATED_TEST_CONFIG_REQUIRED');
    $active = realpath($config['OFFICE_PRIVATE_ROOT'] ?? '');
    if (!$active || !str_starts_with($active, $base . '/release-') || !str_ends_with($active, '/private') || is_link($config['OFFICE_PRIVATE_ROOT'])) throw new RuntimeException('INVALID_TEST_RELEASE');
    return [$base,$path,$config,$active];
}
function officeTestDatabase(array $config): PDO {
    return new PDO($config['OFFICE_INTAKE_PDO_DSN'],$config['OFFICE_INTAKE_DB_USER'],$config['OFFICE_INTAKE_DB_PASSWORD']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
}
