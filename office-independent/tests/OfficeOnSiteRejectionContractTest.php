<?php
declare(strict_types=1);
$service=file_get_contents(dirname(__DIR__).'/src/Inzet/OfficeOnSiteApprovalService.php');
$page=file_get_contents(dirname(__DIR__).'/public/office-onsite-presence-review.php');
$installer=file_get_contents(dirname(__DIR__).'/deployment/hostinger/install-onsite-rejection-reasons-schema.php');
foreach (['function reject(', 'OnSite event pair is not a verified interval',
 'office_onsite_rejection_reasons', "'rejected',0", '$this->db->rollBack()',
 'OFFICE_TEST_ONSITE_REJECTION_SCHEMA_VERIFIED'] as $part) {
 if (!str_contains($service,$part)) throw new RuntimeException('Missing rejection guard: '.$part);
}
foreach (['review_action', 'rejection_reason', '->reject(', 'Registratie afgekeurd; geen uren geboekt.'] as $part) {
 if (!str_contains($page,$part)) throw new RuntimeException('Missing rejection UI: '.$part);
}
foreach (['PHP_SAPI', "'/office-test'", "'_office_test'", '--preflight', '--apply'] as $part) {
 if (!str_contains($installer,$part)) throw new RuntimeException('Missing test-only installer guard: '.$part);
}
echo "ONSITE_REJECTION_CONTRACT_OK\n";
