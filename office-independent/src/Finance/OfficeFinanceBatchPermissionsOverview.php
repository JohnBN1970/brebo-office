<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

use PDO;

/**
 * Read-only Office Finance permissions screen model.
 * The calling route must independently authorize its viewer as a role administrator.
 * No grants are assigned, no bank action is available.
 */
final class OfficeFinanceBatchPermissionsOverview {
  public function __construct(private readonly PDO $db) {}

  public function forOperator(string $operatorId): array {
    if ($operatorId === '' || strlen($operatorId) > 128) {
      return ['operator_id' => '', 'active' => false, 'permissions' => []];
    }
    try {
      $q = $this->db->prepare('SELECT active FROM office_operator_accounts WHERE id = ? LIMIT 1');
      $q->execute([$operatorId]);
      $active = $q->fetchColumn();
      if ($active === false || (int)$active !== 1) {
        return ['operator_id' => $operatorId, 'active' => false, 'permissions' => []];
      }
      $q = $this->db->prepare(
        "SELECT permission FROM office_module_permissions
         WHERE operator_id = ? AND module = 'finance'"
      );
      $q->execute([$operatorId]);
      $grants = [];
      foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $key) {
        if (!is_string($key)) continue;
        $qualified = 'finance.' . $key;
        if (in_array($qualified, OfficeFinanceBatchPermissionCatalog::permissions(), true)) {
          $grants[] = $qualified;
        }
      }
      return [
        'operator_id' => $operatorId,
        'active' => true,
        'permissions' => array_values(array_unique($grants)),
      ];
    } catch (\PDOException $exception) {
      return ['operator_id' => $operatorId, 'active' => false, 'permissions' => []];
    }
  }

  public function renderForOperator(string $operatorId): string {
    $snapshot = $this->forOperator($operatorId);
    return OfficeFinanceBatchPermissionsView::render($snapshot['permissions']);
  }
}
