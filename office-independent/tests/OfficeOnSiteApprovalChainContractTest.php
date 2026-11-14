<?php
declare(strict_types=1);

// Regression gates for the authenticated OnSite approval -> ledger -> portfolio chain.
// A real database-backed trial booking remains a separate runtime acceptance check.
$root=dirname(__DIR__);
$service=file_get_contents($root.'/src/Inzet/OfficeOnSiteApprovalService.php');
$controller=file_get_contents($root.'/public/office-onsite-presence-review.php');
$portfolio=file_get_contents($root.'/public/office-projects.php');
$weekly=file_get_contents($root.'/src/Inzet/OfficeApprovedHoursWeekRepository.php');
foreach ([$service,$controller,$portfolio,$weekly] as $source) {
    if (!is_string($source) || $source==='') throw new RuntimeException('Required OnSite source unavailable');
}
$require=static function(bool $condition,string $error):void {
    if (!$condition) throw new RuntimeException($error);
};
$require(str_contains($service,'$seconds < 1 || $seconds > 86400'),'Zero-length OnSite interval must be rejected');
$require(str_contains($service,'$this->db->beginTransaction()') && str_contains($service,'$this->db->rollBack()'),
    'Approval and ledger booking must use a transaction');
$require(str_contains($service,'FOR UPDATE') && str_contains($service,'Previously approved OnSite booking missing or changed'),
    'Duplicate approval must verify immutable existing booking');
$require(str_contains($service, "OFFICE_TEST_ONSITE_FOUR_EYES_REQUIRED")
    && str_contains($service, 'OfficeOnSiteFourEyesGuard($this->db))->assertIndependent($reviewer,$personId)'),
    'Independent reviewer must be enforced when the four-eyes flag is enabled');
$require(str_contains($controller,"OFFICE_TEST_ONSITE_HOURS_APPROVAL_ENABLED") && str_contains($controller,'hash_equals('),
    'OnSite feature flag and CSRF protection required');
$require(str_contains($weekly,'office_inzet_approved_hours') && str_contains($weekly,"m.permission IN ('read','manage')"),
    'Approved weekly hours must come from the ACL-scoped ledger');
$require(str_contains($portfolio,'Approved labour hours are an operational project KPI'),
    'Approved weekly hours must not require Finance permission');
$require(str_contains($portfolio,"$"."portfolioTotals['approved_week_seconds']"),
    'Approved week total must reach the portfolio view');
echo "ONSITE_APPROVAL_CHAIN_CONTRACT_OK\n";
