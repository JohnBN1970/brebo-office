<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

/**
 * Calculates dependency-driven project dates without persisting changes.
 */
final class ProjectScheduleCalculator {

  /**
   * Calculates proposed dates for normalized planning activities.
   *
   * Required keys per activity: id, label, start, end, duration,
   * predecessors, relation and lag.
   *
   * @return array{activities: array<int|string, array<string, mixed>>, errors: string[]}
   */
  public function calculate(array $activities): array {
    $errors = [];
    $order = $this->topologicalOrder($activities, $errors);
    if ($errors) {
      return ['activities' => $activities, 'errors' => $errors];
    }

    $result = $activities;
    foreach ($order as $id) {
      $activity = $result[$id];
      $predecessors = array_values(array_filter(
        (array) ($activity['predecessors'] ?? []),
        static fn ($predecessor): bool => isset($result[$predecessor])
      ));
      if (!$predecessors) {
        $result[$id]['proposed_start'] = $activity['start'];
        $result[$id]['proposed_end'] = $activity['end'];
        $result[$id]['changed'] = FALSE;
        $result[$id]['reason'] = 'Geen voorganger';
        continue;
      }

      $relation = strtoupper((string) ($activity['relation'] ?? 'FS'));
      if (!in_array($relation, ['FS', 'SS', 'FF', 'SF'], TRUE)) {
        $errors[] = sprintf('%s heeft een onbekende relatie %s.', $activity['label'] ?? $id, $relation);
        continue;
      }
      $duration = max(1, (int) ($activity['duration'] ?? 1));
      $lag = (int) ($activity['lag'] ?? 0);
      $candidate_starts = [];
      $candidate_ends = [];

      foreach ($predecessors as $predecessor_id) {
        $predecessor = $result[$predecessor_id];
        $predecessor_start = new \DateTimeImmutable((string) ($predecessor['proposed_start'] ?? $predecessor['start']));
        $predecessor_end = new \DateTimeImmutable((string) ($predecessor['proposed_end'] ?? $predecessor['end']));

        if ($relation === 'FS') {
          $candidate_starts[] = $this->addWorkingDays($predecessor_end, $lag + 1);
        }
        elseif ($relation === 'SS') {
          $candidate_starts[] = $this->addWorkingDays($predecessor_start, $lag);
        }
        elseif ($relation === 'FF') {
          $candidate_ends[] = $this->addWorkingDays($predecessor_end, $lag);
        }
        else {
          $candidate_ends[] = $this->addWorkingDays($predecessor_start, $lag);
        }
      }

      if ($candidate_starts) {
        $start = $this->latest($candidate_starts);
        $end = $this->addWorkingDays($start, $duration - 1);
      }
      else {
        $end = $this->latest($candidate_ends);
        $start = $this->addWorkingDays($end, -($duration - 1));
      }

      $proposed_start = $start->format('Y-m-d');
      $proposed_end = $end->format('Y-m-d');
      $result[$id]['proposed_start'] = $proposed_start;
      $result[$id]['proposed_end'] = $proposed_end;
      $result[$id]['changed'] = $proposed_start !== (string) $activity['start']
        || $proposed_end !== (string) $activity['end'];
      $result[$id]['reason'] = sprintf(
        '%s met %d werkdag(en) wachttijd op %d voorganger(s)',
        $relation,
        $lag,
        count($predecessors)
      );
    }

    if ($result) {
      $project_finish = $this->latest(array_map(
        static fn (array $activity): \DateTimeImmutable => new \DateTimeImmutable((string) $activity['proposed_end']),
        $result
      ));
      $successors = array_fill_keys(array_keys($result), []);
      foreach ($result as $successor_id => $successor) {
        foreach ((array) ($successor['predecessors'] ?? []) as $predecessor_id) {
          if (isset($successors[$predecessor_id])) {
            $successors[$predecessor_id][] = $successor_id;
          }
        }
      }

      foreach (array_reverse($order) as $id) {
        $duration = max(1, (int) ($result[$id]['duration'] ?? 1));
        $candidate_ends = [];
        foreach ($successors[$id] as $successor_id) {
          $successor = $result[$successor_id];
          $relation = strtoupper((string) ($successor['relation'] ?? 'FS'));
          $lag = (int) ($successor['lag'] ?? 0);
          $successor_latest_start = new \DateTimeImmutable((string) $successor['latest_start']);
          $successor_latest_end = new \DateTimeImmutable((string) $successor['latest_end']);

          if ($relation === 'FS') {
            $candidate_ends[] = $this->addWorkingDays($successor_latest_start, -($lag + 1));
          }
          elseif ($relation === 'SS') {
            $predecessor_start = $this->addWorkingDays($successor_latest_start, -$lag);
            $candidate_ends[] = $this->addWorkingDays($predecessor_start, $duration - 1);
          }
          elseif ($relation === 'FF') {
            $candidate_ends[] = $this->addWorkingDays($successor_latest_end, -$lag);
          }
          else {
            $predecessor_start = $this->addWorkingDays($successor_latest_end, -$lag);
            $candidate_ends[] = $this->addWorkingDays($predecessor_start, $duration - 1);
          }
        }

        $latest_end = $candidate_ends ? $this->earliest($candidate_ends) : $project_finish;
        $latest_start = $this->addWorkingDays($latest_end, -($duration - 1));
        $proposed_start = new \DateTimeImmutable((string) $result[$id]['proposed_start']);
        $float = $this->workingDayDistance($proposed_start, $latest_start);
        $result[$id]['latest_start'] = $latest_start->format('Y-m-d');
        $result[$id]['latest_end'] = $latest_end->format('Y-m-d');
        $result[$id]['total_float'] = $float;
        $result[$id]['calculated_critical'] = $float <= 0;
        $result[$id]['critical_changed'] = (bool) ($result[$id]['current_critical'] ?? FALSE) !== ($float <= 0);
      }
    }

    return ['activities' => $result, 'errors' => $errors];
  }

  /**
   * Returns a dependency-safe activity order and reports cycles.
   */
  private function topologicalOrder(array $activities, array &$errors): array {
    $order = [];
    $state = [];
    $visit = function ($id, array $path = []) use (&$visit, &$order, &$state, &$errors, $activities): void {
      if (($state[$id] ?? 0) === 2) {
        return;
      }
      if (($state[$id] ?? 0) === 1) {
        $cycle = array_merge($path, [$id]);
        $errors[] = 'Cyclische planningsrelatie: ' . implode(' → ', $cycle) . '.';
        return;
      }
      if (!isset($activities[$id])) {
        return;
      }
      $state[$id] = 1;
      foreach ((array) ($activities[$id]['predecessors'] ?? []) as $predecessor) {
        if (isset($activities[$predecessor])) {
          $visit($predecessor, array_merge($path, [$id]));
        }
      }
      $state[$id] = 2;
      $order[] = $id;
    };

    foreach (array_keys($activities) as $id) {
      $visit($id);
    }
    return array_values(array_unique($order, SORT_REGULAR));
  }

  /**
   * Adds signed working days, skipping Saturday and Sunday.
   */
  private function addWorkingDays(\DateTimeImmutable $date, int $days): \DateTimeImmutable {
    if ($days === 0) {
      return $this->moveToWorkingDay($date, 1);
    }
    $direction = $days > 0 ? 1 : -1;
    $remaining = abs($days);
    $cursor = $date;
    while ($remaining > 0) {
      $cursor = $cursor->modify(($direction > 0 ? '+' : '-') . '1 day');
      if ((int) $cursor->format('N') <= 5) {
        $remaining--;
      }
    }
    return $this->moveToWorkingDay($cursor, $direction);
  }

  private function moveToWorkingDay(\DateTimeImmutable $date, int $direction): \DateTimeImmutable {
    $cursor = $date;
    while ((int) $cursor->format('N') > 5) {
      $cursor = $cursor->modify(($direction >= 0 ? '+' : '-') . '1 day');
    }
    return $cursor;
  }

  /**
   * Returns the latest date in a non-empty list.
   */
  private function latest(array $dates): \DateTimeImmutable {
    usort($dates, static fn (\DateTimeImmutable $a, \DateTimeImmutable $b): int => $a <=> $b);
    return $dates[count($dates) - 1];
  }

  /**
   * Returns the earliest date in a non-empty list.
   */
  private function earliest(array $dates): \DateTimeImmutable {
    usort($dates, static fn (\DateTimeImmutable $a, \DateTimeImmutable $b): int => $a <=> $b);
    return $dates[0];
  }

  /**
   * Counts signed working-day steps between two dates.
   */
  private function workingDayDistance(\DateTimeImmutable $from, \DateTimeImmutable $to): int {
    if ($from == $to) {
      return 0;
    }
    $direction = $from < $to ? 1 : -1;
    $cursor = $from;
    $days = 0;
    while (($direction > 0 && $cursor < $to) || ($direction < 0 && $cursor > $to)) {
      $cursor = $cursor->modify(($direction > 0 ? '+' : '-') . '1 day');
      if ((int) $cursor->format('N') <= 5) {
        $days += $direction;
      }
    }
    return $days;
  }

}
