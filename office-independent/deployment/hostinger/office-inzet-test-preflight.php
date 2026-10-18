<?php
declare(strict_types=1);

/**
 * CLI-only, read-only preflight for the isolated Office-test Inzet module.
 * Run on Hostinger from the test release. Never prints secrets or personal data.
 * Exit 0: ready; exit 1: incomplete. No migrations, seeding or writes.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
$configFile = '/home/u213420663/domains/brebobv.nl/office_calc_test/config/config.php';
$ok = true;
$check = static function (string $label, bool $result) use (&$ok): void {
    echo ($result ? 'PASS ' : 'BLOCK ') . $label . PHP_EOL;
    $ok = $ok && $result;
};
$check('test configuration exists', is_file($configFile));
if (!is_file($configFile)) exit(1);
$config = require $configFile;
$check('test configuration is an array', is_array($config));
if (!is_array($config)) exit(1);
foreach ($config as $key => $value) {
    if (!is_string($key) || !is_string($value)) { $check('configuration string values', false); exit(1); }
    putenv($key . '=' . $value);
}
$check('isolated Office mount', getenv('OFFICE_BASE_PATH') === '/office-test');
$check('isolated Office private root', getenv('OFFICE_PRIVATE_ROOT') === '/home/u213420663/domains/brebobv.nl/office_calc_test/release-next/private');
$check('isolated test database', preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D', getenv('OFFICE_INTAKE_PDO_DSN') ?: '') === 1);
$check('test database user', getenv('OFFICE_INTAKE_DB_USER') === 'u213420663_office_test');
$check('project module enabled and verified', getenv('OFFICE_NATIVE_PROJECTS_ENABLED') === 'enabled' && getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED') === 'enabled');
$check('shift read enabled and verified', getenv('OFFICE_TEST_INZET_SHIFTS_ENABLED') === 'enabled' && getenv('OFFICE_TEST_INZET_SHIFTS_SCHEMA_VERIFIED') === 'enabled');
$check('shift write enabled', getenv('OFFICE_TEST_INZET_SHIFT_WRITE_ENABLED') === 'enabled');
$check('session CSRF secret configured', is_string(getenv('OFFICE_TEST_INZET_CSRF_SECRET')) && strlen(getenv('OFFICE_TEST_INZET_CSRF_SECRET')) >= 32);
$check('Calc launch remains disabled', getenv('OFFICE_CALC_LAUNCH_ENABLED') !== 'enabled');
$required = [
    'public/office-projects.php', 'public/office-inzet-shift-create.php',
    'src/Inzet/OfficeInzetSchema.php', 'src/Inzet/OfficeInzetShiftWriter.php',
    'src/Inzet/OfficeInzetWeekShiftReadRepository.php', 'src/Inzet/OfficeInzetShiftForm.php',
];
foreach ($required as $file) $check('release file '.$file, is_file($root.'/'.$file));
if (!$ok) exit(1);
try {
    $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN'), getenv('OFFICE_INTAKE_DB_USER'), getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    $tables = ['office_projects','office_project_memberships','office_operator_accounts','office_inzet_people','office_inzet_project_team','office_inzet_shifts'];
    foreach ($tables as $table) {
        $statement = $db->query('SELECT 1 FROM `'.$table.'` LIMIT 1');
        $check('schema '.$table, $statement !== false);
    }
    $active = (int)$db->query("SELECT COUNT(*) FROM office_inzet_people WHERE active = 1")->fetchColumn();
    $team = (int)$db->query("SELECT COUNT(*) FROM office_inzet_project_team t INNER JOIN office_inzet_people p ON p.id=t.person_id AND p.active=1")->fetchColumn();
    $manageable = (int)$db->query("SELECT COUNT(*) FROM office_project_memberships m INNER JOIN office_operator_accounts a ON a.id=m.operator_id AND a.active=1 WHERE m.permission='manage'")->fetchColumn();
    $ready = (int)$db->query("SELECT COUNT(*) FROM office_project_memberships m INNER JOIN office_operator_accounts a ON a.id=m.operator_id AND a.active=1 INNER JOIN office_inzet_project_team t ON t.project_id=m.project_id INNER JOIN office_inzet_people p ON p.id=t.person_id AND p.active=1 WHERE m.permission='manage'")->fetchColumn();
    $check('at least one active employee', $active > 0);
    $check('at least one active project team assignment', $team > 0);
    $check('at least one active project manager membership', $manageable > 0);
    $check('manager, project and employee connected', $ready > 0);
} catch (Throwable $e) {
    $check('read-only database preflight', false);
}
exit($ok ? 0 : 1);
