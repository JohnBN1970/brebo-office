<?php
declare(strict_types=1);

/**
 * Read-only privileged preflight for the isolated Office-test VPS Startdossier.
 * No migrations, writes, flag changes, user impersonation or credential output.
 */
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--check') {
    fwrite(STDERR, "USAGE: php startdossier-preflight.php --check\n");
    exit(2);
}
if (!function_exists('posix_geteuid') || posix_geteuid() !== 0) {
    echo "STARTDOSSIER_PREFLIGHT=BLOCKED_ROOT_CLI_REQUIRED\n";
    exit(2);
}
$failed = false;
$check = static function (string $name, bool $ok) use (&$failed): void {
    echo ($ok ? 'PASS ' : 'BLOCKED ').$name."\n";
    if (!$ok) $failed = true;
};
$readPrivate = static function (string $path): ?string {
    clearstatcache(true, $path);
    if (!is_file($path) || is_link($path) || fileowner($path) !== 0
        || (fileperms($path) & 0077) !== 0) return null;
    $data = file_get_contents($path);
    return is_string($data) ? $data : null;
};
$root = '/srv/brebo/office-test';
$release = realpath($root.'/current');
$validRelease = is_string($release)
    && preg_match('~^/srv/brebo/office-test/releases/[a-f0-9]{40}$~D', $release) === 1
    && trim((string)@file_get_contents($release.'/.office-source-sha')) === basename($release);
$check('RELEASE_IDENTITY', $validRelease);
if ($validRelease) {
    echo 'OFFICE_TEST_RELEASE='.basename($release)."\n";
    foreach ([
        'src/Project/OfficeProjectKickstartPolicy.php',
        'src/Project/OfficeProjectKickstartRepository.php',
        'src/Project/OfficeProjectKickstartQuestionsRepository.php',
        'src/Project/OfficeProjectKickstartActionHandler.php',
        'src/Project/OfficeProjectExecutionStartGate.php',
        'public/office-project.php',
    ] as $file) {
        $check('RELEASE_FILE_'.basename($file), is_file($release.'/'.$file));
    }
}
$pool = $readPrivate('/etc/php/8.3/fpm/pool.d/brebo-office-test.conf');
$check('ISOLATED_FPM_POOL', $pool !== null && preg_match('/^\\[brebo-office-test\\]\\s*$/m', $pool) === 1);
if ($pool !== null) {
    foreach (['OFFICE_VPS_TEST_ROUTER_ENABLED','OFFICE_TEST_PROJECT_KICKSTART_ENABLED'] as $flag) {
        $enabled = preg_match('/^env\\['.preg_quote($flag,'/').'\\]\\s*=\\s*["\\x27]?enabled["\\x27]?\\s*$/m', $pool) === 1;
        echo 'FPM_FLAG_'.$flag.'='.($enabled ? 'ENABLED' : 'NOT_ENABLED')."\n";
        if ($flag === 'OFFICE_TEST_PROJECT_KICKSTART_ENABLED') $check('KICKSTART_FLAG', $enabled);
    }
}
$raw = $readPrivate('/etc/brebo/office-test-db.env');
$check('ROOT_OWNED_TEST_DB_CONFIG', $raw !== null);
$env = [];
if ($raw !== null) {
    foreach (explode("\n", $raw) as $line) {
        if (preg_match('/^\\s*(?:export\\s+)?(OFFICE_TEST_DB_[A-Z_]+)=(.*)$/D', $line, $m)) {
            $value = trim($m[2]);
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'")
                && substr($value, -1) === $value[0]) $value = substr($value, 1, -1);
            $env[$m[1]] = $value;
        }
    }
}
$identity = ($env['OFFICE_TEST_DB_NAME'] ?? '') === 'brebo_office_test'
    && ($env['OFFICE_TEST_DB_USER'] ?? '') === 'brebo_office_test'
    && in_array($env['OFFICE_TEST_DB_HOST'] ?? '', ['localhost','127.0.0.1'], true);
$check('ISOLATED_DB_IDENTITY', $identity);
if ($identity) {
    try {
        $db = new PDO(
            'mysql:host='.$env['OFFICE_TEST_DB_HOST'].';dbname=brebo_office_test;charset=utf8mb4',
            $env['OFFICE_TEST_DB_USER'],
            $env['OFFICE_TEST_DB_PASSWORD'] ?? '',
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]
        );
        $check('TEST_DATABASE_READ', $db->query('SELECT DATABASE()')->fetchColumn() === 'brebo_office_test');
        $stmt = $db->query("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()");
        $columns = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $columns[$row['TABLE_NAME']][$row['COLUMN_NAME']] = true;
        foreach ([
            'office_project_start_dossiers'=>['id','project_id','scope_type','scope_id','revision','project_leader_user_id','status','items_json','critical_open_points'],
            'office_project_start_approvals'=>['dossier_id','dossier_revision','actor_user_id','decision'],
            'office_project_start_events'=>['dossier_id','dossier_revision','actor_user_id','event_type'],
            'office_project_start_questions'=>['dossier_id','is_critical','decision_text'],
            'office_projects'=>['id','status'],
            'office_project_memberships'=>['project_id','operator_id','permission'],
            'office_operator_accounts'=>['id','active'],
        ] as $table=>$required) {
            $check('SCHEMA_'.$table, array_diff($required,array_keys($columns[$table] ?? [])) === []);
        }
    } catch (Throwable $error) {
        $check('TEST_DATABASE_READ', false);
    }
}
echo "STARTDOSSIER_TWO_USER_HTTP_ACCEPTANCE=NOT_VERIFIED\n";
echo 'STARTDOSSIER_PREFLIGHT='.($failed ? 'BLOCKED' : 'READY_FOR_FUNCTIONAL_ACCEPTANCE')."\n";
exit($failed ? 1 : 0);
