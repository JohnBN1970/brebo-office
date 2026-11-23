<?php
declare(strict_types=1);

// Structural regression only. This test does not connect to or migrate MySQL.
$root = dirname(__DIR__);
$files = [
    'fresh' => $root.'/src/Crm/OfficeCrmOpportunitySchema.php',
    'legacy' => $root.'/deployment/hostinger/migrate-office-crm-legacy-opportunities-test.php',
    'start' => $root.'/public/office-calc-crm-start.php',
    'preflight' => $root.'/deployment/vps/crm-calc-schema-preflight.php',
];
$sources = [];
foreach ($files as $name => $path) {
    $source = @file_get_contents($path);
    if (!is_string($source)) throw new RuntimeException('Missing source: '.$name);
    $sources[$name] = $source;
}
$requirements = [
    'fresh' => ['revision BIGINT UNSIGNED NOT NULL DEFAULT 1,'],
    'legacy' => [
        "'revision'=>'BIGINT UNSIGNED NOT NULL DEFAULT 1'",
        'OFFICE_CRM_LEGACY_MIGRATION',
        'legacy_rows_present_manual_mapping_required',
        'partial_migration_inspect_first',
        'Office-test migration approval',
    ],
    'start' => ['revision,stage,status FROM office_crm_opportunities', 'FOR UPDATE', 'crm_revision'],
    'preflight' => [
        'information_schema.COLUMNS',
        "'revision', 'stage', 'status'",
        'information_schema.STATISTICS',
        'CRM_DRAFT_UNIQUE_OPPORTUNITY',
        'CRM_DRAFT_OPPORTUNITY_REQUIRED',
        'CRM_DRAFT_PROJECT_OPTIONAL',
        'CALC_PROJECT_OPTIONAL',
        'SCHEMA_ONLY_PASS',
    ],
];
foreach ($requirements as $file => $tokens) {
    foreach ($tokens as $token) {
        if (!str_contains($sources[$file], $token)) {
            throw new RuntimeException('Deal Calc contract missing: '.$file.' / '.$token);
        }
    }
}
echo "CRM_DEAL_REVISION_MIGRATION_CONTRACT=PASS\n";
