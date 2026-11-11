<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Workflow/OfficeCommercialAcceptancePolicy.php';
use Brebo\Office\Workflow\OfficeCommercialAcceptancePolicy as Policy;
function denied(array $input): void {
    try { Policy::validate($input); } catch (DomainException $expected) { return; }
    throw new RuntimeException('Unsafe acceptance allowed');
}
$base = [
    'kind'=>'direct_order',
    'opportunity_id'=>4,
    'operator_id'=>7,
    'quotation_id'=>null,
    'evidence_reference'=>'Signed order reference 2026-01',
    'idempotency_key'=>str_repeat('a',64),
];
Policy::validate($base);
denied(array_replace($base,['evidence_reference'=>' ']));
denied(array_replace($base,['operator_id'=>0]));
denied(array_replace($base,['quotation_id'=>1]));
denied(array_replace($base,['idempotency_key'=>'bad']));
denied(array_replace($base,['kind'=>'quotation']));
denied(array_replace($base,['amount_ex_vat_cents'=>-1]));
denied(array_replace($base,['amount_ex_vat_cents'=>'1000']));
denied(array_replace($base,['evidence_document_id'=>'1']));
denied(array_replace($base,['evidence_reference'=>str_repeat('x',256)]));
Policy::validate(array_replace($base,['amount_ex_vat_cents'=>0]));
Policy::validate(array_replace($base,['kind'=>'quotation','quotation_id'=>12]));
echo "Commercial acceptance policy OK\n";
