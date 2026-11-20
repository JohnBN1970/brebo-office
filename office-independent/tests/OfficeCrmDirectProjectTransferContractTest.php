<?php
declare(strict_types=1);
// Static contract gate for direct-order transfer. Database integration is a separate VPS acceptance.
$root=dirname(__DIR__);
$service=file_get_contents($root.'/src/Crm/OfficeCrmDirectProjectTransfer.php');
$sql=file_get_contents($root.'/deployment/hostinger/sql/office-crm-direct-project-transfer.sql');
if ($service===false || $sql===false) throw new RuntimeException('Direct project transfer sources missing');
foreach ([
    'beginTransaction()', 'FOR UPDATE', "['won','closed']", 'office_calc_crm_acceptance_transfers',
    'office_crm_direct_project_transfers', 'office_projects', 'rollBack()', 'commit()',
    '$createProject($this->db,$opportunity)'
] as $required) {
    if (!str_contains($service,$required)) throw new RuntimeException('Missing direct transfer invariant: '.$required);
}
// A retry must not return a stale project ID when the mapped project was deleted.
// Keep this check alongside the transfer service so the CI contract gate catches regressions.
foreach (['$verifyExisting=', 'SELECT id FROM office_projects WHERE id=?',
    'Direct transfer mapping references a missing project'] as $required) {
    if (!str_contains($service,$required)) throw new RuntimeException('Missing orphaned mapping protection: '.$required);
}
foreach (['PRIMARY KEY (opportunity_id)','UNIQUE KEY uq_direct_project (project_id)',
    'FOREIGN KEY (project_id) REFERENCES office_projects(id)',
    'FOREIGN KEY (opportunity_id) REFERENCES office_crm_opportunities(id)'] as $required) {
    if (!str_contains($sql,$required)) throw new RuntimeException('Missing direct transfer schema invariant: '.$required);
}
echo "CRM_DIRECT_PROJECT_TRANSFER_CONTRACT=PASS\n";
