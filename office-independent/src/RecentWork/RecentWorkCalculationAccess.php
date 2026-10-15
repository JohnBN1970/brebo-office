<?php

declare(strict_types=1);

namespace Brebo\Office\RecentWork;

use Brebo\Office\Calculation\OfficeCalcProjectScopeRepository;
use Brebo\Office\Calculation\PdoOfficeCalculationPermissionRepository;
use PDO;

/**
 * Uses only server-side Office calculation/project mappings and existing grants.
 * Never trusts a project ID from the recent-work row or browser.
 */
final class RecentWorkCalculationAccess {
  public function __construct(private readonly PDO $db) {}

  public function canAccess(string $operatorId, string $resourceType, int $resourceId): bool {
    if ($resourceType !== 'calculation' || $resourceId < 1
      || $operatorId === '' || strlen($operatorId) > 128) {
      return false;
    }
    try {
      if (!(new PdoOfficeCalculationPermissionRepository($this->db))->canCalculate($operatorId)) {
        return false;
      }
      $stmt = $this->db->prepare(
        'SELECT project_id FROM office_calc_calculations
         WHERE calculation_id = :calculation_id LIMIT 2'
      );
      $stmt->execute([':calculation_id' => $resourceId]);
      $projects = $stmt->fetchAll(PDO::FETCH_COLUMN);
      if (count($projects) !== 1) {
        return false;
      }
      $projectId = filter_var($projects[0], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
      if (!is_int($projectId)) {
        return false;
      }
      return (new OfficeCalcProjectScopeRepository($this->db))
        ->canLaunch($operatorId, $projectId, $resourceId);
    } catch (\Throwable $e) {
      return false;
    }
  }
}
