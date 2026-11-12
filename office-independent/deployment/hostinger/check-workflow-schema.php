<?php
declare(strict_types=1);
/**
 * Read-only schema readiness probe. Never runs migrations or changes data.
 * Execute in office-test with existing OFFICE_INTAKE_PDO_DSN credentials.
 */
header('Content-Type: text/plain; charset=utf-8');
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (getenv('OFFICE_BASE_PATH') !== '/office-test') {
    fwrite(STDERR, "OFFICE_TEST_REQUIRED\n");
    exit(2);
}
try {
    $pdo = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
        getenv('OFFICE_INTAKE_DB_USER') ?: '',
        getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
    $expected = [
        'office_workflow_blueprints' => ['id','tenant_id','blueprint_key'],
        'office_workflow_blueprint_versions' => [
            'id','blueprint_id','version_number','status','definition_json',
            'definition_sha256','created_by','published_by','published_at'
        ],
        'office_workflow_case_bindings' => [
            'id','tenant_id','version_id','case_type','case_id','current_node'
        ],
    ];
    $ready = true;
    foreach ($expected as $table=>$columns) {
        $stmt = $pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        $present = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $missing = array_values(array_diff($columns, $present));
        echo $table . '=' . ($missing === [] ? 'OK' : 'MISSING_COLUMNS') . "\n";
        if ($missing !== []) {
            $ready = false;
            echo 'missing=' . implode(',', $missing) . "\n";
        }
    }
    echo 'WORKFLOW_SCHEMA=' . ($ready ? 'READY' : 'NOT_READY') . "\n";
    exit($ready ? 0 : 1);
} catch (Throwable $e) {
    error_log('WORKFLOW_SCHEMA_CHECK ' . get_class($e));
    fwrite(STDERR, "WORKFLOW_SCHEMA=UNAVAILABLE\n");
    exit(2);
}
