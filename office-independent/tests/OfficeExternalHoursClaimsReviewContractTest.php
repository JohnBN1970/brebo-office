<?php
declare(strict_types=1);
$source=file_get_contents(dirname(__DIR__).'/src/Inzet/OfficeExternalHoursClaimsReviewRepository.php');
foreach (['WHERE project_id=:project AND id<:before','ORDER BY id DESC LIMIT :limit',
    "PDO::PARAM_INT","pending_reconciliation","Invalid review query scope"] as $required) {
    if (!str_contains($source,$required)) throw new RuntimeException('Missing read-only review scope: '.$required);
}
foreach (['INSERT INTO','UPDATE office_inzet','DELETE FROM'] as $forbidden) {
    if (str_contains($source,$forbidden)) throw new RuntimeException('Unexpected write in review query');
}
echo "EXTERNAL_HOURS_REVIEW_QUERY_CONTRACT_OK\n";
