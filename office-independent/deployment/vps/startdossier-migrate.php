<?php
declare(strict_types=1);

/**
 * Isolated Office-test Startdossier schema installation.
 * --plan is read-only. --apply requires explicit confirmation and installs
 * only four reviewed additive tables. It never changes PHP-FPM flags.
 */
$mode = $argv[1] ?? '';
if (PHP_SAPI !== 'cli' || !in_array($mode, ['--plan', '--apply'], true)
    || ($mode === '--apply' && ($argv[2] ?? '') !== 'CONFIRM-OFFICE-TEST-STARTDOSSIER')) {
    fwrite(STDERR, "USAGE: sudo php startdossier-migrate.php --plan | --apply CONFIRM-OFFICE-TEST-STARTDOSSIER\n");
    exit(2);
}
if (!function_exists('posix_geteuid') || posix_geteuid() !== 0) {
    fwrite(STDERR, "BLOCKED ROOT_REQUIRED\n");
    exit(2);
}
$private = static function (string $path): string {
    clearstatcache(true, $path);
    if (!is_file($path) || is_link($path) || fileowner($path) !== 0
        || (fileperms($path) & 0077) !== 0) throw new RuntimeException('PRIVATE_CONFIG_INVALID');
    $value = file_get_contents($path);
    if (!is_string($value)) throw new RuntimeException('PRIVATE_CONFIG_UNREADABLE');
    return $value;
};
try {
    $root = '/srv/brebo/office-test';
    $release = realpath($root.'/current');
    if (!is_string($release)
        || preg_match('~^/srv/brebo/office-test/releases/[a-f0-9]{40}$~D', $release) !== 1
        || trim((string)@file_get_contents($release.'/.office-source-sha')) !== basename($release)) {
        throw new RuntimeException('RELEASE_IDENTITY');
    }
    echo 'OFFICE_TEST_RELEASE='.basename($release)."\n";
    $raw = $private('/etc/brebo/office-test-db.env');
    $env = [];
    foreach (explode("\n", $raw) as $line) {
        if (preg_match('/^\\s*(?:export\\s+)?(OFFICE_TEST_DB_[A-Z_]+)=(.*)$/D', $line, $m)) {
            $value = trim($m[2]);
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'")
                && substr($value, -1) === $value[0]) $value = substr($value, 1, -1);
            $env[$m[1]] = $value;
        }
    }
    if (($env['OFFICE_TEST_DB_NAME'] ?? '') !== 'brebo_office_test'
        || ($env['OFFICE_TEST_DB_USER'] ?? '') !== 'brebo_office_test'
        || !in_array($env['OFFICE_TEST_DB_HOST'] ?? '', ['localhost','127.0.0.1'], true)) {
        throw new RuntimeException('ISOLATED_DB_IDENTITY');
    }
    $db = new PDO(
        'mysql:host='.$env['OFFICE_TEST_DB_HOST'].';dbname=brebo_office_test;charset=utf8mb4',
        $env['OFFICE_TEST_DB_USER'], $env['OFFICE_TEST_DB_PASSWORD'] ?? '',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]
    );
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'brebo_office_test') {
        throw new RuntimeException('DATABASE_MISMATCH');
    }
    $schema = $release.'/schema/office-project-kickstart.sql';
    if (!is_file($schema) || is_link($schema)) throw new RuntimeException('SCHEMA_FILE_MISSING');
    $sql = file_get_contents($schema);
    if (!is_string($sql)) throw new RuntimeException('SCHEMA_UNREADABLE');
    $sql = preg_replace('/--[^\\n]*/', '', $sql);
    $statements = array_values(array_filter(array_map('trim', explode(';', $sql))));
    $tables = [
        'office_project_start_dossiers',
        'office_project_start_approvals',
        'office_project_start_events',
        'office_project_start_questions',
    ];
    if (count($statements) !== count($tables)) throw new RuntimeException('UNREVIEWED_SCHEMA_COUNT');
    foreach ($statements as $i => $statement) {
        if (!preg_match('/^CREATE TABLE IF NOT EXISTS '.preg_quote($tables[$i], '/').'\\s*\\(/D', $statement)
            || !preg_match('/ENGINE=InnoDB\\s+DEFAULT CHARSET=utf8mb4\\s*$/D', $statement)) {
            throw new RuntimeException('UNREVIEWED_SCHEMA_'.$i);
        }
    }
    $q = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'office_project_start_%'");
    $existing = $q->fetchAll(PDO::FETCH_COLUMN);
    $present = array_values(array_intersect($tables, $existing));
    if (count($present) !== 0 && count($present) !== count($tables)) {
        throw new RuntimeException('PARTIAL_SCHEMA_REQUIRES_MANUAL_REVIEW');
    }
    if (count($present) === count($tables)) {
        // Do not silently accept wrong columns in pre-existing tables.
        $columns = [];
        $q = $db->query("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()");
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) $columns[$row['TABLE_NAME']][$row['COLUMN_NAME']] = true;
        foreach ([
            'office_project_start_dossiers'=>['id','project_id','scope_type','scope_id','revision','project_leader_user_id','status','items_json','critical_open_points'],
            'office_project_start_approvals'=>['id','dossier_id','dossier_revision','actor_user_id','decision'],
            'office_project_start_events'=>['id','dossier_id','dossier_revision','actor_user_id','event_type','payload_json'],
            'office_project_start_questions'=>['id','dossier_id','created_revision','actor_user_id','question','answer','decision_text','owner_user_id','due_date','is_critical'],
        ] as $table=>$required) {
            if (array_diff($required, array_keys($columns[$table] ?? [])) !== []) {
                throw new RuntimeException('EXISTING_SCHEMA_MISMATCH_'.$table);
            }
        }
        echo "STARTDOSSIER_SCHEMA=ALREADY_PRESENT\n";
        exit(0);
    }
    echo "STARTDOSSIER_SCHEMA=".($mode === '--plan' ? 'PLAN_CREATE_FOUR_TABLES' : 'APPLY_CREATE_FOUR_TABLES')."\n";
    if ($mode === '--plan') exit(0);
    foreach ($statements as $i => $statement) {
        $db->exec($statement);
        echo 'CREATED '.$tables[$i]."\n";
    }
    foreach ($tables as $table) $db->query('SELECT 1 FROM `'.$table.'` LIMIT 0');
    echo "STARTDOSSIER_SCHEMA=INSTALLED\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'STARTDOSSIER_MIGRATION=BLOCKED '.$error->getMessage()."\n");
    exit(1);
}
