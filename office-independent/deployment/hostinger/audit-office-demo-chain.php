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
        'crm' => ['office_intake_requests', 'office_crm_relations'],
        'opportunity' => ['office_crm_opportunities'],
        'inspectieopdracht' => ['office_inspections', 'office_inspection_findings'],
        'calculatie' => ['office_calc_calculations', 'office_project_calculation_scopes'],
        'offerte' => ['office_offers'],
        'contractacceptatie' => ['office_calc_crm_acceptance_transfers', 'office_project_contracts'],
        'projecten' => ['office_projects'],
        'gebouwen' => ['office_buildings', 'office_project_buildings'],
        'definitieve_inmeting' => ['office_measurements'],
        'contracten' => ['office_project_contracts', 'office_project_terms'],
        'inkoop' => ['office_procurement_orders'],
        'personeel' => ['office_inzet_people', 'office_inzet_personnel_details'],
        'inzet_onsite' => ['office_inzet_shifts', 'office_onsite_device_tokens'],
        'uitvoering' => ['office_project_control_milestones', 'office_project_plan_items'],
        'finance' => ['office_finance_invoices', 'office_finance_invoice_lines'],
        'mjop' => ['office_mjop_plans'],
        'rapportage' => ['office_inspection_report_configs', 'office_inspection_report_findings'],
    ];
    $evidence = [];
    foreach ($required as $stage => $names) {
        $missing = array_values(array_filter($names, static fn(string $name): bool => !isset($tables[$name])));
        // A missing candidate table name does not prove that a module lacks a
        // schema; an alternate canonical table or external repository may own it.
        $evidence[$stage] = [
            'schema' => $missing === [] ? true : null,
            'reason' => $missing
                ? 'Candidate tables not found (canonical schema unverified): ' . implode(', ', $missing)
                : 'Candidate tables present; records and workflow not verified',
        ];
    }
    $result = \Brebo\Office\Demo\OfficeDemoProcessChain::monitor($evidence);
    $result['audit'] = 'READ_ONLY_SCHEMA_CANDIDATE_PREFLIGHT';
    $result['schema_evidence_scope'] = 'Candidate table names only; absence may mean another canonical table name';
    // Identify actual candidate schema names for the unresolved business modules.
    // Metadata only: no rows, credentials, or financial figures are exposed.
    $patterns = [
        'crm' => ['crm', 'intake', 'lead'],
        'opportunity' => ['opportunit', 'lead'],
        'inspectieopdracht' => ['inspect', 'finding'],
        'calculatie' => ['calc', 'calculat', 'scope', 'quantity'],
        'offerte' => ['offer', 'offert', 'quotation'],
        'contractacceptatie' => ['contract', 'accept'],
        'projecten' => ['project'],
        'gebouwen' => ['building', 'housing'],
        'definitieve_inmeting' => ['measure', 'inmet'],
        'contracten' => ['contract', 'term'],
        'inkoop' => ['purchase', 'procure', 'supplier'],
        'personeel' => ['personnel', 'inzet_people', 'employee'],
        'inzet_onsite' => ['onsite', 'inzet', 'presence'],
        'uitvoering' => ['milestone', 'plan_item', 'execution'],
        'finance' => ['finance', 'invoice', 'payment', 'bank'],
        'mjop' => ['mjop', 'maintenance'],
        'rapportage' => ['report', 'rapport', 'kpi'],
    ];
    $result['schema_candidates'] = [];
    foreach ($patterns as $stage => $terms) {
        $candidates = [];
        foreach (array_keys($tables) as $tableName) {
            foreach ($terms as $term) {
                if (stripos($tableName, $term) !== false) {
                    $candidates[] = $tableName;
                    break;
                }
            }
        }
        sort($candidates);
        $result['schema_candidates'][$stage] = array_slice($candidates, 0, 30);
        $result['schema_candidates_truncated'][$stage] = count($candidates) > 30;
    }
    $result['project_id'] = null;
    if (isset($tables['office_projects'])) {
        $stmt = $db->prepare('SELECT id FROM office_projects WHERE code = ?');
        $stmt->execute([\Brebo\Office\Demo\OfficeDemoProcessChain::PROJECT_CODE]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($ids) === 1) $result['project_id'] = (int)$ids[0];
        else $result['project_identity_warning'] = 'Expected exactly one test project';
    }
    // Verify concrete project-linked records without exposing business data.
    // The relationships below are accepted only when both project_id and id
    // exist in the actual table. No guessed cross-module join is treated as proof.
    $projectEvidenceTables = [
        'projecten' => ['office_projects'],
        'gebouwen' => ['office_project_buildings'],
        'inspectieopdracht' => ['office_inspections'],
        'calculatie' => ['office_project_calculation_scopes'],
        'contracten' => ['office_project_contracts'],
        'inkoop' => ['office_procurement_orders'],
        'personeel' => ['office_inzet_project_team'],
        'inzet_onsite' => ['office_inzet_shifts'],
        'uitvoering' => ['office_project_plan_items', 'office_project_control_milestones'],
        'finance' => ['office_finance_invoices'],
    ];
    $columnsStmt = $db->query('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()');
    $columns = [];
    foreach ($columnsStmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[$column['TABLE_NAME']][$column['COLUMN_NAME']] = true;
    }
    $result['project_record_evidence'] = [];
    foreach ($projectEvidenceTables as $stage => $names) {
        $checks = [];
        foreach ($names as $name) {
            if (!isset($tables[$name])) {
                $checks[$name] = ['state' => 'TABLE_NOT_FOUND'];
                continue;
            }
            if (!isset($columns[$name]['project_id'])) {
                $checks[$name] = ['state' => 'NO_DIRECT_PROJECT_ID'];
                continue;
            }
            if ($result['project_id'] === null) {
                $checks[$name] = ['state' => 'PROJECT_ID_UNVERIFIED'];
                continue;
            }
            // Table identifiers come from the hardcoded allowlist above.
            $stmt = $db->prepare('SELECT COUNT(*) FROM `' . $name . '` WHERE project_id = ?');
            $stmt->execute([$result['project_id']]);
            $count = (int)$stmt->fetchColumn();
            $checks[$name] = ['state' => $count > 0 ? 'LINKED_RECORDS_PRESENT' : 'NO_LINKED_RECORDS', 'count' => $count];
        }
        $result['project_record_evidence'][$stage] = $checks;
    }
    $result['record_evidence_scope'] = 'Direct project_id counts only; no workflow or financial correctness inferred';
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'DEMO_CHAIN_AUDIT_FAILED: ' . $e->getMessage() . "\n");
    exit(4);
}
