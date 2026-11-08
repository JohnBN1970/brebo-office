<?php
declare(strict_types=1);
/** Read-only Office-test governance deployment readiness audit. No credentials output. */
if (PHP_SAPI !== 'cli' || getenv('OFFICE_BASE_PATH') !== '/office-test'
    || getenv('OFFICE_GOVERNANCE_RUNTIME_AUDIT') !== 'read-only') {
    fwrite(STDERR, "GOVERNANCE_RUNTIME_SCOPE_DENIED\n");
    exit(2);
}
$base = '/home/u213420663/domains/brebobv.nl/office_calc_test';
$configFile = $base . '/config/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "GOVERNANCE_RUNTIME_CONFIG_MISSING\n");
    exit(2);
}
$config = require $configFile;
if (!is_array($config) || ($config['OFFICE_BASE_PATH'] ?? null) !== '/office-test') {
    fwrite(STDERR, "GOVERNANCE_RUNTIME_WRONG_ENVIRONMENT\n");
    exit(2);
}
$dsn = $config['OFFICE_INTAKE_PDO_DSN'] ?? null;
$user = $config['OFFICE_INTAKE_DB_USER'] ?? null;
$password = $config['OFFICE_INTAKE_DB_PASSWORD'] ?? null;
if (!is_string($dsn)
    || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D', $dsn)
    || $user !== 'u213420663_office_test'
    || !is_string($password) || $password === '') {
    fwrite(STDERR, "GOVERNANCE_RUNTIME_DB_SCOPE_DENIED\n");
    exit(2);
}
$passed = true;
$check = static function (string $name, bool $ok) use (&$passed): void {
    echo $name . '=' . ($ok ? 'OK' : 'MISSING') . "\n";
    if (!$ok) $passed = false;
};
$root = $config['OFFICE_PRIVATE_ROOT'] ?? null;
$release = is_string($root) ? realpath($root) : false;
$realBase = realpath($base);
$validRelease = is_string($release) && $realBase !== false
    && str_starts_with($release, $realBase . DIRECTORY_SEPARATOR)
    && preg_match('~/release-[^/]+/private$~D', $release) === 1;
$check('ACTIVE_TEST_RELEASE', $validRelease);
if ($validRelease) {
    $projectFile = $release . '/public/office-project.php';
    $projectContent = is_file($projectFile) ? file_get_contents($projectFile) : false;
    foreach (['$financialSectionHtml .= $financeHtml;', '$financeHtml', 'OfficeProjectDetailV3View', 'OfficeProjectFinanceOverview', 'create_project_contract'] as $token) {
        echo 'PROJECT_FINANCE_MARKER_' . strtoupper(preg_replace('/[^a-zA-Z0-9]+/', '_', $token)) . '='
            . (is_string($projectContent) ? substr_count($projectContent, $token) : 0) . "\n";
    }
    if (is_string($projectContent)) {
        $pos = strrpos($projectContent, '$financeHtml');
        $window = $pos === false ? '' : substr($projectContent, max(0, $pos - 110), 230);
        echo 'PROJECT_FINANCE_LAST_USAGE_CONTEXT=' . preg_replace('/[^a-zA-Z0-9_$=;().\[\]{}+ \n-]/', '_', $window) . "\n";
    }
    echo 'OPTIONAL_PROJECT_FINANCE_PROCUREMENT_WIDGET='
        . (is_string($projectContent) && str_contains($projectContent, 'awardedProjectCommitment')
            && str_contains($projectContent, 'OfficeProcurementOrderSource') ? 'PRESENT' : 'NOT_INSTALLED')
        . "\n";
    foreach ([
        'public/office-governance.php',
        'src/Governance/bootstrap.php',
        'src/Governance/Contract/OfficeGovernanceStore.php',
        'src/Governance/OfficeGovernanceService.php',
        'src/Governance/OfficeGovernanceCsrf.php',
        'src/Governance/Infrastructure/PdoOfficeGovernanceStore.php',
    ] as $relative) {
        $check('RELEASE_' . str_replace(['/', '.', '-'], '_', $relative), is_file($release . '/' . $relative));
    }
}
$router = '/home/u213420663/domains/brebobv.nl/public_html/office-test/index.php';
$routerCode = is_file($router) ? file_get_contents($router) : false;
$check('ISOLATED_ROUTER', is_string($routerCode));
foreach (['kwaliteit', 'risicos', 'inkoop'] as $route) {
    $check('ROUTE_' . strtoupper($route), is_string($routerCode)
        && str_contains($routerCode, '/office-test/' . $route)
        && str_contains($routerCode, 'office-governance.php'));
}
foreach (['OFFICE_TEST_GOVERNANCE_ENABLED', 'OFFICE_TEST_GOVERNANCE_SCHEMA_VERIFIED',
    'OFFICE_CRM_SESSION_MODE'] as $flag) {
    $check('CONFIG_' . $flag, ($config[$flag] ?? null) === 'enabled');
}
$secret = $config['OFFICE_TEST_GOVERNANCE_CSRF_SECRET'] ?? null;
$check('CONFIG_GOVERNANCE_CSRF_SECRET', is_string($secret) && strlen($secret) >= 32);
try {
    $db = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'u213420663_office_test') {
        throw new RuntimeException('wrong_database');
    }
    $needed = [
        'office_quality_checks' => ['id','project_id','building_id','title','status','payload_json','revision','created_by','updated_by','created_at','updated_at'],
        'office_risks' => ['id','project_id','building_id','title','status','payload_json','revision','created_by','updated_by','created_at','updated_at'],
        'office_procurement_orders' => ['id','project_id','building_id','title','status','payload_json','revision','created_by','updated_by','created_at','updated_at'],
        'office_governance_commands' => ['operator_id','command_key','fingerprint','result_json'],
        'office_governance_audit' => ['id','module','record_id','project_id','building_id','revision','operator_id','snapshot_json','created_at'],
    ];
    $metadata = $db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
    $columns = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
    foreach ($needed as $table => $fields) {
        $metadata->execute(['u213420663_office_test', $table]);
        $engine = $metadata->fetchColumn();
        $columns->execute(['u213420663_office_test', $table]);
        $available = $columns->fetchAll(PDO::FETCH_COLUMN);
        $check('SCHEMA_' . strtoupper($table), $engine === 'InnoDB'
            && count(array_diff($fields, $available)) === 0);
    }
    $dependencies = [
        'office_operator_accounts' => ['id','username','active'],
        'office_project_memberships' => ['operator_id','project_id','permission'],
        'office_module_permissions' => ['operator_id','module','permission'],
        'office_crm_relations' => ['id','display_name','status'],
        'office_crm_relation_roles' => ['relation_id','role_code'],
        'office_inspections' => ['id','building_id','project_id'],
        'office_inspection_findings' => ['id','inspection_id','observation'],
    ];
    $dependenciesReady = true;
    foreach ($dependencies as $table => $fields) {
        $metadata->execute(['u213420663_office_test', $table]);
        $engine = $metadata->fetchColumn();
        $columns->execute(['u213420663_office_test', $table]);
        $available = $columns->fetchAll(PDO::FETCH_COLUMN);
        $valid = $engine === 'InnoDB' && count(array_diff($fields, $available)) === 0;
        $check('DEPENDENCY_' . strtoupper($table), $valid);
        if (!$valid) $dependenciesReady = false;
    }
    if ($dependenciesReady) {
        $reads = $db->query("SELECT module,COUNT(*) AS n FROM office_module_permissions WHERE module IN ('quality','risk','procurement') AND permission='read' GROUP BY module")->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach (['quality','risk','procurement'] as $module) {
            echo 'EXPLICIT_READ_GRANTS_' . strtoupper($module) . '=' . (int)($reads[$module] ?? 0) . "\n";
        }
        $activeOperators = (int)$db->query("SELECT COUNT(*) FROM office_operator_accounts WHERE active=1")->fetchColumn();
        $memberships = (int)$db->query("SELECT COUNT(*) FROM office_project_memberships")->fetchColumn();
        echo "ACTIVE_OPERATOR_COUNT=$activeOperators\n";
        echo "PROJECT_MEMBERSHIP_COUNT=$memberships\n";
        $managers = (int)$db->query("SELECT COUNT(DISTINCT a.id) FROM office_operator_accounts a JOIN office_project_memberships m ON m.operator_id=a.id WHERE a.active=1 AND m.permission='manage'")->fetchColumn();
        echo "ACTIVE_PROJECT_MANAGERS=$managers\n";
        $eligible = (int)$db->query("SELECT COUNT(DISTINCT a.id) FROM office_operator_accounts a JOIN office_project_memberships m ON m.operator_id=a.id JOIN office_module_permissions mp ON mp.operator_id=a.id AND mp.module='settings' AND mp.permission='manage' WHERE a.active=1 AND m.permission='manage'")->fetchColumn();
        echo "ACTIVE_PROJECT_MANAGERS_WITH_SETTINGS_MANDATE=$eligible\n";
        $otherPermissions = $db->query("SELECT module,permission,COUNT(*) AS count_records FROM office_module_permissions GROUP BY module,permission ORDER BY module,permission")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($otherPermissions as $entry) {
            $module = preg_replace('/[^a-z0-9_]/', '_', strtolower((string)$entry['module']));
            $permission = preg_replace('/[^a-z0-9_]/', '_', strtolower((string)$entry['permission']));
            echo "EXISTING_MODULE_RIGHT_".strtoupper($module)."_".strtoupper($permission)."=".(int)$entry['count_records']."\n";
        }
    }
    $foreign = $db->prepare('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND REFERENCED_TABLE_NAME IS NOT NULL AND TABLE_NAME IN (?,?,?)');
    $foreign->execute(['u213420663_office_test','office_quality_checks','office_risks','office_procurement_orders']);
    $keys = [];
    foreach ($foreign->fetchAll(PDO::FETCH_ASSOC) as $fk) {
        $keys[$fk['TABLE_NAME']][$fk['COLUMN_NAME']] = $fk['REFERENCED_TABLE_NAME'] . '.' . $fk['REFERENCED_COLUMN_NAME'];
    }
    foreach (['office_quality_checks','office_risks','office_procurement_orders'] as $table) {
        $check('FK_' . strtoupper($table), ($keys[$table]['project_id'] ?? null) === 'office_project_buildings.project_id'
            && ($keys[$table]['building_id'] ?? null) === 'office_project_buildings.building_id');
    }
} catch (Throwable $e) {
    $check('DATABASE_READONLY_AUDIT', false);
}
echo $passed ? "GOVERNANCE_RUNTIME_READY\n" : "GOVERNANCE_RUNTIME_NOT_READY\n";
