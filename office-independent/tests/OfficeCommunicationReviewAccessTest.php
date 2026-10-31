<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Communication/OfficeCommunicationReviewAccess.php';

use Brebo\Office\Communication\OfficeCommunicationReviewAccess;

$cases = [
    [false, ['communication.review'], false],
    [true, [], false],
    [true, ['communication.read'], false],
    [true, ['communication.review'], true],
    [true, ['Communication.Review'], false],
];
foreach ($cases as [$authenticated, $permissions, $expected]) {
    if (OfficeCommunicationReviewAccess::canReview($authenticated, $permissions) !== $expected) {
        throw new RuntimeException('Communication review access failed closed contract.');
    }
}
if (!OfficeCommunicationReviewAccess::canRead(true, ['communication.review'])
    || OfficeCommunicationReviewAccess::canRead(false, ['communication.read'])) {
    throw new RuntimeException('Communication read access failed closed contract.');
}
echo "Office communication access contract OK\n";
