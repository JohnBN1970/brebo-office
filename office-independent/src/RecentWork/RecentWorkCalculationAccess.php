<?php

declare(strict_types=1);

namespace Brebo\Office\RecentWork;

use Brebo\Office\Calculation\OfficeCalcProjectScopeRepository;
use Brebo\Office\Calculation\PdoOfficeCalculationPermissionRepository;
use PDO;

/**
 * Reuses the existing Office calculation permission and project-scope grants.
 * A calculation recent-work event is only safe when a trusted project binding
 * has been resolved separately. Do not infer the project from a URL or client.
 */
final class RecentWorkCalculationAccess {
  public function __construct(private readonly PDO $db) {}

  public function canAccess(string $operatorId, string $resourceType, int $resourceId): bool {
    if ($resourceType !== 'calculation' || $resourceId < 1 || $operatorId === '') {
      return false;
    }
    try {
      if (!(new PdoOfficeCalculationPermissionRepository($this->db))->canCalculate($operatorId)) {
        return false;
      }
      // A trusted calculation -> project mapping and canLaunch() must both be
      // verified before enabling this resolver for dashboard navigation.
      return false;
    } catch (\Throwable $e) {
      return false;
    }
  }
}
