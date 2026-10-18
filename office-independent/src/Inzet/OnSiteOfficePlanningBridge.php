<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Office-native planning boundary. The canonical Office planning service
 * supplies verified string identifiers and absolute timestamps; Drupal ids
 * and legacy planning rows are not part of this contract.
 */
final class OnSiteOfficePlanningBridge {
  public function __construct(private OnSiteProjectionWriter $projection) {}

  public function stageConfirmed(
    string $employeeId,
    string $projectId,
    string $zoneId,
    string $status,
    DateTimeImmutable $startsAt,
    DateTimeImmutable $endsAt
  ): bool {
    if ($status !== 'confirmed') return false;
    if (trim($employeeId) === '' || trim($projectId) === '' || trim($zoneId) === '' || $endsAt <= $startsAt) {
      throw new InvalidArgumentException('Invalid verified Office planning assignment');
    }
    $this->projection->assign($employeeId,$projectId,$zoneId,$startsAt,$endsAt);
    return true;
  }
}
