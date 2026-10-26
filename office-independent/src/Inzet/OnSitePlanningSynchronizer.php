<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use DateTimeZone;

/**
 * Privileged application service between canonical Project/Inzet records
 * and the Office OnSite projection. Identity and zone mapping are supplied
 * by a trusted Office adapter, never by the device.
 */
final class OnSitePlanningSynchronizer {
  public function __construct(
    private OnSitePlanningProjection $planning,
    private OnSiteProjectionWriter $writer
  ) {}

  /**
   * @return bool True when a confirmed planning row was staged.
   */
  public function stage(array $canonicalAssignment, string $verifiedEmployeeId, string $verifiedZoneId, ?DateTimeZone $timezone = null): bool {
    $window = $this->planning->map($canonicalAssignment, $verifiedEmployeeId, $verifiedZoneId, $timezone);
    if ($window === null) return false;
    $this->writer->assign($window['employee_id'], $window['project_id'], $window['zone_id'], $window['from'], $window['until']);
    return true;
  }
}
