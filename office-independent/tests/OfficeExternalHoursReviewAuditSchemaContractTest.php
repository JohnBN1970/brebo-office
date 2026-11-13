<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OfficeExternalHoursReviewAuditSchema.php';
use Brebo\Office\Inzet\OfficeExternalHoursReviewAuditSchema;
$ddl=implode("\n",OfficeExternalHoursReviewAuditSchema::statements());
foreach (['office_inzet_external_hours_review_decisions','external_claim_id',
    'source_line_sha256','approved_payable_seconds','first_reviewer','second_reviewer',
    'uq_external_review_decision_reference','fk_external_review_claim',
    'chk_external_review_distinct'] as $part) {
    if (!str_contains($ddl,$part)) throw new RuntimeException('Missing audit contract: '.$part);
}
if (str_contains($ddl,'office_inzet_approved_hours') || str_contains($ddl,'ON UPDATE CASCADE')) {
    throw new RuntimeException('Audit must not rewrite OnSite hours');
}
echo "EXTERNAL_HOURS_REVIEW_AUDIT_SCHEMA_OK\n";
