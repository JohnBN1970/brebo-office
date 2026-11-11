<?php
declare(strict_types=1);
namespace Brebo\Office\WorkingBudget;
use PDO;
use RuntimeException;

/** Read-only, fail-closed article identity lookup for a verified baseline. */
final class MaterialIdentityReadModel
{
    public function __construct(private readonly PDO $db) {}

    public function forBaseline(int $baselineId): array
    {
        if ($baselineId < 1) throw new \InvalidArgumentException('Invalid baseline.');
        $query = $this->db->prepare(
            'SELECT l.source_line_id, l.activity_ref, l.budget_cents, l.quantity,
                    m.article_code, m.specification, m.unit, m.building_id, m.zone_id
             FROM office_calc_work_budget_lines l
             LEFT JOIN office_work_budget_material_identity m ON m.baseline_line_id = l.id
             WHERE l.baseline_id = :baseline AND l.cost_type = :material
             ORDER BY l.id'
        );
        $query->execute(['baseline'=>$baselineId, 'material'=>'material']);
        $items = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $quantity = (string)$row['quantity'];
            $amount = (string)$row['budget_cents'];
            foreach ([$quantity, $amount] as $raw) {
                if (!preg_match('/^(0|[1-9][0-9]*)$/D', $raw)
                    || filter_var($raw, FILTER_VALIDATE_INT, ['options'=>['min_range'=>0]]) === false) {
                    throw new RuntimeException('Invalid material quantity or amount.');
                }
            }
            $quantityInt = (int)$quantity;
            if ($quantityInt > intdiv(PHP_INT_MAX, 1000)) {
                throw new RuntimeException('Material quantity overflow.');
            }
            $items[] = [
                'source_line_id'=>(string)$row['source_line_id'],
                'activity_ref'=>$row['activity_ref'],
                'article_code'=>$row['article_code'],
                'specification'=>$row['specification'],
                'unit'=>$row['unit'],
                'building_id'=>$row['building_id'] === null ? null : (int)$row['building_id'],
                'zone_id'=>$row['zone_id'] === null ? null : (int)$row['zone_id'],
                'quantity_milli'=>$quantityInt * 1000,
                'budget_cents'=>(int)$amount,
            ];
        }
        require_once __DIR__ . '/MaterialConsolidation.php';
        return MaterialConsolidation::group($items);
    }
}
