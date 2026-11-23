<?php
declare(strict_types=1);

// Structural safety gate only: live database and authenticated E2E remain separate.
$root = dirname(__DIR__);
$router = file_get_contents($root.'/deployment/hostinger/office-test-index.php');
$vpsRouter = file_get_contents($root.'/public/index.php');
$start = file_get_contents($root.'/public/office-calc-crm-start.php');
$deal = file_get_contents($root.'/public/office-crm-opportunity-transition.php');
$workbench = file_get_contents($root.'/public/office-calc-crm-workbench.php');
foreach ([$router,$vpsRouter,$start,$deal,$workbench] as $source) {
    if (!is_string($source)) throw new RuntimeException('Missing Deal Calc source');
}
$checks = [
    'route'=>[$router,"'/office-test/calculatie/start' => 'office-calc-crm-start.php'"],
    'vps-route'=>[$vpsRouter,"'/office-test/calculatie/start' => 'office-calc-crm-start.php'"],
    'deal-action'=>[$deal,'Calculatie starten of openen'],
    'launch-flag'=>[$start,"getenv('OFFICE_CALC_CRM_DRAFT_LAUNCH_ENABLED') !== 'enabled'"],
    'schema-gate'=>[$start,"getenv('OFFICE_CALC_CRM_SCHEMA_VERIFIED') !== 'enabled'"],
    'crm-gate'=>[$start,"getenv('OFFICE_CRM_OPPORTUNITIES_SCHEMA_VERIFIED') !== 'enabled'"],
    'session-guard'=>[$start,'findActive($session, time())'],
    'csrf-guard'=>[$start,'hash_equals($csrf,'],
    'origin-guard'=>[$start,"HTTP_ORIGIN"],
    'locking'=>[$start,"FOR UPDATE"],
    'revision-match'=>[$start,"$"."payload['revision']"],
    'draft-unique-context'=>[$start,'WHERE opportunity_id=?'],
    'projectless-office-identity'=>[$start,'INSERT INTO office_calc_calculations (project_id) VALUES (NULL)'],
    'projectless-draft-binding'=>[$start,'(opportunity_id,crm_revision,created_by,project_id,calculation_id) VALUES (?,?,?,NULL,?)'],
    'canonical-redirect'=>[$start,'Location: /office-test/calculatie?draft_id='],
    'existing-draft-consumer'=>[$workbench,'office_calc_crm_drafts'],
    'canonical-lines'=>[$workbench,'OfficeCalcCrmDraftLineRepository'],
];
foreach ($checks as $label=>[$source,$expected]) {
    if (!str_contains($source,$expected)) throw new RuntimeException('Deal Calc contract missing: '.$label);
}
if (str_contains($start,'office_projects') || str_contains($start,'office_project_id')) {
    throw new RuntimeException('Deal Calc start must not create or demand a project');
}
if (!str_contains($start,'$db->beginTransaction()') || !str_contains($start,'$db->commit()')
    || !str_contains($start,'$db->rollBack()')) {
    throw new RuntimeException('Deal Calc creation must be atomic');
}
echo "CALC_DEAL_START_ROUTE=PASS\n";
