<?php
declare(strict_types=1);
namespace Brebo\Office\WorkingBudget;

use DomainException;
use PDO;
use Throwable;

/**
 * Creates a draft purchase order for one or more locked budget lines.
 * Caller supplies the authoritative project ACL check. Drafts do not create
 * issued commitments and may not be received until separately issued.
 */
final class PurchaseOrderService
{
    public function __construct(private readonly PDO $db, private readonly \Closure $authorizeProject) {}

    public function createDraft(int $projectId, int $actorId, int $supplierRelationId, string $orderRef, array $lines): int
    {
        if ($projectId < 1 || $actorId < 1 || $supplierRelationId < 1
            || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/D', $orderRef)
            || $lines === []) throw new \InvalidArgumentException('Invalid purchase order.');
        if (($this->authorizeProject)($projectId, $actorId, 'create_purchase_order') !== true) {
            throw new DomainException('Purchase order access denied.');
        }
        if ($this->db->inTransaction()) throw new DomainException('Purchase order service owns transaction.');
        $validated = [];
        foreach ($lines as $line) {
            if (!is_array($line) || !is_int($line['baseline_line_id'] ?? null)
                || $line['baseline_line_id'] < 1
                || !is_int($line['quantity_milli'] ?? null) || $line['quantity_milli'] < 1
                || !is_int($line['unit_price_cents'] ?? null) || $line['unit_price_cents'] < 0
                || isset($validated[$line['baseline_line_id']])) {
                throw new \InvalidArgumentException('Invalid or duplicate purchase order line.');
            }
            $validated[$line['baseline_line_id']] = $line;
        }
        $this->db->beginTransaction();
        try {
            $check = $this->db->prepare(
                'SELECT l.cost_type, t.office_project_id, m.id AS identity_id
                 FROM office_calc_work_budget_lines l
                 JOIN office_calc_work_budget_baselines b ON b.id = l.baseline_id
                 JOIN office_calc_crm_acceptance_transfers t ON t.id = b.acceptance_transfer_id
                 LEFT JOIN office_work_budget_material_identity m ON m.baseline_line_id = l.id
                 WHERE l.id = ? AND b.locked_at IS NOT NULL FOR UPDATE'
            );
            foreach ($validated as $baselineId => $line) {
                $check->execute([$baselineId]);
                $source = $check->fetch(PDO::FETCH_ASSOC);
                if (!is_array($source) || (int)$source['office_project_id'] !== $projectId
                    || $source['cost_type'] !== 'material' || $source['identity_id'] === null) {
                    throw new DomainException('Purchase line requires a verified project material baseline.');
                }
            }
            $insert = $this->db->prepare(
                'INSERT INTO office_work_budget_purchase_orders
                 (project_id,supplier_relation_id,order_ref,status) VALUES (?,?,?,?)'
            );
            $insert->execute([$projectId,$supplierRelationId,$orderRef,'draft']);
            $orderId = (int)$this->db->lastInsertId();
            $insertLine = $this->db->prepare(
                'INSERT INTO office_work_budget_purchase_order_lines
                 (order_id,baseline_line_id,quantity_milli,unit_price_cents) VALUES (?,?,?,?)'
            );
            foreach ($validated as $line) {
                $insertLine->execute([$orderId,$line['baseline_line_id'],$line['quantity_milli'],$line['unit_price_cents']]);
            }
            $this->db->commit();
            return $orderId;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }
}
