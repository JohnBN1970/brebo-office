<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeOfferChangeLinkSchema.php';
require_once __DIR__ . '/../src/Offer/OfficeOfferChangeLinkRepository.php';

use Brebo\Office\Offer\OfficeOfferChangeLinkSchema;

$sql = implode("\n", OfficeOfferChangeLinkSchema::statements());
foreach ([
    'UNIQUE KEY uq_offer_link_acceptance',
    'UNIQUE KEY uq_offer_link_offer',
    'UNIQUE KEY uq_offer_link_change',
    'linked_by_operator_id',
] as $required) {
    if (!str_contains($sql, $required)) {
        throw new RuntimeException('Missing link constraint: ' . $required);
    }
}
$repository = file_get_contents(__DIR__ . '/../src/Offer/OfficeOfferChangeLinkRepository.php');
foreach ([
    'FOR UPDATE', 'beginTransaction()', 'commit()', 'rollBack()',
    'contract_project', 'actual_change_type', 'version_status',
] as $required) {
    if (!str_contains($repository, $required)) {
        throw new RuntimeException('Missing link verification: ' . $required);
    }
}
foreach ([
    'UPDATE office_project_contracts',
    'UPDATE office_project_change_orders',
    'UPDATE office_project_terms',
] as $forbidden) {
    if (str_contains($repository, $forbidden)) {
        throw new RuntimeException('Link repository must not alter contracts');
    }
}
echo "OfficeOfferChangeLinkSchemaContractTest: PASS\n";
