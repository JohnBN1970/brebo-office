<?php
declare(strict_types=1);

/**
 * Controlled, isolated quotation draft flags activation for Office-test only.
 * No production/Drupal access. Run with --audit or --activate after schema install.
 */
$mode = $argv[1] ?? '--audit';
if (!in_array($mode, ['--audit', '--activate'], true)) { fwrite(STDERR, "INVALID_MODE\n"); exit(2); }
$home = getenv('HOME');
if (!is_string($home) || $home === '') exit(3);
$base = $home . '/domains/brebobv.nl/office_calc_test';
$configPath = $base . '/config/config.php';
$router = $home . '/domains/brebobv.nl/public_html/office-test/index.php';
if (!is_file($configPath) || is_link($configPath) || !is_file($router)) exit(4);
if (strpos((string) file_get_contents($router), "'/office-test/crm/offertes'") === false) exit(5);
$c = require $configPath;
if (!is_array($c) || ($c['OFFICE_BASE_PATH'] ?? '') !== '/office-test'
 || ($c['OFFICE_TEST_CRM_ENABLED'] ?? '') !== 'enabled'
 || ($c['OFFICE_CRM_AUTH_MODE'] ?? '') !== 'session_only'
 || !isset($c['OFFICE_PRIVATE_ROOT'])
 || !str_starts_with((string) realpath((string)$c['OFFICE_PRIVATE_ROOT']), $base . '/release-')
 || ($c['OFFICE_PUBLIC_HOST'] ?? '') !== 'brebobv.nl'
 || ($c['OFFICE_INTAKE_DB_USER'] ?? '') !== 'u213420663_office_test') {
    fwrite(STDERR, "OFFICE_TEST_ENVIRONMENT_GUARD_FAILED\n"); exit(6);
}
$dsn = $c['OFFICE_INTAKE_PDO_DSN'] ?? '';
if (!is_string($dsn) || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D', $dsn)) exit(7);
$db = new PDO($dsn, $c['OFFICE_INTAKE_DB_USER'] ?? '', $c['OFFICE_INTAKE_DB_PASSWORD'] ?? '',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
$names = ['office_quotations','office_quotation_sections','office_quotation_instalments','office_quotation_assets','office_quotation_versions'];
foreach ($names as $name) {
    $q = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $q->execute([$name]);
    if ((int)$q->fetchColumn() !== 1) { fwrite(STDERR, "TABLE_MISSING $name\n"); exit(8); }
    echo "TABLE_OK $name\n";
}
$requiredColumns = [
    'office_crm_relations' => ['id','display_name','status'],
    'office_operator_accounts' => ['id','active'],
];
foreach ($requiredColumns as $table => $columns) {
    foreach ($columns as $column) {
        $q = $db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
        $q->execute([$table,$column]);
        if ((int)$q->fetchColumn() !== 1) { fwrite(STDERR, "DEPENDENCY_COLUMN_MISSING $table $column\n"); exit(9); }
    }
    echo "DEPENDENCY_OK $table\n";
}
$flags=['OFFICE_OFFERTSTUDIO_PREVIEW_ENABLED','OFFICE_QUOTATIONS_SCHEMA_VERIFIED','OFFICE_QUOTATIONS_WRITE_ENABLED'];
$missing=[];
foreach ($flags as $flag) {
    $value = $c[$flag] ?? '(unset)';
    echo "FLAG_BEFORE $flag " . ($value === 'enabled' ? 'enabled' : 'not-enabled') . "\n";
    if ($value === 'enabled') continue;
    if (array_key_exists($flag,$c)) {
        fwrite(STDERR,"EXISTING_FLAG_NOT_ENABLED_REQUIRES_MANUAL_REVIEW $flag\n"); exit(10);
    }
    $missing[] = $flag;
}
if ($mode === '--audit') { echo "OFFERTSTUDIO_AUDIT_COMPLETE\n"; exit(0); }
if (!$missing) { echo "OFFERTSTUDIO_FLAGS_ALREADY_ENABLED\n"; exit(0); }
$src = file_get_contents($configPath);
if (!is_string($src)) exit(11);
// The tested Office-test config returns a variable. Insert the three flags
// immediately before that return instead of editing its array expression.
// The returned array is still verified after writing, with rollback on failure.
$tokens=token_get_all($src);
$returns=[];
$offset=0;
foreach ($tokens as $token) {
    $value=is_array($token)?$token[1]:$token;
    if (is_array($token) && $token[0]===T_RETURN) $returns[]=$offset;
    $offset+=strlen($value);
}
if (count($returns)!==1) {
    fwrite(STDERR,"CONFIG_RETURN_LAYOUT_NOT_SUPPORTED\n");exit(11);
}
$returnOffset=$returns[0];
$tail=substr($src,$returnOffset);
if (!preg_match('/^return\s+(\$[a-zA-Z_][a-zA-Z0-9_]*)\s*;\s*(?:\?>)?\s*$/D',$tail,$match)) {
    fwrite(STDERR,"CONFIG_RETURN_VARIABLE_LAYOUT_REQUIRES_REVIEW\n");exit(11);
}
$variable=$match[1];
foreach ($flags as $flag) {
    if (str_contains($src,$flag)) {
        fwrite(STDERR,"CONFIG_FLAG_TEXT_PRESENT_REQUIRES_REVIEW $flag\n");exit(12);
    }
}
$insertion='';
foreach ($missing as $flag) {
    $insertion.=$variable."['".$flag."'] = 'enabled';\n";
}
$next=substr($src,0,$returnOffset).$insertion.substr($src,$returnOffset);
if ($next===$src) exit(13);
$runId = getenv('GITHUB_RUN_ID');
if (!is_string($runId) || !preg_match('/^[0-9]{6,20}$/D',$runId)) exit(14);
$backup=$configPath . '.backup-offertestudio-' . $runId;
if (file_exists($backup) || !copy($configPath,$backup)) exit(15);
chmod($backup,0600);
$tmp=$configPath . '.offertestudio-candidate-' . $runId;
if (file_put_contents($tmp,$next,LOCK_EX) !== strlen($next)) exit(16);
chmod($tmp, fileperms($configPath) & 0777);
$lint=shell_exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($tmp).' 2>&1');
if (!is_string($lint) || !str_contains($lint,'No syntax errors detected')) {
    unlink($tmp); fwrite(STDERR,"CONFIG_CANDIDATE_LINT_FAILED\n");exit(17);
}
if (!rename($tmp,$configPath)) exit(18);
$after=require $configPath;
foreach ($flags as $flag) if (($after[$flag] ?? '') !== 'enabled') {
    copy($backup,$configPath); fwrite(STDERR,"CONFIG_ROLLBACK_VERIFY_FAILED\n"); exit(19);
}
echo "OFFERTSTUDIO_FLAGS_ACTIVATED\n";
