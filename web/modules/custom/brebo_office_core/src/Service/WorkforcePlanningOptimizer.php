<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

/**
 * Produces explainable personnel suggestions and capacity forecasts.
 */
final class WorkforcePlanningOptimizer {

  /**
   * Ranks candidates without relaxing safety or availability constraints.
   *
   * @param array<int, array<string, mixed>> $candidates
   * @return array<int, array<string, mixed>>
   */
  public function rank(array $candidates, float $requiredHours): array {
    $ranked = [];
    foreach ($candidates as $candidate) {
      $blocks = [];
      if (empty($candidate['available'])) {
        $blocks[] = 'niet beschikbaar';
      }
      if (($candidate['qualification_status'] ?? 'Blokkade') === 'Blokkade') {
        $blocks[] = 'kwalificatie ontbreekt of is ongeldig';
      }
      if (!empty($candidate['overlap'])) {
        $blocks[] = 'roosterconflict';
      }
      if ((float) ($candidate['remaining_hours'] ?? 0) + 0.0001 < $requiredHours) {
        $blocks[] = 'onvoldoende resterende capaciteit';
      }

      $distance = isset($candidate['distance_km']) ? max(0.0, (float) $candidate['distance_km']) : NULL;
      $quality = min(100.0, max(0.0, (float) ($candidate['quality_score'] ?? 50)));
      $score = 100.0;
      $score += !empty($candidate['continuity']) ? 15.0 : 0.0;
      $score += $quality * 0.2;
      $score += min(40.0, max(0.0, (float) ($candidate['remaining_hours'] ?? 0))) * 0.25;
      $score -= $distance === NULL ? 15.0 : min(100.0, $distance) * 0.5;
      $score -= ($candidate['qualification_status'] ?? '') === 'Waarschuwing' ? 10.0 : 0.0;

      $candidate['eligible'] = $blocks === [];
      $candidate['score'] = $blocks === [] ? round($score, 2) : 0.0;
      $candidate['explanation'] = $blocks === []
        ? $this->explain($candidate, $distance)
        : 'Niet voorstelbaar: ' . implode(', ', $blocks) . '.';
      $ranked[] = $candidate;
    }

    usort($ranked, static function (array $left, array $right): int {
      if ($left['eligible'] !== $right['eligible']) {
        return $left['eligible'] ? -1 : 1;
      }
      $scoreOrder = $right['score'] <=> $left['score'];
      return $scoreOrder !== 0
        ? $scoreOrder
        : strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
    });
    return $ranked;
  }

  /**
   * @param array<int, array{week: string, demand_hours: float|int, staffed_hours: float|int}> $weeks
   * @return array<int, array<string, float|string>>
   */
  public function forecast(array $weeks): array {
    $result = [];
    foreach ($weeks as $week) {
      $demand = max(0.0, (float) $week['demand_hours']);
      $staffed = max(0.0, (float) $week['staffed_hours']);
      $gap = $staffed - $demand;
      $result[] = [
        'week' => $week['week'],
        'demand_hours' => round($demand, 2),
        'staffed_hours' => round($staffed, 2),
        'gap_hours' => round($gap, 2),
        'coverage_percent' => $demand > 0 ? round(($staffed / $demand) * 100, 1) : 100.0,
        'status' => $gap < -0.01 ? 'Tekort' : ($gap > 0.01 ? 'Ruimte' : 'In balans'),
      ];
    }
    return $result;
  }

  private function explain(array $candidate, ?float $distance): string {
    $parts = [
      'beschikbaar',
      'kwalificaties ' . strtolower((string) ($candidate['qualification_status'] ?? 'passend')),
      number_format((float) ($candidate['remaining_hours'] ?? 0), 1, ',', '.') . ' uur capaciteit',
      $distance === NULL ? 'reisafstand onbekend' : number_format($distance, 1, ',', '.') . ' km reisafstand',
    ];
    if (!empty($candidate['continuity'])) {
      $parts[] = 'projectcontinuïteit';
    }
    return ucfirst(implode('; ', $parts)) . '.';
  }

}
