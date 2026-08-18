<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

/**
 * Classifies time-entry deviations before human approval.
 */
final class WorkforceTimeEntryControl {

  /**
   * @param array<string, mixed> $entry
   * @return array{status: string, deviations: string[], blocking: string[], planned_hours: float, actual_hours: float, delta_hours: float}
   */
  public function assess(array $entry): array {
    $plannedStart = $this->timestamp($entry['planned_start'] ?? NULL);
    $plannedEnd = $this->timestamp($entry['planned_end'] ?? NULL);
    $actualStart = $this->timestamp($entry['actual_start'] ?? NULL);
    $actualEnd = $this->timestamp($entry['actual_end'] ?? NULL);
    $breakMinutes = max(0, (int) ($entry['break_minutes'] ?? 0));

    $plannedHours = ($plannedStart !== NULL && $plannedEnd !== NULL && $plannedEnd > $plannedStart)
      ? round(($plannedEnd - $plannedStart) / 3600, 2)
      : 0.0;
    $actualHours = isset($entry['actual_hours'])
      ? max(0.0, (float) $entry['actual_hours'])
      : (($actualStart !== NULL && $actualEnd !== NULL && $actualEnd > $actualStart)
        ? round(max(0, $actualEnd - $actualStart - ($breakMinutes * 60)) / 3600, 2)
        : 0.0);

    $deviations = [];
    $blocking = [];
    $tolerance = max(0, (int) ($entry['tolerance_minutes'] ?? 15)) * 60;

    if ($actualStart === NULL) {
      $blocking[] = 'Werkelijke start ontbreekt.';
    }
    if ($actualEnd === NULL) {
      $blocking[] = 'Werkelijk einde ontbreekt.';
    }

    if ($plannedStart !== NULL && $actualStart !== NULL) {
      $delta = $actualStart - $plannedStart;
      if ($delta > $tolerance) {
        $deviations[] = 'Later gestart dan gepland (' . round($delta / 60) . ' min).';
      }
      elseif ($delta < -$tolerance) {
        $deviations[] = 'Eerder gestart dan gepland (' . round(abs($delta) / 60) . ' min).';
      }
    }

    if ($plannedEnd !== NULL && $actualEnd !== NULL) {
      $delta = $actualEnd - $plannedEnd;
      if ($delta > $tolerance) {
        $deviations[] = 'Later geëindigd dan gepland (' . round($delta / 60) . ' min).';
      }
      elseif ($delta < -$tolerance) {
        $deviations[] = 'Eerder geëindigd dan gepland (' . round(abs($delta) / 60) . ' min).';
      }
    }

    if ($actualHours > $plannedHours + 0.25 && $plannedHours > 0) {
      $deviations[] = 'Werkelijke uren liggen boven de geplande dienstduur.';
    }

    $clockTypes = array_values(array_unique(array_map('strval', $entry['clock_types'] ?? [])));
    if (!in_array('In', $clockTypes, TRUE)) {
      $blocking[] = 'Inklokregistratie ontbreekt.';
    }
    if (!in_array('Uit', $clockTypes, TRUE)) {
      $blocking[] = 'Uitklokregistratie ontbreekt.';
    }
    if (in_array('Pauze start', $clockTypes, TRUE) xor in_array('Pauze einde', $clockTypes, TRUE)) {
      $blocking[] = 'Pauzeregistratie is onvolledig.';
    }

    foreach (($entry['geo_statuses'] ?? []) as $geoStatus) {
      if ($geoStatus !== 'Binnen zone' && $geoStatus !== 'Handmatig goedgekeurd') {
        $deviations[] = 'Locatieafwijking: ' . (string) $geoStatus . '.';
      }
    }

    $budgetHours = max(0.0, (float) ($entry['budget_hours'] ?? 0));
    $approvedHours = max(0.0, (float) ($entry['approved_budget_hours'] ?? 0));
    if ($budgetHours <= 0 && $actualHours > 0) {
      $blocking[] = 'Geen vrijgegeven urenbudget beschikbaar.';
    }
    elseif ($approvedHours + $actualHours > $budgetHours + 0.001) {
      $blocking[] = 'Goedkeuring zou het vrijgegeven urenbudget overschrijden.';
    }
    elseif ($budgetHours > 0 && $approvedHours + $actualHours >= $budgetHours * 0.9) {
      $deviations[] = 'Na goedkeuring is minimaal 90% van het urenbudget verbruikt.';
    }

    $status = $blocking ? 'Blokkade' : ($deviations ? 'Afwijking' : 'Akkoord');

    return [
      'status' => $status,
      'deviations' => array_values(array_unique($deviations)),
      'blocking' => array_values(array_unique($blocking)),
      'planned_hours' => $plannedHours,
      'actual_hours' => $actualHours,
      'delta_hours' => round($actualHours - $plannedHours, 2),
    ];
  }

  private function timestamp(mixed $value): ?int {
    if ($value === NULL || $value === '') {
      return NULL;
    }
    if (is_int($value)) {
      return $value;
    }
    $timestamp = strtotime((string) $value);
    return $timestamp === FALSE ? NULL : $timestamp;
  }

}
