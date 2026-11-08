<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$m=file_get_contents($root.'/deployment/hostinger/migrate-office-crm-legacy-opportunities-test.php');
$r=file_get_contents($root.'/src/Crm/OfficeCrmOpportunityRepository.php');
foreach (['OFFICE_CRM_LEGACY_MIGRATION','OFFICE_BASE_PATH','OFFICE_TEST_CONFIG_PATH','u213420663_office_test','legacy_rows_present_manual_mapping_required','partial_migration_inspect_first','ALTER TABLE office_crm_opportunities ADD COLUMN','CREATE','CRM_LEGACY_MIGRATION_COMPLETE'] as $marker) {
    if (!str_contains($m,$marker)) { fwrite(STDERR,"Missing safety marker: ".$marker."\n"); exit(1); }
}
foreach (['DROP TABLE','TRUNCATE TABLE','DELETE FROM office_crm_opportunities','RENAME TABLE'] as $forbidden) {
    if (str_contains($m,$forbidden)) { fwrite(STDERR,"Forbidden destructive statement: ".$forbidden."\n"); exit(1); }
}
if (!str_contains($r,"'prospect','EUR'")) { fwrite(STDERR,"Legacy currency insert missing\n"); exit(1); }
echo "CRM_LEGACY_MIGRATION_SAFETY_OK\n";
