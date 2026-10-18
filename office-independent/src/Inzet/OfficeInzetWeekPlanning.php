<?php

declare(strict_types=1);

namespace Brebo\Office\Inzet;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Pure planning view; all input shifts must already be authorized by Office ACL. */
final class OfficeInzetWeekPlanning {
  /**
   * @param list<array<string,mixed>> $shifts
   * @param list<int> $authorizedProjectIds
   * @return list<array<string,mixed>>
   */
  public static function rows(array $shifts, array $authorizedProjectIds, int $isoYear, int $isoWeek): array {
    if ($isoYear < 2020 || $isoYear > 2100 || $isoWeek < 1 || $isoWeek > 53) {
      throw new InvalidArgumentException('Invalid ISO week.');
    }
    $utc = new DateTimeZone('UTC');
    $monday = (new DateTimeImmutable('now', $utc))->setISODate($isoYear, $isoWeek, 1)->setTime(0, 0);
    if ((int)$monday->format('o') !== $isoYear || (int)$monday->format('W') !== $isoWeek) {
      throw new InvalidArgumentException('Nonexistent ISO week.');
    }
    $end = $monday->modify('+7 days');
    $allowed = array_fill_keys(array_filter($authorizedProjectIds, static fn($v) => is_int($v) && $v > 0), true);
    $rows = [];
    foreach ($shifts as $shift) {
      $project = (int)($shift['project_id'] ?? 0);
      if (!isset($allowed[$project])) continue;
      $startRaw = $shift['starts_at'] ?? null;
      $endRaw = $shift['ends_at'] ?? null;
      if (!is_string($startRaw) || !is_string($endRaw)) continue;
      try {
        $start = new DateTimeImmutable($startRaw, $utc);
        $finish = new DateTimeImmutable($endRaw, $utc);
      } catch (\Exception $exception) {
        continue;
      }
      if ($finish <= $start || $start >= $end || $finish <= $monday) continue;
      $rows[] = [
        'project_id' => $project,
        'person_id' => isset($shift['person_id']) ? (int)$shift['person_id'] : null,
        'person_name' => isset($shift['person_name']) && is_string($shift['person_name']) ? $shift['person_name'] : '',
        'starts_at' => $start->format('Y-m-d H:i'),
        'ends_at' => $finish->format('Y-m-d H:i'),
        'status' => (string)($shift['status'] ?? 'planned'),
      ];
    }
    usort($rows, static fn(array $a, array $b): int =>
      strcmp($a['starts_at'], $b['starts_at']) ?: ($a['project_id'] <=> $b['project_id']));
    return $rows;
  }
}
