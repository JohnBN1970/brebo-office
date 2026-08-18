<?php

declare(strict_types=1);

namespace Drupal\brebo_glass\Service;

/**
 * Defines the single source of truth for technical glass approval.
 */
final class GlassApprovalPolicy {

  /**
   * @param array<string, mixed> $position
   *
   * @return array{allowed: bool, issues: string[]}
   */
  public function evaluate(array $position): array {
    $issues = [];

    if ((string) ($position['technical_status'] ?? '') !== 'measured') {
      $issues[] = 'De glaspositie is niet als ingemeten geregistreerd.';
    }
    if ((string) ($position['technical_check_state'] ?? '') !== 'passed') {
      $issues[] = 'De technische voorcontrole is niet akkoord.';
    }
    if ((int) ($position['measurement_verified'] ?? 0) !== 1) {
      $issues[] = 'De maatvoering is niet geverifieerd.';
    }
    if ((int) ($position['wind_verified'] ?? 0) !== 1) {
      $issues[] = 'De windbelastingberekening is niet geverifieerd.';
    }
    if ((float) ($position['wind_utilization'] ?? INF) > 1.0) {
      $issues[] = 'De windbenutting is groter dan 100%.';
    }
    if (trim((string) ($position['recommended_glass_ref'] ?? '')) === '') {
      $issues[] = 'Een geverifieerd passend glasproduct ontbreekt.';
    }

    return ['allowed' => $issues === [], 'issues' => $issues];
  }

}
