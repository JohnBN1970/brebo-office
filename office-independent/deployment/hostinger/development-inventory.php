<?php
declare(strict_types=1);
// CLI only; never emit credentials, sessions or business data.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
$base = getenv('HOME') . '/domains/brebobv.nl/office_calc_test';
$config = require $base . '/config/config.php';
if (($config['OFFICE_BASE_PATH'] ?? '') !== '/office-test'
    || !preg_match('/^mysql:host=(localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D', $config['OFFICE_INTAKE_PDO_DSN'] ?? '')
    || ($config['OFFICE_INTAKE_DB_USER'] ?? '') !== 'u213420663_office_test') {
    throw new RuntimeException('ISOLATED_TEST_CONFIG_REQUIRED');
}
$active = realpath($config['OFFICE_PRIVATE_ROOT'] ?? '');
if (!$active || !str_starts_with($active, $base . '/release-') || !str_ends_with($active, '/private') || is_link($config['OFFICE_PRIVATE_ROOT'])) {
    throw new RuntimeException('INVALID_TEST_RELEASE');
}
echo 'CLI_MBSTRING_AVAILABLE=' . (function_exists('mb_strlen') ? 'yes' : 'no') . "\n";
echo 'ACTIVE_RELEASE=' . basename(dirname($active)) . "\n";
foreach (['OFFICE_LOGIN_ENABLED','OFFICE_CRM_SESSION_MODE','OFFICE_NATIVE_PROJECTS_ENABLED','OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED','OFFICE_TEST_PROJECT_KICKSTART_ENABLED'] as $flag) {
    echo $flag . '=' . (($config[$flag] ?? '') === 'enabled' ? 'enabled' : 'disabled') . "\n";
}
$db = new PDO($config['OFFICE_INTAKE_PDO_DSN'], $config['OFFICE_INTAKE_DB_USER'], $config['OFFICE_INTAKE_DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
sort($tables);
foreach (['office_projects','office_operator_accounts','office_operator_sessions','office_project_memberships','office_user_roles','office_roles','office_module_permissions'] as $table) {
    echo 'SCHEMA_' . $table . '=' . json_encode($db->query('DESCRIBE `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC), JSON_THROW_ON_ERROR) . "\n";
}

echo 'TABLES=' . json_encode($tables, JSON_THROW_ON_ERROR) . "\n";
$manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$counts = ['same'=>0,'different'=>0,'missing'=>0];
foreach ($manifest as $path => $hash) {
    if (!preg_match('~^(src|public|schema|deployment)/[A-Za-z0-9_./-]+$~D', $path) || str_contains($path, '..')) throw new RuntimeException('INVALID_MANIFEST_PATH');
    $file = $active . '/' . $path;
    $status = !is_file($file) ? 'missing' : (hash_equals($hash, hash_file('sha256', $file)) ? 'same' : 'different');
    ++$counts[$status];
    if ($status !== 'same' && preg_match('~^(public/|src/Project/)~', $path)) echo 'DRIFT_' . strtoupper($status) . '=' . $path . "\n";
}
echo 'SOURCE_COMPARISON=' . json_encode($counts, JSON_THROW_ON_ERROR) . "\n";
$router = getenv('HOME') . '/domains/brebobv.nl/public_html/office-test/index.php';
echo 'ROUTER_CONFIG_REFERENCE=' . (str_contains(file_get_contents($router), 'office_calc_test/config/config.php') ? 'yes' : 'no') . "\n";
echo "OFFICE_TEST_INVENTORY_COMPLETE\n";
