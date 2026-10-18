<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Maps a verified canonical PersonnelAssignmentRepository record to an
 * OnSite planning window. Geometry and employee identity come from separately
 * verified Office mappings, never from the mobile client.
 */
final class OnSitePlanningProjection {
  /** @return array{employee_id:string,project_id:string,zone_id:string,from:DateTimeImmutable,until:DateTimeImmutable}|null */
  public function map(array $assignment, string $employeeId, string $zoneId, ?DateTimeZone $timezone = null): ?array {
    if ($employeeId === '' || $zoneId === '' || (int)($assignment['project_id'] ?? 0) <= 0
      || (int)($assignment['user_id'] ?? 0) <= 0) {
      throw new InvalidArgumentException('Missing verified planning identifiers');
    }
    if (($assignment['assignment_status'] ?? '') !== 'confirmed') return null;
    $day = (string)($assignment['plan_date'] ?? '');
    $start = (string)($assignment['start'] ?? '');
    $end = (string)($assignment['end'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $day)
      || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $start)
      || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $end)
      || $end <= $start) return null;
    $tz = $timezone ?? new DateTimeZone('Europe/Brussels');
    // A daylight-saving transition may make a wall-clock time ambiguous.
    // Reject transitions instead of silently granting an unintended window.
    $midnight = new DateTimeImmutable($day . ' 00:00:00', $tz);
    $nextMidnight = $midnight->modify('+1 day');
    if ($tz->getOffset($midnight) !== $tz->getOffset($nextMidnight)) return null;
    $from = DateTimeImmutable::createFromFormat('!Y-m-d H:i', "$day $start", $tz);
    $until = DateTimeImmutable::createFromFormat('!Y-m-d H:i', "$day $end", $tz);
    if (!$from || !$until || $from->format('Y-m-d H:i') !== "$day $start"
      || $until->format('Y-m-d H:i') !== "$day $end" || $until <= $from) return null;
    return ['employee_id'=>$employeeId,'project_id'=>(string)$assignment['project_id'],
      'zone_id'=>$zoneId,'from'=>$from,'until'=>$until];
  }
}
