<?php
declare(strict_types=1);

/** Office-test only: reviewed additive handover draft schema. */
$mode = $argv[1] ?? '';
if (PHP_SAPI !== 'cli' || !in_array($mode, ['--plan', '--apply'], true)
    || ($mode === '--apply' && ($argv[2] ?? '') !== 'CONFIRM-OFFICE-TEST-HANDOVER')) {
    fwrite(STDERR, "USAGE: sudo php handover-report-migrate.php --plan | --apply CONFIRM-OFFICE-TEST-HANDOVER\n");
    exit(2);
}
if (!function_exists('posix_geteuid') || posix_geteuid() !== 0) {
    fwrite(STDERR, "BLOCKED ROOT_REQUIRED\n"); exit(2);
}
try {
    $root = '/srv/brebo/office-test';
    $release = realpath($root.'/current');
    if (!is_string($release)
        || preg_match('~^/srv/brebo/office-test/releases/[a-f0-9]{40}$~D', $release) !== 1
        || trim((string)@file_get_contents($release.'/.office-source-sha')) !== basename($release)) {
        throw new RuntimeException('RELEASE_IDENTITY');
    }
    $config = '/etc/brebo/office-test-db.env';
    if (!is_file($config) || is_link($config) || fileowner($config) !== 0
        || (fileperms($config) & 0077) !== 0) throw new RuntimeException('PRIVATE_CONFIG_INVALID');
    $env = [];
    foreach (explode("\n", (string)file_get_contents($config)) as $line) {
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
    $db = new PDO('mysql:host='.$env['OFFICE_TEST_DB_HOST'].';dbname=brebo_office_test;charset=utf8mb4',
        $env['OFFICE_TEST_DB_USER'], $env['OFFICE_TEST_DB_PASSWORD'] ?? '',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'brebo_office_test') throw new RuntimeException('DATABASE_MISMATCH');
    $schema = $release.'/sql/20261127_project_handover_reports.sql';
    if (!is_file($schema) || is_link($schema)) throw new RuntimeException('SCHEMA_FILE_MISSING');
    $sql = preg_replace('/--[^\\n]*/', '', (string)file_get_contents($schema));
    $statements = array_values(array_filter(array_map('trim', explode(';', $sql))));
    if (count($statements) !== 1
        || !preg_match('/^CREATE TABLE IF NOT EXISTS office_project_handover_reports\\s*\\(/D', $statements[0])
        || !preg_match('/ENGINE=InnoDB DEFAULT CHARSET=utf8mb4$/D', $statements[0])) {
        throw new RuntimeException('UNREVIEWED_SCHEMA');
    }
    $table = 'office_project_handover_reports';
    $q = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $q->execute([$table]);
    $columns = $q->fetchAll(PDO::FETCH_COLUMN);
    if ($columns !== []) {
        $required = ['id','project_id','title','notes','status','created_by_operator_id','created_at'];
        if (array_diff($required, $columns) !== []) throw new RuntimeException('EXISTING_SCHEMA_MISMATCH');
        echo "HANDOVER_SCHEMA=ALREADY_PRESENT\n"; exit(0);
    }
    echo "HANDOVER_SCHEMA=".($mode === '--plan' ? 'PLAN_CREATE' : 'APPLY_CREATE')."\n";
    if ($mode === '--plan') exit(0);
    $db->exec($statements[0]);
    $db->query('SELECT 1 FROM office_project_handover_reports LIMIT 0');
    echo "HANDOVER_SCHEMA=INSTALLED\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'HANDOVER_MIGRATION=BLOCKED '.$error->getMessage()."\n");
    exit(1);
}
