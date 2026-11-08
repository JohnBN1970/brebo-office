<?php
declare(strict_types=1);
/**
 * Opt-in isolated Office-test activation. Never runs on HTTP.
 * Only a uniquely determined active project manager with existing settings:manage
 * receives read/write for quality, risk and procurement. No approval or award grants.
 */
if (PHP_SAPI !== 'cli' || getenv('OFFICE_BASE_PATH') !== '/office-test'
    || getenv('OFFICE_GOVERNANCE_ACTIVATE') !== 'explicit-test-only') {
    fwrite(STDERR, "GOVERNANCE_ACTIVATION_DENIED\n"); exit(2);
}
$base = '/home/u213420663/domains/brebobv.nl/office_calc_test';
$configPath = $base . '/config/config.php';
if (!is_file($configPath) || is_link($configPath)) {
    fwrite(STDERR, "GOVERNANCE_ACTIVATION_CONFIG_MISSING\n"); exit(2);
}
$config = require $configPath;
$dsn = is_array($config) ? ($config['OFFICE_INTAKE_PDO_DSN'] ?? null) : null;
$user = is_array($config) ? ($config['OFFICE_INTAKE_DB_USER'] ?? null) : null;
$pass = is_array($config) ? ($config['OFFICE_INTAKE_DB_PASSWORD'] ?? null) : null;
if (!is_array($config) || ($config['OFFICE_BASE_PATH'] ?? null) !== '/office-test'
    || ($config['OFFICE_CRM_SESSION_MODE'] ?? null) !== 'enabled'
    || !is_string($dsn)
    || !preg_match('/^mysql:host=(?:localhost|127\.0\.0\.1);dbname=u213420663_office_test;charset=utf8mb4$/D', $dsn)
    || $user !== 'u213420663_office_test' || !is_string($pass) || $pass === '') {
    fwrite(STDERR, "GOVERNANCE_ACTIVATION_DB_OR_SESSION_SCOPE_FAILED\n"); exit(2);
}
$active = isset($config['OFFICE_PRIVATE_ROOT']) && is_string($config['OFFICE_PRIVATE_ROOT'])
    ? realpath($config['OFFICE_PRIVATE_ROOT']) : false;
$realBase = realpath($base);
if (!is_string($active) || !is_string($realBase)
    || !str_starts_with($active, $realBase . DIRECTORY_SEPARATOR)
    || preg_match('~/release-[^/]+/private$~D', $active) !== 1
    || !is_file($active . '/public/office-governance.php')
    || !is_file($active . '/src/Governance/Infrastructure/PdoOfficeGovernanceStore.php')) {
    fwrite(STDERR, "GOVERNANCE_ACTIVATION_RELEASE_FAILED\n"); exit(2);
}
$router = '/home/u213420663/domains/brebobv.nl/public_html/office-test/index.php';
$rewrite = '/home/u213420663/domains/brebobv.nl/public_html/office-test/.htaccess';
$routerText = is_file($router) ? file_get_contents($router) : false;
$rewriteText = is_file($rewrite) ? file_get_contents($rewrite) : false;
foreach (['kwaliteit','risicos','inkoop'] as $route) {
    if (!is_string($routerText)
        || !str_contains($routerText, "'/office-test/$route' => 'office-governance.php'")) {
        fwrite(STDERR, "GOVERNANCE_ACTIVATION_ROUTE_MISSING\n"); exit(2);
    }
}
if (!is_string($rewriteText)
    || !str_contains($rewriteText, 'RewriteRule ^(?:kwaliteit|risicos|inkoop)/?$ index.php [END,QSA]')) {
    fwrite(STDERR, "GOVERNANCE_ACTIVATION_REWRITE_MISSING\n"); exit(2);
}
if (($config['OFFICE_TEST_GOVERNANCE_ENABLED'] ?? '') === 'enabled'
    || ($config['OFFICE_TEST_GOVERNANCE_SCHEMA_VERIFIED'] ?? '') === 'enabled'
    || isset($config['OFFICE_TEST_GOVERNANCE_CSRF_SECRET'])) {
    fwrite(STDERR, "GOVERNANCE_ACTIVATION_ALREADY_OR_PARTIALLY_CONFIGURED\n"); exit(2);
}
$lock = fopen($base . '/.governance-activation.lock', 'c');
if (!is_resource($lock) || !flock($lock, LOCK_EX)) {
    fwrite(STDERR, "GOVERNANCE_ACTIVATION_LOCK_FAILED\n"); exit(2);
}
try {
    $db = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'u213420663_office_test') {
        throw new RuntimeException('database_scope');
    }
    foreach (['office_quality_checks','office_risks','office_procurement_orders',
        'office_governance_commands','office_governance_audit'] as $table) {
        $q = $db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
        $q->execute(['u213420663_office_test',$table]);
        if ($q->fetchColumn() !== 'InnoDB') throw new RuntimeException('schema_unverified');
    }
    $candidates = $db->query("SELECT DISTINCT a.id FROM office_operator_accounts a
        JOIN office_project_memberships pm ON pm.operator_id=a.id AND pm.permission='manage'
        JOIN office_module_permissions mp ON mp.operator_id=a.id
            AND mp.module='settings' AND mp.permission='manage'
        WHERE a.active=1")->fetchAll(PDO::FETCH_COLUMN);
    if (count($candidates) !== 1) throw new RuntimeException('eligible_operator_not_unique');
    $actor = (string)$candidates[0];
    if ($actor === '') throw new RuntimeException('eligible_operator_empty');
    $q = $db->prepare("SELECT COUNT(*) FROM office_module_permissions WHERE module IN ('quality','risk','procurement')");
    $q->execute();
    if ((int)$q->fetchColumn() !== 0) throw new RuntimeException('preexisting_grants_review_required');

    // Prepare a private, reversible configuration wrapper before granting rights.
    $configDir = dirname($configPath);
    $stamp = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(6));
    $previous = '.config-before-governance-' . $stamp . '.php';
    $secretName = '.governance-csrf-secret-' . $stamp;
    $old = file_get_contents($configPath);
    if (!is_string($old) || !str_starts_with(ltrim($old), '<?php')) {
        throw new RuntimeException('unexpected_original_config');
    }
    $oldPath = $configDir . '/' . $previous;
    $secretPath = $configDir . '/' . $secretName;
    $temp = tempnam($configDir, '.governance-new-config-');
    if (!is_string($temp)) throw new RuntimeException('temp_config_failed');
    if (file_put_contents($oldPath, $old, LOCK_EX) !== strlen($old)
        || file_put_contents($secretPath, bin2hex(random_bytes(32)) . "\n", LOCK_EX) !== 65) {
        throw new RuntimeException('private_backup_or_secret_failed');
    }
    chmod($oldPath, 0600);
    chmod($secretPath, 0600);
    $wrapper = "<?php\n"
        . "declare(strict_types=1);\n"
        . '$config = require __DIR__ . ' . var_export('/' . $previous, true) . ";\n"
        . "if (!is_array(\$config)) throw new RuntimeException('Invalid isolated Office config');\n"
        . "\$config['OFFICE_TEST_GOVERNANCE_ENABLED'] = 'enabled';\n"
        . "\$config['OFFICE_TEST_GOVERNANCE_SCHEMA_VERIFIED'] = 'enabled';\n"
        . '$config["OFFICE_TEST_GOVERNANCE_CSRF_SECRET"] = trim((string)file_get_contents(__DIR__ . '
        . var_export('/' . $secretName, true) . "));\n"
        . "return \$config;\n";
    if (file_put_contents($temp, $wrapper, LOCK_EX) !== strlen($wrapper)) {
        throw new RuntimeException('config_candidate_failed');
    }
    chmod($temp, fileperms($configPath) & 0777);
    $lintOutput = [];
    $lintStatus = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($temp) . ' 2>&1', $lintOutput, $lintStatus);
    if ($lintStatus !== 0) throw new RuntimeException('candidate_php_invalid');
    $candidate = require $temp;
    if (!is_array($candidate) || strlen((string)($candidate['OFFICE_TEST_GOVERNANCE_CSRF_SECRET'] ?? '')) < 32) {
        throw new RuntimeException('candidate_config_invalid');
    }
    foreach ($config as $key => $value) {
        if (!array_key_exists($key, $candidate) || $candidate[$key] !== $value) {
            throw new RuntimeException('candidate_config_changed_existing_values');
        }
    }
    $db->beginTransaction();
    $grant = $db->prepare('INSERT INTO office_module_permissions (operator_id,module,permission) VALUES (?,?,?)');
    foreach (['quality','risk','procurement'] as $module) {
        foreach (['read','write'] as $right) {
            $grant->execute([$actor,$module,$right]);
        }
    }
    $db->commit();
    if (!rename($temp, $configPath)) {
        // The rights are harmless without flags, but fail closed for operator review.
        throw new RuntimeException('config_swap_failed_grants_only');
    }
    $enabled = require $configPath;
    if (!is_array($enabled)
        || ($enabled['OFFICE_TEST_GOVERNANCE_ENABLED'] ?? null) !== 'enabled'
        || ($enabled['OFFICE_TEST_GOVERNANCE_SCHEMA_VERIFIED'] ?? null) !== 'enabled'
        || strlen((string)($enabled['OFFICE_TEST_GOVERNANCE_CSRF_SECRET'] ?? '')) < 32) {
        copy($oldPath, $configPath);
        throw new RuntimeException('post_activate_verification_failed_config_restored');
    }
    echo "GOVERNANCE_TEST_ACTIVATED\n";
    echo "GOVERNANCE_MODULES=quality,risk,procurement\n";
    echo "GOVERNANCE_INITIAL_RIGHTS=read,write\n";
    echo "GOVERNANCE_APPROVE_AWARD_RECEIVE=NOT_GRANTED\n";
    echo "GOVERNANCE_CONFIG_BACKUP_PRESENT\n";
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    $reason = $e instanceof RuntimeException ? $e->getMessage() : get_class($e);
    if (!preg_match('/^[a-zA-Z0-9_]+$/D', $reason)) $reason = get_class($e);
    fwrite(STDERR, "GOVERNANCE_ACTIVATION_ABORT=" . $reason . "\n");
    exit(1);
}
