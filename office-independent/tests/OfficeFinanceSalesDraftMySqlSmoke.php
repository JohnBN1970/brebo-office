<?php
declare(strict_types=1);

/**
 * Read-only Office-test schema smoke test.
 * Requires OFFICE_FINANCE_INTEGRATION_DSN and database credentials.
 * Never changes schema, records, feature flags, or production data.
 */
if (getenv('OFFICE_BASE_PATH') !== '/office-test') {
  fwrite(STDERR, "Office-test required\n");
  exit(1);
}
$dsn = getenv('OFFICE_FINANCE_INTEGRATION_DSN');
if (!is_string($dsn) || $dsn === '' || !str_starts_with($dsn, 'mysql:')) {
  fwrite(STDERR, "MySQL integration DSN required\n");
  exit(1);
}
$db = new PDO($dsn, getenv('OFFICE_FINANCE_INTEGRATION_USER') ?: '', getenv('OFFICE_FINANCE_INTEGRATION_PASSWORD') ?: '', [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_EMULATE_PREPARES => false,
]);
$required = [
  'office_finance_sales_drafts' => ['id','operator_id','office_project_id','customer_name','status','lines_json','created_at'],
  'office_vat_regimes' => ['code','label','rate','treatment','active','sort_order'],
  'office_module_permissions' => ['operator_id','module','permission'],
  'office_project_memberships' => ['operator_id','project_id','permission'],
  'office_operator_accounts' => ['id','active'],
  'office_projects' => ['id','title'],
];
foreach ($required as $table => $columns) {
  $query = $db->query('SHOW COLUMNS FROM `' . $table . '`');
  $definitions = $query->fetchAll(PDO::FETCH_ASSOC);
  $actual = array_column($definitions, 'Field');
  $missing = array_diff($columns, $actual);
  if ($missing !== []) throw new RuntimeException($table . ' missing: ' . implode(',', $missing));
  if ($table === 'office_finance_sales_drafts') {
    $types = array_column($definitions, 'Type', 'Field');
    if (strtolower((string)$types['lines_json']) !== 'json'
      || !str_starts_with(strtolower((string)$types['id']), 'bigint')
      || !str_starts_with(strtolower((string)$types['office_project_id']), 'bigint')) {
      throw new RuntimeException('Sales draft schema column types mismatch');
    }
    $indexes = $db->query('SHOW INDEX FROM `office_finance_sales_drafts`')->fetchAll(PDO::FETCH_ASSOC);
    $indexNames = array_column($indexes, 'Key_name');
    foreach (['PRIMARY', 'idx_sales_drafts_project', 'idx_sales_drafts_operator'] as $name) {
      if (!in_array($name, $indexNames, true)) throw new RuntimeException('Sales draft index missing: ' . $name);
    }
  }
}
require_once dirname(__DIR__) . '/src/Finance/OfficeVatRegimeCatalog.php';
if (getenv('OFFICE_VAT_REGIMES_SCHEMA_VERIFIED') === 'enabled') {
  $options = (new \Brebo\Office\Finance\OfficeVatRegimeCatalog($db))->activeOptions();
  if ($options === []) throw new RuntimeException('No approved active VAT regimes');
}
echo "Office-test Finance MySQL schema smoke OK (read-only)\n";
