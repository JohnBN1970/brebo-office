<?php

declare(strict_types=1);

/** CLI-only metadata audit. No schema, data, configuration or routes are changed. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$fail = static function (string $code): never { echo "BLOCK $code\n"; exit(2); };
$configFile = (getenv('HOME') ?: '') . '/domains/brebobv.nl/office_calc_test/config/config.php';
if (!is_file($configFile)) $fail('TEST_CONFIG_MISSING');
try {
    $config = require $configFile;
    if (!is_array($config)) $fail('TEST_CONFIG_INVALID');
    $allowed = [
        'mysql:host=localhost;dbname=u213420663_office_test;charset=utf8mb4',
        'mysql:host=127.0.0.1;dbname=u213420663_office_test;charset=utf8mb4',
    ];
    if (($config['OFFICE_BASE_PATH'] ?? null) !== '/office-test'
        || !in_array($config['OFFICE_INTAKE_PDO_DSN'] ?? null, $allowed, true)
        || ($config['OFFICE_INTAKE_DB_USER'] ?? null) !== 'u213420663_office_test') $fail('TEST_ISOLATION');
    $root = realpath((string) ($config['OFFICE_PRIVATE_ROOT'] ?? ''));
    $testRoot = realpath(dirname(dirname($configFile)));
    if ($root === false || $testRoot === false
        || !str_starts_with($root, $testRoot . '/')
        || !preg_match('~/release-[^/]+/private$~D', $root)) $fail('TEST_RELEASE');
    echo 'RELEASE_SHA256=' . hash('sha256', $root) . "\n";
    foreach (['src/Intake/OfficeMount.php', 'src/Intake/OfficeNavigation.php',
        'src/Finance/OfficeFinanceAccess.php', 'src/Finance/OfficeFinanceReadRepository.php',
        'src/Project/OfficeNativeProjectAccess.php', 'public/office-project.php'] as $file) {
        $path = $root . '/' . $file;
        echo 'FILE ' . $file . ' ' . (is_file($path) ? hash_file('sha256', $path) : 'MISSING') . "\n";
    }
    $db = new PDO($config['OFFICE_INTAKE_PDO_DSN'], $config['OFFICE_INTAKE_DB_USER'],
        $config['OFFICE_INTAKE_DB_PASSWORD'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $db->exec('SET TRANSACTION READ ONLY');
    $db->beginTransaction();
    $query = $db->query('SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION');
    $schema = [];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $table = (string) $row['TABLE_NAME'];
        if (preg_match('/^(office_|brebo_finance_|brebo_procurement_)/D', $table)
            && preg_match('/(project|building|permission|operator|quality|verification|deviation|risk|inspection|procurement|commitment|purchase|award|decision|receipt)/i', $table)) {
            $schema[$table][] = (string) $row['COLUMN_NAME'];
        }
    }
    foreach ($schema as $table => $columns) echo 'SCHEMA ' . $table . ' ' . implode(',', $columns) . "\n";
    foreach (['office_projects', 'office_buildings', 'office_project_buildings',
        'office_project_memberships', 'office_operator_accounts', 'office_module_permissions',
        'office_finance_project_mapping', 'brebo_finance_commitment', 'brebo_finance_commitment_line',
        'brebo_finance_performance_receipt', 'brebo_procurement_decision', 'brebo_procurement_award'] as $table) {
        echo (isset($schema[$table]) ? 'FOUND ' : 'MISSING ') . $table . "\n";
    }
    // Aggregate coverage only; never emit project/operator IDs, names or invoice data.
    if (isset($schema['office_finance_project_mapping'])
        && count(array_intersect(['verified', 'office_project_id', 'source_project_id'], $schema['office_finance_project_mapping'])) === 3) {
        $count = $db->query('SELECT COUNT(*) FROM office_finance_project_mapping WHERE verified=1')->fetchColumn();
        echo 'VERIFIED_FINANCE_PROJECT_MAPPINGS=' . (int) $count . "\n";
    }
    $db->rollBack();
    echo "AUDIT_COMPLETE_READ_ONLY\n";
    echo "INTEGRATION_NOT_ACCEPTED_SOURCE_AND_IDENTITY_REVIEW_REQUIRED\n";
} catch (Throwable $error) {
    // Connection details and exception messages can contain credentials or personal data.
    $fail('METADATA_AUDIT_FAILED');
}
