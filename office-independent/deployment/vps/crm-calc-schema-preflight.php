<?php
declare(strict_types=1);

/**
 * Read-only schema preflight: CRM Deal -> Calc draft before a project exists.
 * Only on isolated Office-test VPS with root-owned test DB configuration.
 * Never installs/migrates tables, changes flags, reads business rows or prints secrets.
 */
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--check') {
    fwrite(STDERR, "USAGE: php crm-calc-schema-preflight.php --check\n");
    exit(2);
}
if (!function_exists('posix_geteuid') || posix_geteuid() !== 0) {
    echo "CRM_CALC_SCHEMA_PREFLIGHT=BLOCKED_ROOT_CLI_REQUIRED\n";
    exit(2);
}
$blocked = [];
$check = static function (string $key, bool $ok) use (&$blocked): void {
    echo ($ok ? 'PASS ' : 'BLOCKED ').$key."\n";
    if (!$ok) $blocked[] = $key;
};
$readRootFile = static function (string $path): ?string {
    clearstatcache(true, $path);
    if (!is_file($path) || is_link($path) || fileowner($path) !== 0
        || (fileperms($path) & 0077) !== 0) {
        return null;
    }
    $raw = file_get_contents($path);
    return is_string($raw) ? $raw : null;
};
$base = '/srv/brebo/office-test';
$release = realpath($base.'/current');
$releaseOk = is_string($release)
    && preg_match('~^/srv/brebo/office-test/releases/[a-f0-9]{40}$~D', $release) === 1
    && trim((string) @file_get_contents($release.'/.office-source-sha')) === basename($release);
$check('ISOLATED_RELEASE', $releaseOk);
if ($releaseOk) {
    echo "OFFICE_TEST_RELEASE=".basename($release)."\n";
    foreach ([
        'public/index.php',
        'public/office-calc-crm-workbench.php',
        'src/Calculation/OfficeCalcCrmDraftAccess.php',
        'src/Calculation/OfficeCalcCrmDraftLineRepository.php',
        'src/Crm/OfficeCrmOpportunitySchema.php',
    ] as $file) {
        $check('RELEASE_FILE_'.str_replace('/', '_', $file), is_file($release.'/'.$file));
    }
}
$pool = $readRootFile('/etc/php/8.3/fpm/pool.d/brebo-office-test.conf');
$check('ISOLATED_FPM_POOL', $pool !== null && preg_match('/^\[brebo-office-test\]\s*$/m', $pool) === 1);

$raw = $readRootFile('/etc/brebo/office-test-db.env');
$check('ROOT_PRIVATE_TEST_DB_CONFIG', $raw !== null);
if ($raw === null || !$releaseOk || $pool === null) {
    echo "CRM_CALC_SCHEMA_PREFLIGHT=BLOCKED\n";
    exit(1);
}
$env = [];
foreach (explode("\n", $raw) as $line) {
    if (!preg_match('/^\s*(?:export\s+)?(OFFICE_TEST_DB_[A-Z_]+)=(.*)$/D', $line, $match)) {
        continue;
    }
    $v = trim($match[2]);
    if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
        $v = substr($v, 1, -1);
    }
    $env[$match[1]] = $v;
}
$identity = ($env['OFFICE_TEST_DB_NAME'] ?? '') === 'brebo_office_test'
    && ($env['OFFICE_TEST_DB_USER'] ?? '') === 'brebo_office_test'
    && in_array($env['OFFICE_TEST_DB_HOST'] ?? '', ['localhost', '127.0.0.1'], true);
$check('ISOLATED_DATABASE_IDENTITY', $identity);
if (!$identity || !isset($env['OFFICE_TEST_DB_PASSWORD'])) {
    echo "CRM_CALC_SCHEMA_PREFLIGHT=BLOCKED\n";
    exit(1);
}
try {
    $pdo = new PDO(
        'mysql:host='.$env['OFFICE_TEST_DB_HOST'].';dbname=brebo_office_test;charset=utf8mb4',
        $env['OFFICE_TEST_DB_USER'],
        $env['OFFICE_TEST_DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $check('CONNECTED_TEST_DATABASE', $pdo->query('SELECT DATABASE()')->fetchColumn() === 'brebo_office_test');
    $rows = $pdo->query(
        "SELECT TABLE_NAME,COLUMN_NAME,IS_NULLABLE FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA=DATABASE()"
    )->fetchAll(PDO::FETCH_ASSOC);
    $columns = [];
    foreach ($rows as $row) {
        $columns[$row['TABLE_NAME']][$row['COLUMN_NAME']] = $row;
    }
    $required = [
        'office_crm_relations' => ['id'],
        'office_crm_opportunities' => [
            'id', 'relation_id', 'title', 'currency', 'revision', 'stage', 'status',
        ],
        'office_crm_opportunity_activities' => ['id', 'opportunity_id', 'scheduled_at'],
        'office_operator_accounts' => ['id', 'active', 'can_edit_crm'],
        'office_calc_calculations' => ['id', 'project_id'],
        'office_calc_project_access' => ['calculation_id', 'project_id', 'operator_id', 'permission'],
        'office_calc_crm_drafts' => ['id', 'opportunity_id', 'crm_revision', 'project_id', 'created_by', 'calculation_id'],
        'calculations' => ['id', 'office_project_id', 'office_calculation_id', 'status'],
        'calculation_versions' => ['id', 'calculation_id', 'version_no', 'status'],
        'calculation_lines' => ['id', 'version_id', 'code', 'quantity', 'unit'],
    ];
    foreach ($required as $table => $fields) {
        foreach ($fields as $field) {
            $check('COLUMN_'.$table.'_'.$field, isset($columns[$table][$field]));
        }
    }
    // A Deal must never create two project-free drafts under concurrent requests.
    // Only an exact, non-nullable, single-column UNIQUE opportunity_id index suffices.
    $indexes = $pdo->query(
        "SELECT INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME
           FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_calc_crm_drafts'
          ORDER BY INDEX_NAME,SEQ_IN_INDEX"
    )->fetchAll(PDO::FETCH_ASSOC);
    $byName = [];
    foreach ($indexes as $index) {
        $byName[(string)$index['INDEX_NAME']]['unique'] = (int)$index['NON_UNIQUE'] === 0;
        $byName[(string)$index['INDEX_NAME']]['columns'][] = (string)$index['COLUMN_NAME'];
    }
    $uniqueOpportunity = false;
    foreach ($byName as $index) {
        if ($index['unique'] && $index['columns'] === ['opportunity_id']) {
            $uniqueOpportunity = true;
            break;
        }
    }
    $check('CRM_DRAFT_UNIQUE_OPPORTUNITY', $uniqueOpportunity);
    $check('CRM_DRAFT_OPPORTUNITY_REQUIRED',
        isset($columns['office_calc_crm_drafts']['opportunity_id'])
        && $columns['office_calc_crm_drafts']['opportunity_id']['IS_NULLABLE'] === 'NO');
    // The CRM draft and the Calc row must refer to the *same* canonical Office identity.
    $check('OFFICE_CANONICAL_CALC_PROJECT_OPTIONAL',
        isset($columns['office_calc_calculations']['project_id'])
        && $columns['office_calc_calculations']['project_id']['IS_NULLABLE'] === 'YES');
    $check('CRM_DRAFT_CALC_ID_REQUIRED',
        isset($columns['office_calc_crm_drafts']['calculation_id'])
        && $columns['office_calc_crm_drafts']['calculation_id']['IS_NULLABLE'] === 'NO');
    $check('CRM_DRAFT_UNIQUE_CALC_ID',
        isset($byName['uq_calc_draft_calculation'])
        && $byName['uq_calc_draft_calculation']['unique']
        && $byName['uq_calc_draft_calculation']['columns'] === ['calculation_id']);
    $foreignKeys = $pdo->query(
        "SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME
           FROM information_schema.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA=DATABASE()
            AND REFERENCED_TABLE_SCHEMA=DATABASE()
            AND TABLE_NAME IN ('office_calc_crm_drafts','calculations')"
    )->fetchAll(PDO::FETCH_ASSOC);
    $relations = [];
    foreach ($foreignKeys as $fk) {
        $relations[$fk['TABLE_NAME'].'.'.$fk['COLUMN_NAME']] =
            $fk['REFERENCED_TABLE_NAME'].'.'.$fk['REFERENCED_COLUMN_NAME'];
    }
    $check('DRAFT_LINKS_CANONICAL_CALC',
        ($relations['office_calc_crm_drafts.calculation_id'] ?? '') === 'office_calc_calculations.id');
    $check('CALC_LINKS_CANONICAL_OFFICE_CALC',
        ($relations['calculations.office_calculation_id'] ?? '') === 'office_calc_calculations.id');
    $check('CRM_DRAFT_PROJECT_OPTIONAL',
        isset($columns['office_calc_crm_drafts']['project_id'])
        && $columns['office_calc_crm_drafts']['project_id']['IS_NULLABLE'] === 'YES');
    $check('CALC_PROJECT_OPTIONAL',
        isset($columns['calculations']['office_project_id'])
        && $columns['calculations']['office_project_id']['IS_NULLABLE'] === 'YES');
    echo "CRM_CALC_PROJECTLESS_BROWSER_E2E=NOT_VERIFIED\n";
    echo "CRM_CALC_ROUTES_AND_PERMISSIONS_E2E=NOT_VERIFIED\n";
    echo "CRM_CALC_SCHEMA_PREFLIGHT=".($blocked ? 'BLOCKED' : 'SCHEMA_ONLY_PASS')."\n";
    exit($blocked ? 1 : 0);
} catch (Throwable $ignored) {
    // Never echo exceptions: they can contain DSNs, SQL or secret values.
    echo "CRM_CALC_SCHEMA_PREFLIGHT=BLOCKED_TEST_DB_QUERY\n";
    exit(1);
}
