<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeOfferSchema.php';
require_once __DIR__ . '/../src/Offer/OfficeOfferAcceptanceRepository.php';

use Brebo\Office\Offer\OfficeOfferSchema;
use Brebo\Office\Offer\OfficeOfferAcceptanceRepository;

$schema = implode("\n", OfficeOfferSchema::statements());
if (!str_contains($schema, 'recorded_by_operator_id VARCHAR(128) NOT NULL')) {
    throw new RuntimeException('Acceptance operator identity must support string IDs');
}
foreach (['office_offer_acceptances', 'UNIQUE KEY uq_offer_acceptance', 'evidence_reference', 'recorded_by_operator_id'] as $required) {
    if (!str_contains($schema, $required)) {
        throw new RuntimeException('Missing acceptance schema: ' . $required);
    }
}
$method = new ReflectionMethod(OfficeOfferAcceptanceRepository::class, 'record');
if ($method->getNumberOfRequiredParameters() !== 6 || (string) $method->getParameters()[5]->getType() !== 'string|int' && (string) $method->getParameters()[5]->getType() !== 'int|string') {
    throw new RuntimeException('Acceptance evidence signature mismatch');
}
$source = file_get_contents(__DIR__ . '/../src/Offer/OfficeOfferAcceptanceRepository.php');
foreach (['FOR UPDATE', 'beginTransaction()', 'rollBack()', 'Only issued offers can be accepted', 'Only the issued offer version can be accepted', 'Acceptance state conflict'] as $required) {
    if (!str_contains($source, $required)) {
        throw new RuntimeException('Missing acceptance guard: ' . $required);
    }
}
foreach (['UPDATE office_project_change_orders', 'UPDATE office_project_contracts', 'UPDATE office_project_terms'] as $forbidden) {
    if (str_contains($source, $forbidden)) {
        throw new RuntimeException('Acceptance must not silently mutate contract');
    }
}
echo "OfficeOfferAcceptanceContractTest: PASS\n";
