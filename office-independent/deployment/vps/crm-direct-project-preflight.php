<?php
declare(strict_types=1);

/**
 * Read-only Office-test VPS readiness for direct CRM -> native project transfer.
 *
 * Requires privileged CLI solely to read root-owned pool/database configuration.
 * It never executes DDL/DML, changes flags, prints secrets or creates projects.
 */
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--check') {
    fwrite(STDERR, "USAGE: php crm-direct-project-preflight.php --check\n");
    exit(2);
}
if (!function_exists('posix_geteuid') || posix_geteuid() !== 0) {
    fwrite(STDERR, "CRM_DIRECT_PREFLIGHT=BLOCKED_ROOT_CLI_REQUIRED\n");
    exit(2);
}
$errors = [];
$pass = static function (string $name): void { echo "PASS $name\n"; };
$fail = static function (string $name) use (&$errors): void {
    echo "BLOCKED $name\n";
    $errors[] = $name;
};
$privateFile = static function (string $path): ?string {
    clearstatcache(true, $path);
    if (!is_file($path) || is_link($path) || fileowner($path) !== 0
        || (fileperms($path) & 0077) !== 0) return null;
    $data = file_get_contents($path);
    return is_string($data) ? $data : null;
};

$root = '/srv/brebo/office-test';
$release = realpath($root.'/current');
if (!is_string($release) || !preg_match('~^/srv/brebo/office-test/releases/[a-f0-9]{40}$~D', $release)
    || trim((string)@file_get_contents($release.'/.office-source-sha')) !== basename($release)) {
    $fail('RELEASE_IDENTITY');
} else {
    $pass('RELEASE_IDENTITY');
    echo 'OFFICE_TEST_RELEASE='.basename($release)."\n";
    foreach ([
        'src/Crm/OfficeCrmDirectProjectAcceptance.php',
        'src/Crm/OfficeCrmDirectProjectTransfer.php',
        'src/Crm/OfficeCrmNativeProjectAdapter.php',
        'src/Crm/OfficeCrmProjectHandoff.php',
        'public/office-crm-opportunity-transition.php',
    ] as $file) {
        if (is_file($release.'/'.$file)) $pass('RELEASE_FILE_'.basename($file));
        else $fail('RELEASE_FILE_MISSING_'.basename($file));
    }
}

$pool = $privateFile('/etc/php/8.3/fpm/pool.d/brebo-office-test.conf');
if ($pool === null || !preg_match('/^\[brebo-office-test\]\s*$/m', $pool)) {
    $fail('ISOLATED_FPM_POOL');
} else {
    $pass('ISOLATED_FPM_POOL');
    foreach ([
        'OFFICE_VPS_TEST_ROUTER_ENABLED',
        'OFFICE_TEST_CRM_DIRECT_PROJECT_TRANSFER_ENABLED',
        'OFFICE_TEST_CRM_DIRECT_PROJECT_TRANSFER_SCHEMA_VERIFIED',
    ] as $flag) {
        $enabled = preg_match(
            '/^env\['.preg_quote($flag,'/').'\]\s*=\s*["\x27]?enabled["\x27]?\s*$/m',
            $pool
        ) === 1;
        echo 'FPM_FLAG_'.$flag.'='.($enabled ? 'ENABLED' : 'NOT_ENABLED')."\n";
    }
}

$raw = $privateFile('/etc/brebo/office-test-db.env');
$env = [];
if ($raw === null) {
    $fail('ROOT_OWNED_TEST_DB_CONFIG');
} else {
    foreach (explode("\n", $raw) as $line) {
        if (preg_match('/^\s*(?:export\s+)?(OFFICE_TEST_DB_[A-Z_]+)=(.*)$/D', $line, $matches)) {
            $value = trim($matches[2]);
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'")
                && substr($value, -1) === $value[0]) $value = substr($value, 1, -1);
            $env[$matches[1]] = $value;
        }
    }
    $pass('ROOT_OWNED_TEST_DB_CONFIG');
}

if (($env['OFFICE_TEST_DB_NAME'] ?? '') !== 'brebo_office_test'
    || ($env['OFFICE_TEST_DB_USER'] ?? '') !== 'brebo_office_test'
    || !in_array($env['OFFICE_TEST_DB_HOST'] ?? '', ['localhost','127.0.0.1'], true)) {
    $fail('ISOLATED_DB_IDENTITY');
    echo "CRM_DIRECT_PREFLIGHT=BLOCKED\n";
    exit(1);
}
$pass('ISOLATED_DB_IDENTITY');

try {
    $db = new PDO(
        'mysql:host='.$env['OFFICE_TEST_DB_HOST'].';dbname=brebo_office_test;charset=utf8mb4',
        $env['OFFICE_TEST_DB_USER'],
        $env['OFFICE_TEST_DB_PASSWORD'] ?? '',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]
    );
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'brebo_office_test') {
        throw new RuntimeException('Unexpected database');
    }
    $pass('TEST_DATABASE_READ');
} catch (Throwable $ignored) {
    $fail('TEST_DATABASE_READ');
    echo "CRM_DIRECT_PREFLIGHT=BLOCKED\n";
    exit(1);
}

$required = [
    'office_projects'=>['id','title','status'],
    'office_project_memberships'=>['project_id','operator_id','permission'],
    'office_operator_accounts'=>['id','active'],
    'office_module_permissions'=>['operator_id','module','permission'],
    'office_crm_opportunities'=>['id','relation_id','title','stage','status'],
    'office_crm_relations'=>['id'],
    'office_calc_crm_acceptance_transfers'=>['crm_opportunity_id','office_project_id'],
    'office_crm_direct_project_transfers'=>['opportunity_id','project_id','relation_id'],
];
$stmt = $db->query(
    "SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,DATA_TYPE
     FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()"
);
$columns = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
    $columns[$column['TABLE_NAME']][$column['COLUMN_NAME']] = $column;
}
$engineStmt=$db->query(
    "SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()"
);
$engines=$engineStmt->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($required as $table=>$fields) {
    if (($engines[$table] ?? null) !== 'InnoDB'
        || array_diff($fields, array_keys($columns[$table] ?? [])) !== []) {
        $fail('SCHEMA_'.$table);
    } else $pass('SCHEMA_'.$table);
}

$projectColumns = $columns['office_projects'] ?? [];
foreach ($projectColumns as $name=>$meta) {
    if (in_array($name,['id','title','status','code','description'],true)) continue;
    if ($meta['IS_NULLABLE'] === 'NO' && $meta['COLUMN_DEFAULT'] === null
        && !str_contains((string)$meta['EXTRA'],'auto_increment')) {
        $fail('UNMAPPED_REQUIRED_PROJECT_FIELD_'.$name);
    }
}
if (isset($projectColumns['id'])) {
    if (str_contains((string)$projectColumns['id']['EXTRA'],'auto_increment')) $pass('PROJECT_AUTO_INCREMENT');
    else $fail('PROJECT_AUTO_INCREMENT');
}
if (isset($projectColumns['status'])) {
    $type = (string)$projectColumns['status']['COLUMN_TYPE'];
    if (!str_starts_with($type,'enum(') || str_contains($type,"'preparation'")) $pass('PROJECT_PREPARATION_STATUS');
    else $fail('PROJECT_PREPARATION_STATUS');
}
foreach ([
    ['office_crm_direct_project_transfers','project_id','office_projects','id'],
    ['office_crm_direct_project_transfers','opportunity_id','office_crm_opportunities','id'],
    ['office_crm_direct_project_transfers','relation_id','office_crm_relations','id'],
] as [$from,$source,$to,$target]) {
    $a=(string)($columns[$from][$source]['COLUMN_TYPE']??'');
    $b=(string)($columns[$to][$target]['COLUMN_TYPE']??'');
    $key='KEY_TYPE_'.$from.'_'.$source;
    if ($a!=='' && $a===$b) $pass($key); else $fail($key);
}

$foreignStmt=$db->query(
    "SELECT COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME
     FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_crm_direct_project_transfers'
       AND REFERENCED_TABLE_NAME IS NOT NULL"
);
$foreign=[];
foreach ($foreignStmt->fetchAll(PDO::FETCH_ASSOC) as $fk) {
    $foreign[$fk['COLUMN_NAME']]=$fk['REFERENCED_TABLE_NAME'].'.'.$fk['REFERENCED_COLUMN_NAME'];
}
foreach ([
    'project_id'=>'office_projects.id',
    'opportunity_id'=>'office_crm_opportunities.id',
    'relation_id'=>'office_crm_relations.id',
] as $field=>$expected) {
    if (($foreign[$field]??null)===$expected) $pass('FK_'.$field); else $fail('FK_'.$field);
}

foreach ([
    ['office_module_permissions','permission',"'create'"],
    ['office_module_permissions','module',"'projects'"],
    ['office_project_memberships','permission',"'manage'"],
] as [$table,$field,$value]) {
    $type=(string)($columns[$table][$field]['COLUMN_TYPE']??'');
    if ($type!=='' && (!str_starts_with($type,'enum(') || str_contains($type,$value))) {
        $pass('PERMISSION_SCHEMA_'.$table.'_'.$field);
    } else $fail('PERMISSION_SCHEMA_'.$table.'_'.$field);
}

// Never print permissions granted to a named operator, customer data, or DB secrets.
if ($errors !== []) {
    echo "CRM_DIRECT_PREFLIGHT=BLOCKED\n";
    exit(1);
}
echo "CRM_DIRECT_PREFLIGHT=READY_FOR_EXPLICIT_TEST_ACTIVATION\n";
