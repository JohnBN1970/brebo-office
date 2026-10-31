<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeOfferChangeLinkApplication.php';
require_once __DIR__ . '/../src/Offer/OfficeOfferAccess.php';

use Brebo\Office\Offer\OfficeOfferChangeLinkApplication;

$method = new ReflectionMethod(OfficeOfferChangeLinkApplication::class, 'linkAcceptedChange');
if ($method->getNumberOfRequiredParameters() !== 2) {
    throw new RuntimeException('Unexpected link signature');
}
$app = file_get_contents(__DIR__ . '/../src/Offer/OfficeOfferChangeLinkApplication.php');
$access = file_get_contents(__DIR__ . '/../src/Offer/OfficeOfferAccess.php');
foreach (['canManageProject', 'Project management access required', 'office_offer_acceptances', 'links->link'] as $expected) {
    if (!str_contains($app, $expected)) {
        throw new RuntimeException('Missing authorization boundary: ' . $expected);
    }
}
if (str_contains($access, '->canRead(')) {
    throw new RuntimeException('Native project access has no canRead method');
}
echo "OfficeOfferChangeLinkApplicationContractTest: PASS\n";
