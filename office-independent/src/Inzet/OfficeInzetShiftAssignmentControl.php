<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Pure domain validation for proposed shift assignments. Never writes to DB.
 * Caller must verify project/manage permission and personnel/supplier assignment
 * against trusted Office repositories before persisting.
 */
final class OfficeInzetShiftAssignmentControl {
  /**
   * Reject overlapping shifts for the same employee, across all projects.
   * Existing shifts must be fetched by the trusted Office service, not the browser.
   * @param list<array<string,mixed>> $existing
   */
  public static function assertNoOverlap(array $candidate, array $existing, ?int $excludeShiftId = null): void {
    $person = (int)($candidate['person_id'] ?? 0);
    $start = (string)($candidate['starts_at'] ?? '');
    $end = (string)($candidate['ends_at'] ?? '');
    if ($person < 1 || $start === '' || $end === '' || $end <= $start) {
      throw new InvalidArgumentException('Invalid shift for overlap validation.');
    }
    foreach ($existing as $shift) {
      if ($excludeShiftId !== null && (int)($shift['id'] ?? 0) === $excludeShiftId) continue;
      if ((int)($shift['person_id'] ?? 0) !== $person) continue;
      if (in_array((string)($shift['status'] ?? ''), ['cancelled','canceled'], true)) continue;
      $otherStart = (string)($shift['starts_at'] ?? '');
      $otherEnd = (string)($shift['ends_at'] ?? '');
      if ($otherStart !== '' && $otherEnd !== '' && $start < $otherEnd && $end > $otherStart) {
        throw new InvalidArgumentException('Employee has an overlapping shift.');
      }
    }
  }

  /** @param array<string,mixed> $request
   * @return array{project_id:int,person_id:int,starts_at:string,ends_at:string,status:string}
   */
  public static function validate(array $request, array $managedProjectIds, array $eligiblePersonIds): array {
    $project = filter_var($request['project_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $person = filter_var($request['person_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if (!is_int($project) || !is_int($person)
      || !in_array((int)$project, $managedProjectIds, true)
      || !in_array((int)$person, $eligiblePersonIds, true)) {
      throw new InvalidArgumentException('Project or worker not authorized for assignment.');
    }
    $start = $request['starts_at'] ?? null;
    $end = $request['ends_at'] ?? null;
    if (!is_string($start) || !is_string($end)
      || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $start)
      || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $end)) {
      throw new InvalidArgumentException('Shift dates must use the Office database timestamp format.');
    }
    $tz = new DateTimeZone('UTC');
    $from = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $start, $tz);
    $to = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $end, $tz);
    if ($from === false || $to === false
      || $from->format('Y-m-d H:i:s') !== $start
      || $to->format('Y-m-d H:i:s') !== $end
      || $to <= $from || $to->getTimestamp() - $from->getTimestamp() > 24 * 3600) {
      throw new InvalidArgumentException('Invalid shift interval.');
    }
    return [
      'project_id'=>(int)$project, 'person_id'=>(int)$person,
      'starts_at'=>$start, 'ends_at'=>$end, 'status'=>'planned',
    ];
  }
}
