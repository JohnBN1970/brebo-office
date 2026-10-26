<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

/**
 * Authorizes evidence against the exact Office project/zone assignments.
 * Caller obtains assignments for the employee and event date from Office.
 */
final class OnSiteZoneAuthorization {
  public static function allows(array $assignments, string $projectId, string $zoneId): bool {
    foreach ($assignments as $project) {
      if (!is_array($project) || (string)($project['id'] ?? '') !== $projectId) {
        continue;
      }
      foreach (($project['zones'] ?? []) as $zone) {
        if (is_array($zone) && (string)($zone['id'] ?? '') === $zoneId
          && ($zone['active'] ?? false) === true) {
          return true;
        }
      }
    }
    return false;
  }
}
