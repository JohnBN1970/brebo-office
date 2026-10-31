<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeOfferVersionRepository.php';

use Brebo\Office\Offer\OfficeOfferVersionRepository;

$method = new ReflectionMethod(OfficeOfferVersionRepository::class, 'createDraftVersion');
if ($method->getNumberOfRequiredParameters() !== 2) {
    throw new RuntimeException('Unexpected offer version signature');
}
$source = file_get_contents(__DIR__ . '/../src/Offer/OfficeOfferVersionRepository.php');
if (!is_string($source)) {
    throw new RuntimeException('Offer version source missing');
}
foreach ([
    'FOR UPDATE',
    'beginTransaction()',
    'commit()',
    'rollBack()',
    'MAX(version_number)',
    'Only draft offers may receive new versions',
    'JSON_THROW_ON_ERROR',
] as $expected) {
    if (!str_contains($source, $expected)) {
        throw new RuntimeException('Missing version safety boundary: ' . $expected);
    }
}
foreach (['office_project_terms', 'office_project_change_orders', 'UPDATE office_offer_versions', 'DELETE FROM office_offer_versions'] as $forbidden) {
    if (str_contains($source, $forbidden)) {
        throw new RuntimeException('Offer version repository crossed domain boundary');
    }
}
echo "OfficeOfferVersionContractTest: PASS\n";
