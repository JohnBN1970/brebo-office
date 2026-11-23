<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$page=file_get_contents($root.'/public/office-crm-opportunity-transition.php');
$writer=file_get_contents($root.'/src/Crm/OfficeCrmDealHousingWriter.php');
$sql=file_get_contents($root.'/deployment/hostinger/sql/office-test-deal-housing-scope-review.sql');
if (!is_string($page)||!is_string($writer)||!is_string($sql)) throw new RuntimeException('Missing deal housing source');
foreach (['save_deal_housing','crm_opportunity_csrf','OFFICE_TEST_CRM_DEAL_HOUSING_ENABLED','OFFICE_TEST_CRM_DEAL_HOUSING_SCHEMA_VERIFIED','expected_revision','Woningtypen en aantallen'] as $required) {
    if (!str_contains($page,$required)) throw new RuntimeException('Missing HTTP contract: '.$required);
}
foreach (['FOR UPDATE','beginTransaction','rollBack','office_crm_deal_housing_events','office_housing_types','revision'] as $required) {
    if (!str_contains($writer,$required)) throw new RuntimeException('Missing writer contract: '.$required);
}
foreach (['office_crm_deal_housing_types','office_crm_deal_housing_events','CREATE TABLE IF NOT EXISTS'] as $required) {
    if (!str_contains($sql,$required)) throw new RuntimeException('Missing schema contract: '.$required);
}
echo "DEAL_HOUSING_HTTP_CONTRACT=OK\n";
