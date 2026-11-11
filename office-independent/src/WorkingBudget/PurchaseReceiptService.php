<?php
declare(strict_types=1);
namespace Brebo\Office\WorkingBudget;

use DomainException;
use PDO;
use Throwable;

/**
 * Records a partial delivery against an issued Office purchase order.
 * The caller MUST authorize the project and actor before invoking.
 * Receipt keys provide retry idempotency. Never updates Finance actuals.
 */
final class PurchaseReceiptService
{
    public function __construct(private readonly PDO $db) {}

    public function receive(int $projectId, int $orderLineId, int $actorId, int $quantityMilli, string $receiptKey, ?int $buildingId = null, ?int $zoneId = null): array
    {
        if ($projectId < 1 || $orderLineId < 1 || $actorId < 1 || $quantityMilli < 1
            || !preg_match('/^[a-f0-9]{64}$/D', $receiptKey)
            || ($buildingId !== null && $buildingId < 1)
            || ($zoneId !== null && ($zoneId < 1 || $buildingId === null))) {
            throw new \InvalidArgumentException('Invalid delivery registration.');
        }
        if ($this->db->inTransaction()) throw new DomainException('Receipt service owns transaction.');
        $this->db->beginTransaction();
        try {
            $existing = $this->db->prepare('SELECT id, order_line_id, quantity_milli, building_id, zone_id, created_by FROM office_work_budget_receipts WHERE receipt_key = ?');
            $existing->execute([$receiptKey]);
            $prior = $existing->fetch(PDO::FETCH_ASSOC);
            if (is_array($prior)) {
                if ((int)$prior['order_line_id'] !== $orderLineId
                    || (int)$prior['quantity_milli'] !== $quantityMilli
                    || (int)$prior['created_by'] !== $actorId
                    || ($prior['building_id'] === null ? null : (int)$prior['building_id']) !== $buildingId
                    || ($prior['zone_id'] === null ? null : (int)$prior['zone_id']) !== $zoneId) {
                    throw new DomainException('Receipt key already used for different delivery.');
                }
                $verify = $this->db->prepare(
                    'SELECT o.project_id FROM office_work_budget_purchase_order_lines l
                     JOIN office_work_budget_purchase_orders o ON o.id = l.order_id WHERE l.id = ?'
                );
                $verify->execute([$orderLineId]);
                if ((int)$verify->fetchColumn() !== $projectId) throw new DomainException('Receipt project mismatch.');
                $this->db->commit();
                return ['receipt_id'=>(int)$prior['id'], 'created'=>false];
            }
            $lock = $this->db->prepare(
                'SELECT l.quantity_milli, o.status, o.project_id, b.baseline_id
                 FROM office_work_budget_purchase_order_lines l
                 JOIN office_work_budget_purchase_orders o ON o.id = l.order_id
                 JOIN office_calc_work_budget_lines b ON b.id = l.baseline_line_id
                 WHERE l.id = ? FOR UPDATE'
            );
            $lock->execute([$orderLineId]);
            $line = $lock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($line) || (int)$line['project_id'] !== $projectId || $line['status'] !== 'issued') {
                throw new DomainException('Purchase order is not issued for project.');
            }
            $received = $this->db->prepare('SELECT quantity_milli FROM office_work_budget_receipts WHERE order_line_id = ? FOR UPDATE');
            $received->execute([$orderLineId]);
            $remaining = (int)$line['quantity_milli'];
            foreach ($received->fetchAll(PDO::FETCH_COLUMN) as $raw) {
                $qty = filter_var((string)$raw, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]);
                if (!is_int($qty) || $qty > $remaining) throw new DomainException('Invalid existing receipts.');
                $remaining -= $qty;
            }
            if ($quantityMilli > $remaining) throw new DomainException('Delivery exceeds open order quantity.');
            $insert = $this->db->prepare(
                'INSERT INTO office_work_budget_receipts
                 (order_line_id,receipt_key,quantity_milli,building_id,zone_id,created_by)
                 VALUES (?,?,?,?,?,?)'
            );
            $insert->execute([$orderLineId,$receiptKey,$quantityMilli,$buildingId,$zoneId,$actorId]);
            $id = (int)$this->db->lastInsertId();
            $this->db->commit();
            return ['receipt_id'=>$id,'created'=>true];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
