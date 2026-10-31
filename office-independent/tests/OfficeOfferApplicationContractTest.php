<?php

declare(strict_types=1);

$root = dirname(__DIR__) . '/src/Offer/';
$app = file_get_contents($root . 'OfficeOfferApplication.php');
$access = file_get_contents($root . 'OfficeOfferAccess.php');
if (!is_string($app) || !is_string($access)) {
    throw new RuntimeException('Offer application source unavailable');
}
foreach ([
    'createProjectDraft',
    'listForProject',
    'canManageProject',
    'canReadProject',
    'requireMatchingChangeOrder',
    'Change-order does not belong to project',
    'Change-order type mismatch',
    'Change-order must be a draft',
] as $required) {
    if (!str_contains($app . $access, $required)) {
        throw new RuntimeException('Missing offer authorization contract: ' . $required);
    }
}
if (str_contains($app, 'createCrmDraft(') || str_contains($app, 'approveChangeOrder(')) {
    throw new RuntimeException('Unsafe unverified write surface');
}
echo "OfficeOfferApplicationContractTest: PASS\n";
