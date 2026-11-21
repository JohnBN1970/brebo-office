<?php
declare(strict_types=1);

// Structural regression: every explicitly guarded empty CRM installation
// must carry the Deal revision used by the projectless Calc launch.
$root=dirname(__DIR__);
$fresh=file_get_contents($root.'/src/Crm/OfficeCrmOpportunitySchema.php');
$legacy=file_get_contents($root.'/deployment/hostinger/migrate-office-crm-legacy-opportunities-test.php');
$start=file_get_contents($root.'/public/office-calc-crm-start.php');
$preflight=file_get_contents(dirname($root).'/scripts/deployment/office_test_crm_calc_readiness.php');
foreach ([$fresh,$legacy,$start,$preflight] as $source) {
    if (!is_string($source)) throw new RuntimeException('Missing CRM Calc revision source');
}
if (!str_contains($fresh, 'revision BIGINT UNSIGNED NOT NULL DEFAULT 1,')
    || !str_contains($legacy, "'revision'=>'BIGINT UNSIGNED NOT NULL DEFAULT 1'")
    || !str_contains($start, 'revision,stage,status FROM office_crm_opportunities')
    || !str_contains($preflight, "'revision','stage','status'")) {
    throw new RuntimeException('Deal revision contract incomplete across installation paths');
}
foreach (["OFFICE_CRM_LEGACY_MIGRATION", "legacy_rows_present_manual_mapping_required",
          "partial_migration_inspect_first", "Office-test migration approval"] as $gate) {
    if (!str_contains($legacy,$gate)) throw new RuntimeException('Legacy migration safety gate removed: '.$gate);
}
if (!str_contains($preflight, "u213420663_office_test")
    || !str_contains($preflight, 'information_schema.COLUMNS')
    || !str_contains($preflight, 'NON_UNIQUE=0')
    || !str_contains($preflight, 'SCHEMA_OK')) {
    throw new RuntimeException('Read-only DB preflight safety gate missing');
}
echo "CRM_DEAL_REVISION_MIGRATION_CONTRACT=PASS\n";
