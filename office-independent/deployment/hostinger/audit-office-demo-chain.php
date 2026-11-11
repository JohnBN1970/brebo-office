<?php
declare(strict_types=1);
/** Read-only schema preflight for the canonical 232-home demo. */
if (PHP_SAPI !== 'cli') exit(3);
require_once __DIR__ . '/../../src/Demo/OfficeDemoProcessChain.php';
$configPath = getenv('OFFICE_TEST_CONFIG_PATH');
if (!is_string($configPath) || !is_file($configPath)) { fwrite(STDERR, "CONFIG_MISSING\n"); exit(2); }
$config = require $configPath;
$dsn = $config['OFFICE_INTAKE_PDO_DSN'] ?? '';
if (!is_string($dsn) || !preg_match('/(?:^|;)dbname=([a-zA-Z0-9_]+)/', $dsn, $match)) { fwrite(STDERR, "DB_NAME_MISSING\n"); exit(3); }
try {
    \Brebo\Office\Demo\OfficeDemoProcessChain::assertTestEnvironment((string)($config['OFFICE_BASE_PATH'] ?? ''), $match[1]);
    $db = new PDO($dsn, (string)($config['OFFICE_INTAKE_DB_USER'] ?? ''), (string)($config['OFFICE_INTAKE_DB_PASSWORD'] ?? ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $tables = array_fill_keys($db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchAll(PDO::FETCH_COLUMN), true);
    // Inventory candidate tables for every stage. Schema presence is only
    // structural evidence: it must never imply working business workflows.
    // Names follow the existing Office-test schema inventory and remain
    // candidates until each module's repository contract is validated.
    $required = [
        'crm' => ['office_intakes', 'office_crm_leads'],
        'opportunity' => ['office_crm_opportunities'],
        'inspectieopdracht' => ['office_inspections', 'office_inspection_findings'],
        'calculatie' => ['office_calculations'],
        'offerte' => ['office_offers'],
        'contractacceptatie' => ['office_project_contracts'],
        'projecten' => ['office_projects'],
        'gebouwen' => ['office_buildings', 'office_project_buildings'],
        'definitieve_inmeting' => ['office_measurements'],
        'contracten' => ['office_project_contracts', 'office_project_terms'],
        'inkoop' => ['office_purchase_orders'],
        'personeel' => ['office_inzet_people', 'office_inzet_personnel_details'],
        'inzet_onsite' => ['office_inzet_shifts', 'office_onsite_device_tokens'],
        'uitvoering' => ['office_project_milestones', 'office_project_plan_items'],
        'finance' => ['office_finance_sales_drafts', 'office_finance_payments'],
        'mjop' => ['office_mjop_plans'],
        'rapportage' => ['office_project_reports'],
    ];
    $evidence = [];
    foreach ($required as $stage => $names) {
        $missing = array_values(array_filter($names, static fn(string $name): bool => !isset($tables[$name])));
        $evidence[$stage] = ['schema' => $missing === [], 'reason' => $missing ? 'Missing: ' . implode(', ', $missing) : 'Schema present; workflow and records not verified'];
    }
    $result = \Brebo\Office\Demo\OfficeDemoProcessChain::monitor($evidence);
    $result['audit'] = 'READ_ONLY_SCHEMA_CANDIDATE_PREFLIGHT';
    $result['schema_evidence_scope'] = 'Candidate table names only; absence may mean another canonical table name';
    $result['project_id'] = null;
    if (isset($tables['office_projects'])) {
        $stmt = $db->prepare('SELECT id FROM office_projects WHERE code = ?');
        $stmt->execute([\Brebo\Office\Demo\OfficeDemoProcessChain::PROJECT_CODE]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($ids) === 1) $result['project_id'] = (int)$ids[0];
        else $result['project_identity_warning'] = 'Expected exactly one test project';
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'DEMO_CHAIN_AUDIT_FAILED: ' . $e->getMessage() . "\n");
    exit(4);
}
