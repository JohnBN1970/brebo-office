<?php
declare(strict_types=1);
$src=file_get_contents(dirname(__DIR__).'/src/Inzet/OfficeExternalHoursReviewDecisionRepository.php');
foreach (['inTransaction()','FOR UPDATE','OfficeExternalHoursFourEyesPolicy::approve',
    'hash_equals','External hours claim already reviewed','INSERT INTO office_inzet_external_hours_review_decisions'] as $part) {
    if (!str_contains($src,$part)) throw new RuntimeException('Missing decision safeguard: '.$part);
}
foreach (['UPDATE office_inzet','DELETE FROM office_inzet'] as $bad) {
    if (str_contains($src,$bad)) throw new RuntimeException('Source mutation in decision repository');
}
echo "EXTERNAL_REVIEW_DECISION_CONTRACT_OK\n";
