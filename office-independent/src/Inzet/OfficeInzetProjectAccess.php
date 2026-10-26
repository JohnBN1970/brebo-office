<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use Brebo\Office\Project\OfficeNativeProjectAccess;
use PDO;

/**
 * Single project authorization boundary for independent Inzet.
 * No Drupal node permissions and no Calc project grants.
 */
final class OfficeInzetProjectAccess {
  private readonly OfficeNativeProjectAccess $projects;

  public function __construct(PDO $db) {
    $this->projects = new OfficeNativeProjectAccess($db);
  }

  public function canView(string $operatorId, int $projectId): bool {
    return $this->projects->canRead($operatorId, $projectId);
  }

  public function canPlan(string $operatorId, int $projectId): bool {
    return $this->projects->canManage($operatorId, $projectId);
  }
}
