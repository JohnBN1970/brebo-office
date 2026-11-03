<?php

declare(strict_types=1);

/**
 * Read-only Office-test schema inventory. Outputs table and column presence only.
 * Never prints credentials, database rows, or customer data.
 */
$configPath = getenv('OFFICE_TEST_CONFIG_PATH');
if (!is_string($configPath) || !is_file($configPath)) {
    fwrite(STDERR, "CONFIG_MISSING\n");
    exit(2);
}
$config = require $configPath;
$basePath = $config['OFFICE_BASE_PATH'] ?? '';
$dsn = $config['OFFICE_INTAKE_PDO_DSN'] ?? '';
if ($basePath !== '/office-test' || !is_string($dsn) ||
    !preg_match('/(?:^|;)dbname=([a-zA-Z0-9_]+)/', $dsn, $matches) ||
    !str_ends_with($matches[1], '_office_test')) {
    fwrite(STDERR, "TEST_SCOPE_REJECTED\n");
    exit(3);
}
$groups = [
    'crm' => ['office_intakes', 'office_crm_leads'],
    'projecten' => ['office_projects'],
    'gebouwen' => ['office_buildings', 'office_project_buildings'],
    'inspectie' => ['office_inspections', 'office_inspection_findings'],
    'calculatie' => ['office_calculations'],
    'offerte' => ['office_offers'],
    'contracten' => ['office_project_contracts', 'office_project_terms'],
    'inkoop' => ['office_purchase_orders'],
    'personeel' => ['office_inzet_people', 'office_inzet_personnel_details'],
    'inzet_onsite' => ['office_inzet_shifts', 'office_onsite_device_tokens'],
    'uitvoering' => ['office_project_milestones'],
    'finance' => ['office_finance_sales_drafts', 'office_finance_payments'],
    'mjop' => ['office_mjop_plans'],
    'rapportage' => ['office_project_reports'],
];
$db = new PDO($dsn, (string) ($config['OFFICE_INTAKE_DB_USER'] ?? ''),
    (string) ($config['OFFICE_INTAKE_DB_PASSWORD'] ?? ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$statement = $db->prepare('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :schema');
$statement->execute([':schema' => $matches[1]]);
$found = [];
foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $found[$row['TABLE_NAME']][] = $row['COLUMN_NAME'];
}
echo "OFFICE_TEST_SCHEMA_AUDIT\n";
foreach ($groups as $group => $tables) {
    echo "=== $group ===\n";
    foreach ($tables as $table) {
        if (!isset($found[$table])) {
            echo "$table: NOT_FOUND_OR_DIFFERENT_NAME\n";
            continue;
        }
        echo "$table: PRESENT (" . count($found[$table]) . " columns)\n";
        echo "  columns: " . implode(', ', $found[$table]) . "\n";
    }
}
echo "READ_ONLY_AUDIT_COMPLETE\n";
