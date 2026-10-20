<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Project-zone authorization contract for automatic presence events.
 * Implementations must resolve the employee from a verified device token,
 * not from client JSON. Deny access if identity or assignment is ambiguous.
 */
interface OnSiteAssignmentRepository {
  public function isAssigned(string $verifiedEmployeeId, string $projectId, string $zoneId): bool;
}
