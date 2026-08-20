<?php

declare(strict_types=1);

namespace Drupal\brebo_inzet\Service;

/**
 * Detects deterministic roster conflicts for BREBO Inzet.
 */
final class WorkforceScheduleControl {

  /**
   * @return array{shifts: array<int|string, array<string, mixed>>, conflicts: int, open: int}
   */
  public function analyze(array $shifts, array $availability): array {
    $result = $shifts;
    foreach ($result as &$shift) {
      $shift['conflicts'] = [];
    }
    unset($shift);

    $ids = array_keys($result);
    foreach ($ids as $position => $id) {
      $shift = $result[$id];
      $resource = (string) ($shift['resource'] ?? '');
      if ($resource === '' && !($shift['open'] ?? FALSE)) {
        $result[$id]['conflicts'][] = 'Geen persoon of ploeg toegewezen';
      }

      foreach ($availability as $period) {
        if ($resource === '' || (string) ($period['resource'] ?? '') !== $resource
          || !in_array((string) ($period['type'] ?? ''), ['Verlof', 'Niet beschikbaar'], TRUE)) {
          continue;
        }
        if ($this->overlaps(
          (string) $shift['start'],
          (string) $shift['end'],
          (string) $period['start'] . 'T00:00:00',
          (string) $period['end'] . 'T23:59:59',
        )) {
          $result[$id]['conflicts'][] = 'Overlap met niet-beschikbaarheid';
        }
      }

      for ($otherPosition = $position + 1; $otherPosition < count($ids); $otherPosition++) {
        $otherId = $ids[$otherPosition];
        $other = $result[$otherId];
        if ($resource !== '' && $resource === (string) ($other['resource'] ?? '')
          && $this->overlaps((string) $shift['start'], (string) $shift['end'], (string) $other['start'], (string) $other['end'])) {
          $result[$id]['conflicts'][] = 'Overlappende dienst';
          $result[$otherId]['conflicts'][] = 'Overlappende dienst';
        }
      }
      $result[$id]['conflicts'] = array_values(array_unique($result[$id]['conflicts']));
    }

    return [
      'shifts' => $result,
      'conflicts' => count(array_filter($result, static fn (array $shift): bool => !empty($shift['conflicts']))),
      'open' => count(array_filter($result, static fn (array $shift): bool => (bool) ($shift['open'] ?? FALSE))),
    ];
  }

  private function overlaps(string $startA, string $endA, string $startB, string $endB): bool {
    return new \DateTimeImmutable($startA) < new \DateTimeImmutable($endB)
      && new \DateTimeImmutable($startB) < new \DateTimeImmutable($endA);
  }

}
