<?php
declare(strict_types=1);

/** Source/fixture contract only. This does not substitute MySQL or browser E2E. */
$root=dirname(__DIR__);
$load=static function(string $path) use($root):string {
    $raw=file_get_contents($root.'/'.$path);
    if (!is_string($raw)) throw new RuntimeException('Missing source: '.$path);
    return $raw;
};
$start=$load('public/office-calc-crm-start.php');
$repo=$load('src/Calculation/OfficeCalcCrmDraftLineRepository.php');
$collab=$load('src/Calculation/OfficeCalcDraftCollaboration.php');
$project=$load('src/Calculation/OfficeCalcLaunchAccess.php');
$schema=$load('deployment/vps/sql/office-crm-calc-shared-identity-REVIEW.sql');
$preflight=$load('deployment/vps/crm-calc-schema-preflight.php');
$expect=static function(bool $ok,string $reason):void {
    if (!$ok) throw new RuntimeException($reason);
};
$expect(str_contains($start,'INSERT INTO office_calc_calculations (project_id) VALUES (NULL)'),
    'A projectless Deal must get a real canonical Office identity');
$expect(str_contains($start,'INSERT INTO office_calc_crm_drafts (opportunity_id,crm_revision,created_by,project_id,calculation_id)'),
    'Deal draft must bind the canonical Office calculation identity');
foreach ([$repo,$collab] as $reader) {
    $expect(str_contains($reader,'d.calculation_id=c.office_calculation_id'),
        'Calc lines and revisions must resolve via draft.calculation_id');
}
$expect(str_contains($repo,'$officeCalcId') && str_contains($repo,'INSERT INTO calculations'),
    'Calc row must be written under the mapped Office identity');
$expect(str_contains($project,'office_calc_project_access')
    && str_contains($project,'OfficeNativeProjectAccess'),
    'Existing project calculation launch needs both explicit rights');
foreach (['CREATE TABLE office_calc_calculations','CREATE TABLE office_calc_crm_drafts',
    'CREATE TABLE calculations','CREATE TABLE calculation_versions','CREATE TABLE calculation_lines',
    'CREATE TABLE office_calc_project_access', 'REFERENCES office_calc_calculations (id,project_id)',
    'UNIQUE KEY uq_calc_draft_opportunity','UNIQUE KEY uq_calc_draft_calculation',
    'REFERENCES office_calc_calculations(id)'] as $token) {
    $expect(str_contains($schema,$token),'Shared identity DDL missing: '.$token);
}
$expect(!str_contains($schema,'REFERENCES office_calc_crm_drafts(id)'),
    'Calc local row cannot refer to the CRM draft as its Office identity');
foreach (['DRAFT_LINKS_CANONICAL_CALC','CALC_LINKS_CANONICAL_OFFICE_CALC',
    'CRM_DRAFT_UNIQUE_CALC_ID','OFFICE_CANONICAL_CALC_PROJECT_OPTIONAL'] as $token) {
    $expect(str_contains($preflight,$token),'VPS preflight missing: '.$token);
}
echo "CRM_CALC_SHARED_IDENTITY_SOURCE_CONTRACT=PASS\n";
