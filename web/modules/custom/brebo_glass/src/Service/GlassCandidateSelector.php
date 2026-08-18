<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

/**
 * Selects the lightest verified glass candidate that satisfies all hard demands.
 */
final class GlassCandidateSelector {

  /**
   * @param array<int, array<string, mixed>> $candidates
   *
   * @return array{recommended: ?array, rejected: array<int, array{id: string, reasons: string[]}>}
   */
  public function select(
    array $candidates,
    float $designPressureKpa,
    int $widthMm,
    int $heightMm,
    string $applicationType,
  ): array {
    $suitable = [];
    $rejected = [];

    foreach ($candidates as $candidate) {
      $reasons = [];
      $id = (string) ($candidate['id'] ?? '');

      if (!(bool) ($candidate['verified'] ?? FALSE)) {
        $reasons[] = 'Technische productprestatie is niet geverifieerd.';
      }
      if ((float) ($candidate['wind_resistance_kpa'] ?? 0) < $designPressureKpa) {
        $reasons[] = 'Windweerstand is lager dan de ontwerpwinddruk.';
      }
      if ((int) ($candidate['max_width_mm'] ?? 0) < $widthMm || (int) ($candidate['max_height_mm'] ?? 0) < $heightMm) {
        $reasons[] = 'Bestelmaat valt buiten de geverifieerde productafmetingen.';
      }
      if (!$this->isAllowedForApplication((string) ($candidate['glass_type'] ?? ''), $applicationType)) {
        $reasons[] = 'Glastype voldoet niet aan de harde toepassingseis.';
      }

      if ($reasons !== []) {
        $rejected[] = ['id' => $id, 'reasons' => $reasons];
        continue;
      }

      $candidate['utilization'] = round($designPressureKpa / (float) $candidate['wind_resistance_kpa'], 3);
      $suitable[] = $candidate;
    }

    usort($suitable, static function (array $left, array $right): int {
      $weight = ((float) ($left['weight_kg_m2'] ?? INF)) <=> ((float) ($right['weight_kg_m2'] ?? INF));
      return $weight !== 0 ? $weight : ((float) $left['wind_resistance_kpa'] <=> (float) $right['wind_resistance_kpa']);
    });

    return [
      'recommended' => $suitable[0] ?? NULL,
      'rejected' => $rejected,
    ];
  }

  private function isAllowedForApplication(string $glassType, string $applicationType): bool {
    return match ($applicationType) {
      'fall_protection', 'overhead' => $glassType === 'laminated',
      'fire_separation' => $glassType === 'fire_resistant',
      'door', 'adjacent_door', 'low_level', 'wet_area', 'ceiling' => in_array($glassType, ['laminated', 'tempered'], TRUE),
      default => TRUE,
    };
  }

}
