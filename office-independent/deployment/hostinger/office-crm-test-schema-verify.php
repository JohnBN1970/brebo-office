<?php
declare(strict_types=1);

/**
 * Read-only Office-test CRM schema verification.
 * Never changes schema, records, config or write flags.
 */
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit(2);
}
$root = '/home/u213420663/domains/brebobv.nl/office_calc_test';
$config = require $root . '/config/config.php';
if (!is_array($config)
  || ($config['OFFICE_BASE_PATH'] ?? '') !== '/office-test'
  || ($config['OFFICE_INTAKE_DB_USER'] ?? '') !== 'u213420663_office_test'
  || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D', (string)($config['OFFICE_INTAKE_PDO_DSN'] ?? ''))) {
  fwrite(STDERR, "STOP: alleen Office-test toegestaan\n");
  exit(2);
}
$source = dirname(__DIR__, 2) . '/src/Crm';
require_once $source . '/OfficeCrmSchema.php';
require_once $source . '/OfficeCrmSchemaPreflight.php';
require_once $source . '/OfficeCrmSchemaVerifier.php';
try {
  $db = new PDO(
    $config['OFFICE_INTAKE_PDO_DSN'],
    $config['OFFICE_INTAKE_DB_USER'],
    $config['OFFICE_INTAKE_DB_PASSWORD'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
  );
  if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'u213420663_office_test') {
    throw new RuntimeException('Database mismatch');
  }
  $native = \Brebo\Office\Crm\OfficeCrmSchemaPreflight::inspect($db);
  $crm = \Brebo\Office\Crm\OfficeCrmSchemaVerifier::inspect($db);
  echo json_encode([
    'native_ready' => $native['ready'],
    'native_issues' => $native['issues'],
    'crm_schema_ready' => $crm['ready'],
    'crm_tables_checked' => $crm['checked'],
    'crm_issues' => $crm['issues'],
    'migration_executed' => false,
    'write_flags_changed' => false,
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
  exit($native['ready'] && $crm['ready'] ? 0 : 1);
} catch (Throwable $e) {
  fwrite(STDERR, "CRM verificatie niet beschikbaar; geen wijzigingen uitgevoerd\n");
  exit(2);
}
