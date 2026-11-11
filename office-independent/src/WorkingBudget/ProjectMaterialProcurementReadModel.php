<?php
declare(strict_types=1);
namespace Brebo\Office\WorkingBudget;

use DomainException;
use PDO;
use RuntimeException;

/**
 * Read-only project-scoped material procurement overview.
 * Project ACL is mandatory; only issued orders count as commitments.
 * Missing baseline or schema must be surfaced, never converted into zero.
 */
final class ProjectMaterialProcurementReadModel
{
    public function __construct(private readonly PDO $db, private readonly \Closure $authorizeProject) {}

    public function forProject(int $projectId, int $actorId): array
    {
        if ($projectId < 1 || $actorId < 1) throw new \InvalidArgumentException('Invalid project or actor.');
        if (($this->authorizeProject)($projectId, $actorId, 'view_project_procurement') !== true) {
            throw new DomainException('Project procurement access denied.');
        }
        $baselineQuery = $this->db->prepare(
            'SELECT b.id FROM office_calc_crm_acceptance_transfers t
             JOIN office_calc_work_budget_baselines b ON b.acceptance_transfer_id = t.id
             WHERE t.office_project_id = ? AND b.locked_at IS NOT NULL
             ORDER BY b.version_number DESC, b.id DESC LIMIT 1'
        );
        $baselineQuery->execute([$projectId]);
        $baselineId = $baselineQuery->fetchColumn();
        if ($baselineId === false) {
            return ['state'=>'baseline_missing', 'rows'=>null, 'unmatched'=>null];
        }
        require_once __DIR__.'/MaterialIdentityReadModel.php';
        $material = (new MaterialIdentityReadModel($this->db))->forBaseline((int)$baselineId);
        $ordersQuery = $this->db->prepare(
            'SELECT l.id AS order_line_id, m.article_code, m.specification, m.unit,
                    l.quantity_milli
             FROM office_work_budget_purchase_order_lines l
             JOIN office_work_budget_purchase_orders o ON o.id = l.order_id
             JOIN office_calc_work_budget_lines b ON b.id = l.baseline_line_id
             JOIN office_work_budget_material_identity m ON m.baseline_line_id = b.id
             WHERE o.project_id = ? AND o.status = ? AND b.baseline_id = ?
             ORDER BY l.id'
        );
        $ordersQuery->execute([$projectId,'issued',(int)$baselineId]);
        $orders = [];
        foreach ($ordersQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $orders[] = [
                'order_line_id'=>(string)$row['order_line_id'],
                'article_code'=>(string)$row['article_code'],
                'specification'=>(string)$row['specification'],
                'unit'=>(string)$row['unit'],
                'quantity_milli'=>self::quantity($row['quantity_milli']),
            ];
        }
        $receiptsQuery = $this->db->prepare(
            'SELECT r.id AS receipt_id, r.order_line_id, r.quantity_milli
             FROM office_work_budget_receipts r
             JOIN office_work_budget_purchase_order_lines l ON l.id = r.order_line_id
             JOIN office_work_budget_purchase_orders o ON o.id = l.order_id
             JOIN office_calc_work_budget_lines b ON b.id = l.baseline_line_id
             WHERE o.project_id = ? AND o.status = ? AND b.baseline_id = ?
             ORDER BY r.id'
        );
        $receiptsQuery->execute([$projectId,'issued',(int)$baselineId]);
        $receipts = [];
        foreach ($receiptsQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $receipts[] = [
                'receipt_id'=>(string)$row['receipt_id'],
                'order_line_id'=>(string)$row['order_line_id'],
                'quantity_milli'=>self::quantity($row['quantity_milli']),
            ];
        }
        require_once __DIR__.'/MaterialProcurementProjection.php';
        $rows = MaterialProcurementProjection::summarize($material['groups'], $orders, $receipts);
        return [
            'state'=>'ready',
            'baseline_id'=>(int)$baselineId,
            'rows'=>$rows,
            'unmatched'=>$material['unmatched'],
        ];
    }

    private static function quantity(mixed $raw): int
    {
        $string = (string)$raw;
        if (!preg_match('/^(0|[1-9][0-9]*)$/D', $string)) {
            throw new RuntimeException('Invalid procurement quantity.');
        }
        $value = filter_var($string, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]);
        if (!is_int($value)) throw new RuntimeException('Procurement quantity overflow.');
        return $value;
    }
}
