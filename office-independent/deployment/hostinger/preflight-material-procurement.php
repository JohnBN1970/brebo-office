<?php
declare(strict_types=1);

/**
 * Read-only Office-test migration preflight. No CREATE/ALTER/INSERT statements.
 * Exit nonzero on missing canonical dependencies or incompatible existing tables.
 */
if (PHP_SAPI !== 'cli' || getenv('OFFICE_TEST_MATERIAL_PREFLIGHT') !== 'READ_ONLY') {
    fwrite(STDERR, "PREFLIGHT=DENIED\n");
    exit(2);
}
$dsn = getenv('OFFICE_INTAKE_PDO_DSN');
if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:')) {
    fwrite(STDERR, "PREFLIGHT=INVALID_DSN\n");
    exit(2);
}
try {
    $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('SET SESSION TRANSACTION READ ONLY');
    $db->beginTransaction();
    $required = [
        'office_calc_crm_acceptance_transfers',
        'office_calc_work_budget_baselines',
        'office_calc_work_budget_lines',
        'office_projects',
        'office_project_buildings',
    ];
    $new = [
        'office_work_budget_material_identity',
        'office_work_budget_purchase_orders',
        'office_work_budget_purchase_order_lines',
        'office_work_budget_receipts',
    ];
    $query = $db->query(
        "SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
    );
    $tables = [];
    foreach ($query as $row) $tables[$row['TABLE_NAME']] = $row['ENGINE'];
    $missing = array_values(array_filter($required, static fn(string $t): bool => !isset($tables[$t])));
    $existing = array_values(array_filter($new, static fn(string $t): bool => isset($tables[$t])));
    $notInnoDb = array_values(array_filter(
        array_merge($required, $existing),
        static fn(string $t): bool => isset($tables[$t]) && strcasecmp((string)$tables[$t], 'InnoDB') !== 0
    ));
    $count = (int)$db->query('SELECT COUNT(*) FROM office_calc_work_budget_baselines WHERE locked_at IS NOT NULL')->fetchColumn();
    $db->commit();
    echo 'PREFLIGHT=' . (($missing === [] && $notInnoDb === []) ? 'READY' : 'BLOCKED') . "\n";
    echo 'MISSING_REQUIRED=' . implode(',', $missing) . "\n";
    echo 'EXISTING_OPTIONAL=' . implode(',', $existing) . "\n";
    echo 'NON_INNODB=' . implode(',', $notInnoDb) . "\n";
    echo 'LOCKED_BASELINES=' . $count . "\n";
    echo "MUTATIONS=NONE\n";
    exit(($missing === [] && $notInnoDb === []) ? 0 : 1);
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    fwrite(STDERR, 'PREFLIGHT=ERROR CLASS=' . get_class($error) . "\n");
    exit(1);
}
