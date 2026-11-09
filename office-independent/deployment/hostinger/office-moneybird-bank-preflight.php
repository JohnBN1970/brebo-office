<?php
declare(strict_types=1);

// CLI-only diagnostic. Never activates flags, changes grants or imports invoices.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
$result = ['test_scope_verified'=>false, 'configuration_loaded'=>false,
  'finance_enabled'=>false, 'finance_schema_verified'=>false, 'office_sessions_enabled'=>false,
  'office_bank_flag_enabled'=>false, 'bank_endpoint_configured'=>false,
  'shared_secret_configured'=>false, 'curl_available'=>function_exists('curl_init'),
  'bank_authorized_operator_present'=>false, 'live_read_requested'=>getenv('BANK_PREFLIGHT_LIVE_READ') === '1',
  'unsigned_http_status'=>null, 'signed_bank_read_ok'=>false, 'bank_period_verified'=>false,
  'active_files_unchanged'=>false, 'bank_read_preflight_go'=>false];
$before = [];
$config = null;
try {
  $officeHome = rtrim((string)getenv('HOME'), '/');
  if ($officeHome === '') throw new RuntimeException('Missing home');
  $base = $officeHome . '/domains/brebobv.nl/office_calc_test';
  $configFile = $base . '/config/config.php';
  if (!is_file($configFile) || is_link($configFile)) throw new RuntimeException('Missing test config');
  $before[$configFile] = hash_file('sha256', $configFile);
  $level = ob_get_level();
  ob_start();
  try { $config = require $configFile; }
  finally { while (ob_get_level() > $level) ob_end_clean(); }
  if (!is_array($config)) throw new RuntimeException('Invalid test config');
  $result['configuration_loaded'] = true;
  $root = $config['OFFICE_PRIVATE_ROOT'] ?? null;
  if (!is_string($root) || !str_starts_with($root, $base . '/release-') || !str_ends_with($root, '/private')
      || is_link($root) || realpath($root) !== $root || ($config['OFFICE_BASE_PATH'] ?? null) !== '/office-test') {
    throw new RuntimeException('Invalid test scope');
  }
  $result['test_scope_verified'] = true;
  foreach (['public/office-finance.php','src/Finance/OfficeFinanceAccess.php','src/Finance/OfficeFinanceView.php',
    'src/Finance/OfficeMoneybirdBankApiClient.php','src/Finance/OfficeMoneybirdBankSnapshot.php','src/Finance/OfficeFinanceBankPanel.php'] as $relative) {
    $file = $root . '/' . $relative;
    $before[$file] = is_file($file) ? hash_file('sha256', $file) : null;
  }
  foreach (['finance_enabled'=>'OFFICE_FINANCE_ENABLED','finance_schema_verified'=>'OFFICE_FINANCE_SCHEMA_VERIFIED',
    'office_sessions_enabled'=>'OFFICE_CRM_SESSION_MODE','office_bank_flag_enabled'=>'OFFICE_MONEYBIRD_BANK_READ_ENABLED'] as $label=>$key) {
    $result[$label] = ($config[$key] ?? null) === 'enabled';
  }
  $endpoint = 'https://brebo-integration-api.john-boon.workers.dev/v1/accounting/bank-data';
  $result['bank_endpoint_configured'] = ($config['OFFICE_MONEYBIRD_BANK_API_URL'] ?? null) === $endpoint;
  $result['shared_secret_configured'] = is_string($config['OFFICE_INTEGRATION_SHARED_SECRET'] ?? null)
    && strlen($config['OFFICE_INTEGRATION_SHARED_SECRET']) >= 24;
  // Only SELECT is executed, inside an explicitly read-only transaction.
  try {
    $db = new PDO((string)($config['OFFICE_INTAKE_PDO_DSN'] ?? ''), (string)($config['OFFICE_INTAKE_DB_USER'] ?? ''),
      (string)($config['OFFICE_INTAKE_DB_PASSWORD'] ?? ''), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') throw new RuntimeException('Unexpected database driver');
    $db->exec('SET TRANSACTION READ ONLY');
    $db->beginTransaction();
    try {
      $count = $db->query("SELECT COUNT(DISTINCT a.id) FROM office_operator_accounts a
        INNER JOIN office_module_permissions r ON r.operator_id=a.id AND r.module='finance' AND r.permission='read'
        INNER JOIN office_module_permissions b ON b.operator_id=a.id AND b.module='finance' AND b.permission='bank_read'
        WHERE a.active=1")->fetchColumn();
      $result['bank_authorized_operator_present'] = (int)$count > 0;
    } finally { if ($db->inTransaction()) $db->rollBack(); }
  } catch (Throwable $ignored) { /* Report only status, never database details. */ }

  if ($result['live_read_requested'] && $result['curl_available']) {
    $curl = curl_init($endpoint);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>false,
      CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_TIMEOUT=>12, CURLOPT_HTTPHEADER=>['Accept: application/json'],
      CURLOPT_WRITEFUNCTION=>static fn($handle, string $chunk): int => strlen($chunk)]);
    curl_exec($curl);
    $result['unsigned_http_status'] = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($result['bank_endpoint_configured'] && $result['shared_secret_configured']) {
      foreach (['OFFICE_MONEYBIRD_BANK_API_URL','OFFICE_INTEGRATION_SHARED_SECRET'] as $key) putenv($key.'='.$config[$key]);
      // Use the staged client, never load or invoke the active Office controller.
      require_once dirname(__DIR__, 2) . '/src/Finance/OfficeMoneybirdBankSnapshot.php';
      require_once dirname(__DIR__, 2) . '/src/Finance/OfficeMoneybirdBankApiClient.php';
      try {
        $snapshot = \Brebo\Office\Finance\OfficeMoneybirdBankApiClient::fetch();
        $result['signed_bank_read_ok'] = true;
        $result['bank_period_verified'] = ($snapshot['period'] ?? null) === 'this_month';
        unset($snapshot);
      } catch (Throwable $ignored) { /* Never print provider responses or credentials. */ }
    }
  }
} catch (Throwable $ignored) { /* Diagnostics stay sanitized on every failure. */ }
if ($before !== []) {
  $result['active_files_unchanged'] = true;
  foreach ($before as $file=>$hash) {
    if ((is_file($file) ? hash_file('sha256', $file) : null) !== $hash) $result['active_files_unchanged'] = false;
  }
}
$required = ['test_scope_verified','configuration_loaded','finance_enabled','finance_schema_verified',
  'office_sessions_enabled','office_bank_flag_enabled','bank_endpoint_configured','shared_secret_configured',
  'curl_available','bank_authorized_operator_present','signed_bank_read_ok','bank_period_verified','active_files_unchanged'];
$result['bank_read_preflight_go'] = !in_array(false, array_map(static fn(string $key): bool => $result[$key] === true, $required), true)
  && $result['unsigned_http_status'] === 401;
echo json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), PHP_EOL;
echo 'BANK_READ_PREFLIGHT_GO=' . ($result['bank_read_preflight_go'] ? '1' : '0') . PHP_EOL;
// A passed CLI preflight still requires target-session/UI acceptance before activation.
