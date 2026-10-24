<?php
declare(strict_types=1);

/**
 * Read-only Hostinger CLI preflight. Run manually against office-test config.
 * This script cannot create tables, change flags, or print credentials.
 */
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}
if (getenv('OFFICE_BASE_PATH') !== '/office-test') {
  fwrite(STDERR, "CRM preflight uitsluitend voor office-test\n");
  exit(2);
}
foreach (['OFFICE_INTAKE_PDO_DSN', 'OFFICE_INTAKE_DB_USER'] as $name) {
  if (!is_string(getenv($name)) || getenv($name) === '') {
    fwrite(STDERR, "Ontbrekende databaseconfiguratie: " . $name . "\n");
    exit(2);
  }
}
require_once dirname(__DIR__, 2) . '/src/Crm/OfficeCrmSchemaPreflight.php';
require_once dirname(__DIR__, 2) . '/src/Crm/OfficeCrmSchemaInventory.php';

try {
  $db = new PDO(
    getenv('OFFICE_INTAKE_PDO_DSN'),
    getenv('OFFICE_INTAKE_DB_USER'),
    getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
  );
  $native = \Brebo\Office\Crm\OfficeCrmSchemaPreflight::inspect($db);
  $crm = \Brebo\Office\Crm\OfficeCrmSchemaInventory::inspect($db);
  echo json_encode([
    'office_native_references_ready' => $native['ready'],
    'office_native_reference_issues' => $native['issues'],
    'crm_inventory_only' => true,
    'crm_tables_existing' => $crm['existing'],
    'crm_tables_missing' => $crm['missing'],
    'crm_issues' => $crm['issues'],
    'migration_executed' => false,
    'write_flags_changed' => false,
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
  exit(($native['ready'] && $crm['missing'] === [] && $crm['issues'] === []) ? 0 : 1);
} catch (Throwable $error) {
  // Never print DSN, SQL exception or passwords in server logs/output.
  fwrite(STDERR, "CRM preflight niet beschikbaar; controleer configuratie en databaseverbinding\n");
  exit(2);
}
