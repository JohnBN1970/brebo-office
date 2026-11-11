<?php
declare(strict_types=1);
namespace Brebo\Office\WorkingBudget;

use DomainException;
use PDO;
use Throwable;

/**
 * Explicitly issues a draft purchase order after project authorization.
 * Idempotent on repeated issue of the same order; never modifies line prices.
 */
final class PurchaseOrderIssueService
{
    public function __construct(private readonly PDO $db, private readonly \Closure $authorizeProject) {}

    public function issue(int $projectId, int $orderId, int $actorId): array
    {
        if ($projectId < 1 || $orderId < 1 || $actorId < 1) {
            throw new \InvalidArgumentException('Invalid purchase order issue request.');
        }
        if (($this->authorizeProject)($projectId, $actorId, 'issue_purchase_order') !== true) {
            throw new DomainException('Purchase order issue access denied.');
        }
        if ($this->db->inTransaction()) throw new DomainException('Issue service owns transaction.');
        $this->db->beginTransaction();
        try {
            $select = $this->db->prepare(
                'SELECT id, project_id, status FROM office_work_budget_purchase_orders WHERE id = ? FOR UPDATE'
            );
            $select->execute([$orderId]);
            $order = $select->fetch(PDO::FETCH_ASSOC);
            if (!is_array($order) || (int)$order['project_id'] !== $projectId) {
                throw new DomainException('Purchase order not found for project.');
            }
            if ($order['status'] === 'issued') {
                $this->db->commit();
                return ['order_id'=>$orderId,'issued'=>false];
            }
            if ($order['status'] !== 'draft') {
                throw new DomainException('Only draft orders can be issued.');
            }
            $lines = $this->db->prepare(
                'SELECT l.id, l.quantity_milli, l.unit_price_cents,
                        b.cost_type, b.baseline_id, bl.locked_at,
                        t.office_project_id, m.id AS identity_id
                 FROM office_work_budget_purchase_order_lines l
                 JOIN office_calc_work_budget_lines b ON b.id = l.baseline_line_id
                 JOIN office_calc_work_budget_baselines bl ON bl.id = b.baseline_id
                 JOIN office_calc_crm_acceptance_transfers t ON t.id = bl.acceptance_transfer_id
                 LEFT JOIN office_work_budget_material_identity m ON m.baseline_line_id = b.id
                 WHERE l.order_id = ? FOR UPDATE'
            );
            $lines->execute([$orderId]);
            $found = false;
            foreach ($lines->fetchAll(PDO::FETCH_ASSOC) as $line) {
                $found = true;
                if ((int)$line['office_project_id'] !== $projectId
                    || $line['cost_type'] !== 'material'
                    || $line['locked_at'] === null
                    || $line['identity_id'] === null
                    || !preg_match('/^[1-9][0-9]*$/D', (string)$line['quantity_milli'])
                    || !preg_match('/^(0|[1-9][0-9]*)$/D', (string)$line['unit_price_cents'])) {
                    throw new DomainException('Purchase order contains an invalid project material.');
                }
            }
            if (!$found) throw new DomainException('Cannot issue an empty purchase order.');
            $update = $this->db->prepare(
                "UPDATE office_work_budget_purchase_orders SET status = 'issued', issued_at = CURRENT_TIMESTAMP
                 WHERE id = ? AND project_id = ? AND status = 'draft'"
            );
            $update->execute([$orderId,$projectId]);
            if ($update->rowCount() !== 1) throw new DomainException('Order state changed.');
            $this->db->commit();
            return ['order_id'=>$orderId,'issued'=>true];
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }
}
