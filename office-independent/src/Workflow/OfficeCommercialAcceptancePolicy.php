<?php
declare(strict_types=1);
namespace Brebo\Office\Workflow;

/** Validates acceptance input; does not persist or change CRM status. */
final class OfficeCommercialAcceptancePolicy
{
    public static function validate(array $input): void
    {
        $kind = $input['kind'] ?? null;
        if (!in_array($kind, ['quotation', 'direct_order'], true)) {
            throw new \DomainException('Unsupported acceptance kind.');
        }
        $opportunityId = $input['opportunity_id'] ?? null;
        $operatorId = $input['operator_id'] ?? null;
        if (!is_int($opportunityId) || $opportunityId < 1 || !is_int($operatorId) || $operatorId < 1) {
            throw new \DomainException('Valid opportunity and operator are required.');
        }
        $quotationId = $input['quotation_id'] ?? null;
        if (($kind === 'quotation' && (!is_int($quotationId) || $quotationId < 1))
            || ($kind === 'direct_order' && $quotationId !== null)) {
            throw new \DomainException('Quotation reference is inconsistent with acceptance kind.');
        }
        $documentId = $input['evidence_document_id'] ?? null;
        $reference = $input['evidence_reference'] ?? null;
        if ((!is_int($documentId) || $documentId < 1)
            && (!is_string($reference) || trim($reference) === '')) {
            throw new \DomainException('Acceptance evidence is required.');
        }
        if (!is_string($input['idempotency_key'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $input['idempotency_key']) !== 1) {
            throw new \DomainException('Valid idempotency key is required.');
        }
    }
}
