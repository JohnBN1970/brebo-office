<?php
declare(strict_types=1);

/**
 * Read-only schema preflight for the Building -> Housing type -> Quantity chain.
 * Run on Office-test VPS with OFFICE_TEST_CONFIG_PATH set to its protected config.
 * No DDL, no data changes, no customer data output.
 */
if (PHP_SAPI !== 'cli') exit(2);
$file = getenv('OFFICE_TEST_CONFIG_PATH');
if (is_string($file) && is_file($file)) {
    $c = require $file;
} else {
    // CLI does not inherit PHP-FPM pool env[] values. Read the existing pool
    // configuration only when explicitly requested; never print credentials.
    $pool = getenv('OFFICE_TEST_FPM_POOL_PATH');
    if (!is_string($pool) || $pool === '' || !is_file($pool) || !is_readable($pool)) {
        echo "BLOCK_CONFIG\n"; exit(2);
    }
    $lines = file($pool, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) { echo "BLOCK_CONFIG\n"; exit(2); }
    $c = [];
    $keys = ['OFFICE_INTAKE_PDO_DSN', 'OFFICE_INTAKE_DB_USER', 'OFFICE_INTAKE_DB_PASSWORD', 'OFFICE_BASE_PATH'];
    foreach ($lines as $line) {
        if (!preg_match('/^\\s*env\\[([A-Z0-9_]+)\\]\\s*=\\s*(.*?)\\s*$/', $line, $match)) continue;
        if (!in_array($match[1], $keys, true)) continue;
        $value = trim($match[2]);
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
            $value = substr($value, 1, -1);
        }
        // Reject references or escaped values that need FPM-specific expansion.
        if (str_contains($value, '$') || str_contains($value, '\\\\')) {
            echo "BLOCK_CONFIG_FORMAT\n"; exit(2);
        }
        $c[$match[1]] = $value;
    }
}
if (!is_array($c) || !isset($c['OFFICE_INTAKE_PDO_DSN'], $c['OFFICE_INTAKE_DB_USER'], $c['OFFICE_INTAKE_DB_PASSWORD'], $c['OFFICE_BASE_PATH'])) {
    echo "BLOCK_CONFIG\n"; exit(2);
}
$dsn = (string)($c['OFFICE_INTAKE_PDO_DSN'] ?? '');
if (($c['OFFICE_BASE_PATH'] ?? '') !== '/office-test'
    || !preg_match('/(?:^|;)dbname=u213420663_office_test(?:;|$)/', $dsn)
    || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);/', $dsn)) {
    echo "BLOCK_TEST_SCOPE\n"; exit(3);
}
try {
    $db = new PDO($dsn, (string)($c['OFFICE_INTAKE_DB_USER'] ?? ''), (string)($c['OFFICE_INTAKE_DB_PASSWORD'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $wanted = [
        'office_buildings' => ['id', 'building_name'],
        'office_project_buildings' => ['project_id', 'building_id'],
        'office_housing_types' => ['id', 'building_id', 'code', 'name'],
        'office_project_housing_types' => ['project_id', 'building_id', 'housing_type_id', 'dwelling_count'],
        'office_project_calculation_scopes' => ['id', 'project_id', 'building_id', 'housing_type_id', 'scope_kind', 'calc_calculation_id'],
        'office_scope_quantity_lines' => ['id', 'scope_id', 'line_code', 'description', 'unit', 'quantity_per_dwelling'],
        'office_process_form_instances' => ['id', 'document_code', 'context_type', 'context_id', 'revision'],
        'office_process_form_events' => ['form_id', 'revision', 'snapshot_json'],
    ];
    $stmt = $db->query('SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()');
    $actual = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $actual[(string)$row['TABLE_NAME']][(string)$row['COLUMN_NAME']] = true;
    }
    $missing = false;
    foreach ($wanted as $table => $columns) {
        if (!isset($actual[$table])) {
            echo "MISSING_TABLE=$table\n";
            $missing = true;
            continue;
        }
        foreach ($columns as $column) {
            if (!isset($actual[$table][$column])) {
                echo "MISSING_COLUMN=$table.$column\n";
                $missing = true;
            }
        }
        if (count(array_diff($columns, array_keys($actual[$table]))) === 0) echo "READY_SCHEMA=$table\n";
    }
    echo $missing ? "BUILDING_QUANTITY_PREFLIGHT=BLOCKED\n" : "BUILDING_QUANTITY_PREFLIGHT=READY_SCHEMA_ONLY\n";
    exit($missing ? 4 : 0);
} catch (Throwable $e) {
    echo "BUILDING_QUANTITY_PREFLIGHT=DB_ERROR\n";
    exit(5);
}
