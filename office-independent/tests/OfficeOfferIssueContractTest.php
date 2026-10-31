<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeOfferRepository.php';

use Brebo\Office\Offer\OfficeOfferRepository;

$method = new ReflectionMethod(OfficeOfferRepository::class, 'issueVersion');
if ($method->getNumberOfRequiredParameters() !== 2) {
    throw new RuntimeException('Invalid issue signature');
}
$source = file_get_contents(__DIR__ . '/../src/Offer/OfficeOfferRepository.php');
foreach (['FOR UPDATE', 'beginTransaction()', 'commit()', 'rollBack()', 'Version issue conflict', 'Offer issue conflict', "status = 'issued'"] as $required) {
    if (!str_contains($source, $required)) {
        throw new RuntimeException('Missing issue guard: ' . $required);
    }
}
foreach (['UPDATE office_project_change_orders', 'UPDATE office_project_contracts', 'UPDATE office_project_terms'] as $forbidden) {
    if (str_contains($source, $forbidden)) {
        throw new RuntimeException('Issue must not change contracts');
    }
}
echo "OfficeOfferIssueContractTest: PASS\n";
