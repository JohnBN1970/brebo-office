<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OfficeApprovedLabourCostSchema.php';
require_once dirname(__DIR__).'/src/Inzet/OfficeApprovedLabourCostPostingService.php';
use Brebo\Office\Inzet\OfficeApprovedLabourCostSchema as S;
$ddl=implode("\n",S::statements());
foreach (['office_inzet_actual_labour_costs','office_inzet_actual_labour_rates',
    'UNIQUE KEY uq_actual_labour_approval','FOREIGN KEY (approved_hours_id)',
    'authorized_by','revoked_at'] as $part) {
    if(!str_contains($ddl,$part)) throw new RuntimeException('Missing labour ledger guard: '.$part);
}
$service=file_get_contents(dirname(__DIR__).'/src/Inzet/OfficeApprovedLabourCostPostingService.php');
foreach (['beginTransaction()','FOR UPDATE','OfficeApprovedLabourValuation::prepare',
    'commit()','rollBack()','source_sha256','office_inzet_actual_labour_rates'] as $part) {
    if(!str_contains($service,$part)) throw new RuntimeException('Missing posting guard: '.$part);
}
foreach (['public function postInsideTransaction(', 'Approval transaction required', 'postInsideTransaction($approvedHoursId)'] as $part) {
    if (!str_contains($service,$part)) throw new RuntimeException('Missing atomic posting contract: '.$part);
}
$approval=file_get_contents(dirname(__DIR__).'/src/Inzet/OfficeOnSiteApprovalService.php');
foreach (['OFFICE_TEST_ACTUAL_LABOUR_COST_POSTING_ENABLED', 'OfficeApprovedLabourCostPostingService($this->db))->postInsideTransaction($bookingId)', 'Actual labour cost posting conflicts with legacy budget debit modes', 'OfficeOnSiteFourEyesGuard'] as $part) {
    if (!str_contains($approval,$part)) throw new RuntimeException('Missing OnSite cost posting guard: '.$part);
}
echo "ACTUAL_LABOUR_COST_POSTING_CONTRACT_OK\\n";
