<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

use PDO;
use RuntimeException;

/**
 * Read-only scope quantities: per-dwelling quantities times assigned dwelling count.
 * No assumptions about prices; unpriced lines are never included in money totals.
 */
final class OfficeScopeQuantityReader {
  public function __construct(private readonly PDO $db) {}

  public function forProject(int $projectId): array {
    if ($projectId < 1) throw new RuntimeException('Ongeldig project.');
    $query = $this->db->prepare(
      "SELECT s.id AS scope_id, ht.code AS housing_code, ht.name AS housing_name,
              pht.dwelling_count, q.line_code, q.description, q.unit,
              q.quantity_per_dwelling, q.unit_price_ex_vat
       FROM office_project_calculation_scopes s
       INNER JOIN office_project_housing_types pht
         ON pht.project_id = s.project_id AND pht.housing_type_id = s.housing_type_id
       INNER JOIN office_housing_types ht
         ON ht.id = pht.housing_type_id AND ht.building_id = pht.building_id
       INNER JOIN office_scope_quantity_lines q ON q.scope_id = s.id
       WHERE s.project_id = :project AND s.scope_kind = 'housing_type'
       ORDER BY ht.code, q.line_code, q.id"
    );
    $query->execute([':project' => $projectId]);
    $lines = [];
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
      // DECIMAL values are intentionally retained as strings; do not use
      // floating-point calculations for contractual financial amounts.
      $row['dwelling_count'] = (int) $row['dwelling_count'];
      $row['total_quantity'] = self::multiplyDecimal((string) $row['quantity_per_dwelling'], $row['dwelling_count']);
      $lines[] = $row;
    }
    return $lines;
  }

  /**
   * Project roll-up by exact code, description and unit. Identical codes with
   * different descriptions or units stay separate to prevent false aggregation.
   * Monetary totals deliberately excluded until Calc pricing is integrated.
   */
  public function projectTotals(int $projectId): array {
    if ($projectId < 1) throw new RuntimeException('Ongeldig project.');
    $query = $this->db->prepare(
      "SELECT q.line_code, q.description, q.unit,
              CAST(SUM(q.quantity_per_dwelling * pht.dwelling_count) AS DECIMAL(38,4)) AS total_quantity,
              COUNT(DISTINCT s.housing_type_id) AS housing_type_count
       FROM office_project_calculation_scopes s
       INNER JOIN office_project_housing_types pht
         ON pht.project_id = s.project_id AND pht.housing_type_id = s.housing_type_id
       INNER JOIN office_housing_types ht
         ON ht.id = pht.housing_type_id AND ht.building_id = pht.building_id
       INNER JOIN office_scope_quantity_lines q ON q.scope_id = s.id
       WHERE s.project_id = :project AND s.scope_kind = 'housing_type'
       GROUP BY q.line_code, q.description, q.unit
       ORDER BY q.line_code, q.description, q.unit"
    );
    $query->execute([':project' => $projectId]);
    return $query->fetchAll(PDO::FETCH_ASSOC);
  }

  private static function multiplyDecimal(string $value, int $multiplier): string {
    if (!preg_match('/^([0-9]+)(?:\.([0-9]{1,4}))?$/D', $value) || $multiplier < 0) {
      throw new RuntimeException('Ongeldige hoeveelheid.');
    }
    [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
    $fraction = str_pad($fraction, 4, '0');
    $digits = ltrim($whole . $fraction, '0');
    if ($digits === '') return '0.0000';
    $carry = 0;
    $result = '';
    for ($i = strlen($digits) - 1; $i >= 0; --$i) {
      $product = ((int) $digits[$i]) * $multiplier + $carry;
      $result = ($product % 10) . $result;
      $carry = intdiv($product, 10);
    }
    while ($carry > 0) {
      $result = ($carry % 10) . $result;
      $carry = intdiv($carry, 10);
    }
    $result = str_pad($result, 5, '0', STR_PAD_LEFT);
    return substr($result, 0, -4) . '.' . substr($result, -4);
  }
}
