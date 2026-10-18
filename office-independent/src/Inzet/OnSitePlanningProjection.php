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
    $from = DateTimeImmutable::createFromFormat('!Y-m-d H:i', "$day $start", $tz);
    $until = DateTimeImmutable::createFromFormat('!Y-m-d H:i', "$day $end", $tz);
    if (!$from || !$until || $from->format('Y-m-d H:i') !== "$day $start"
      || $until->format('Y-m-d H:i') !== "$day $end" || $until <= $from) return null;
    // Reject ambiguous or nonexistent clock times only when the planned
    // interval intersects the changed local-clock hour.
    $transitions = $tz->getTransitions($from->getTimestamp() - 86400, $until->getTimestamp() + 86400);
    if (is_array($transitions)) {
      foreach (array_slice($transitions, 1) as $transition) {
        $timestamp = (int) $transition['ts'];
        $previousOffset = $tz->getOffset(new DateTimeImmutable('@' . ($timestamp - 1)));
        $currentOffset = (int) $transition['offset'];
        if ($previousOffset === $currentOffset) continue;
        $low = min($timestamp + $previousOffset, $timestamp + $currentOffset);
        $high = max($timestamp + $previousOffset, $timestamp + $currentOffset);
        $startWall = strtotime($day . ' ' . $start . ' UTC');
        $endWall = strtotime($day . ' ' . $end . ' UTC');
        if ($startWall !== false && $endWall !== false && $startWall < $high && $endWall > $low) return null;
      }
    }
    return ['employee_id'=>$employeeId,'project_id'=>(string)$assignment['project_id'],
      'zone_id'=>$zoneId,'from'=>$from,'until'=>$until];
  }
}
