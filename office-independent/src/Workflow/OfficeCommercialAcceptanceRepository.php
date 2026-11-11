<?php
declare(strict_types=1);
namespace Brebo\Office\Workflow;

use PDO;
use DomainException;
use Throwable;

/**
 * Transactional acceptance registration. Call only after authenticating and
 * authorizing the operator and verifying the actual acceptance evidence.
 * Does not create a project, budget, invoice or other financial side effects.
 */
final class OfficeCommercialAcceptanceRepository
{
    public function __construct(private PDO $db) {}

    public function accept(array $input): int
    {
        OfficeCommercialAcceptancePolicy::validate($input);
        if ($this->db->inTransaction()) {
            throw new DomainException('Acceptance requires its own transaction boundary.');
        }
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare(
                "SELECT stage,status FROM office_crm_opportunities WHERE id=? FOR UPDATE"
            );
            $lock->execute([$input['opportunity_id']]);
            $opportunity = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$opportunity || $opportunity['status'] !== 'open'
                || !in_array($opportunity['stage'], ['prospect','opportunity','proposal','negotiation'], true)) {
                throw new DomainException('Opportunity is not eligible for acceptance.');
            }
            if ($input['kind'] === 'quotation' && !in_array($opportunity['stage'], ['proposal','negotiation'], true)) {
                throw new DomainException('Quotation acceptance requires an offer stage.');
            }
            // Only a verified evidence document can authorize a contractual status change.
            // Free-text references are retained for audit, but are not proof on their own.
            if (!isset($input['evidence_document_id']) || !is_int($input['evidence_document_id'])
                || $input['evidence_document_id'] < 1) {
                throw new DomainException('A verified acceptance document is required.');
            }
            if ($input['kind'] === 'quotation') {
                $q = $this->db->prepare(
                    "SELECT id FROM office_quotations WHERE id=? AND opportunity_id=? AND status='accepted' LIMIT 1 FOR UPDATE"
                );
                $q->execute([$input['quotation_id'], $input['opportunity_id']]);
                if (!$q->fetchColumn()) {
                    throw new DomainException('Accepted quotation not verified.');
                }
            }
            $insert = $this->db->prepare(
                'INSERT INTO office_commercial_acceptances
                 (opportunity_id,quotation_id,acceptance_kind,evidence_document_id,evidence_reference,
                  accepted_by_operator_id,accepted_at,contract_amount_ex_vat_cents,idempotency_key)
                 VALUES (?,?,?,?,?,?,UTC_TIMESTAMP(),?,?)'
            );
            $insert->execute([
                $input['opportunity_id'], $input['quotation_id'] ?? null, $input['kind'],
                $input['evidence_document_id'] ?? null, $input['evidence_reference'] ?? null,
                $input['operator_id'], $input['amount_ex_vat_cents'] ?? null, $input['idempotency_key'],
            ]);
            $acceptanceId = (int)$this->db->lastInsertId();
            $update = $this->db->prepare(
                "UPDATE office_crm_opportunities SET stage='won',status='closed'
                 WHERE id=? AND status='open' AND stage=?"
            );
            $update->execute([$input['opportunity_id'], $opportunity['stage']]);
            if ($update->rowCount() !== 1) {
                throw new DomainException('Opportunity changed during acceptance.');
            }
            $this->db->commit();
            return $acceptanceId;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }
}
