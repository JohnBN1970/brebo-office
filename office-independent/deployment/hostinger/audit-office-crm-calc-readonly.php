<?php
declare(strict_types=1);

/**
 * Read-only readiness check for a Deal -> Calc draft before project formation.
 * Run on the Office-test VPS with the same DB connection settings as Office.
 * Never creates tables, modifies settings or prints credentials.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (getenv('OFFICE_BASE_PATH') !== '/office-test') {
    fwrite(STDERR, "CRM_CALC_PREFLIGHT=BLOCKED:NOT_TEST_ENV\n");
    exit(2);
}
$root='/home/u213420663/domains/brebobv.nl/office_calc_test';
$path=getenv('OFFICE_TEST_CONFIG_PATH');
if ($path!==$root.'/config/config.php' || !is_file($path)) {
    fwrite(STDERR,"CRM_CALC_PREFLIGHT=BLOCKED:TEST_CONFIG_REQUIRED\n"); exit(2);
}
$config=require $path;
if (!is_array($config) || ($config['OFFICE_BASE_PATH']??null)!=='/office-test') {
    fwrite(STDERR,"CRM_CALC_PREFLIGHT=BLOCKED:NOT_TEST_CONFIG\n"); exit(2);
}
$dsn=$config['OFFICE_INTAKE_PDO_DSN']??null;
$user=$config['OFFICE_INTAKE_DB_USER']??null;
$password=$config['OFFICE_INTAKE_DB_PASSWORD']??null;
if (!is_string($dsn) || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$dsn) || $user!=='u213420663_office_test' || !is_string($password) || $password==='') {
    fwrite(STDERR,"CRM_CALC_PREFLIGHT=BLOCKED:DB_SCOPE_MISMATCH\n"); exit(2);
}
try {
    $db = new PDO($dsn,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') throw new RuntimeException('MYSQL_REQUIRED');
    $schema = (string)$db->query('SELECT DATABASE()')->fetchColumn();
    if ($schema !== 'u213420663_office_test') {
        throw new RuntimeException('NOT_TEST_SCHEMA');
    }
    $required = [
        'office_crm_opportunities' => ['id','relation_id','title','revision','stage','status'],
        'office_calc_crm_drafts' => ['id','opportunity_id','crm_revision','created_by','project_id'],
        'office_operator_accounts' => ['id','active','can_edit_crm'],
        'calculations' => ['id','office_project_id','office_calculation_id','status'],
        'calculation_versions' => ['id','calculation_id','version_no','status'],
        'calculation_lines' => ['id','version_id','code','quantity'],
    ];
    $check = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $missing = [];
    foreach ($required as $table => $columns) {
        $check->execute([$table]);
        $found = array_fill_keys($check->fetchAll(PDO::FETCH_COLUMN), true);
        foreach ($columns as $column) {
            if (!isset($found[$column])) $missing[] = $table.'.'.$column;
        }
    }
    $indexes = $db->prepare(
        "SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS columns_list
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_calc_crm_drafts' AND NON_UNIQUE=0
         GROUP BY INDEX_NAME"
    );
    $indexes->execute();
    $uniqueOpportunity = false;
    foreach ($indexes->fetchAll(PDO::FETCH_ASSOC) as $index) {
        if ($index['columns_list'] === 'opportunity_id') $uniqueOpportunity = true;
    }
    if (!$uniqueOpportunity) $missing[] = 'office_calc_crm_drafts.UNIQUE(opportunity_id)';
    if ($missing !== []) {
        sort($missing);
        echo "CRM_CALC_PREFLIGHT=BLOCKED\n";
        foreach ($missing as $item) echo "MISSING=".$item."\n";
        exit(1);
    }
    echo "CRM_CALC_PREFLIGHT=SCHEMA_OK\n";
    echo "OFFICE_TEST_BROWSER_E2E=NOT_VERIFIED\n";
    echo "CRM_CALC_RELEASE=BLOCKED_UNTIL_E2E\n";
} catch (Throwable $e) {
    // Do not leak DSNs, SQL errors, usernames or passwords in the console.
    fwrite(STDERR, "CRM_CALC_PREFLIGHT=BLOCKED:INSPECTION_FAILED\n");
    exit(2);
}
