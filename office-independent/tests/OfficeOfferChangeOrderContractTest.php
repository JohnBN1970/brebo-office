<?php

declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../src/Offer/OfficeOfferApplication.php');
if (!is_string($source)) {
    throw new RuntimeException('Application unavailable');
}
foreach ([
    'requireMatchingChangeOrder($draft)',
    '$this->contracts->forProject($projectId)',
    '$this->changes->forContract((int) $contract[\'id\'])',
    "Change-order type mismatch",
    "Change-order must be a draft",
    "Change-order does not belong to project",
] as $required) {
    if (!str_contains($source, $required)) {
        throw new RuntimeException('Missing change-order verification: ' . $required);
    }
}
echo "OfficeOfferChangeOrderContractTest: PASS\n";
