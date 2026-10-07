<?php

declare(strict_types=1);

namespace Drupal\brebo_procurement_control\Service;

use Drupal\brebo_procurement_control\Contract\ProcurementControlRepositoryInterface;

/**
 * Measures whether human procurement overrides add or destroy economic value.
 */
final class ProcurementDecisionIntelligenceService {

  public function __construct(private readonly ProcurementControlRepositoryInterface $repository) {}

  /** @return array<string, mixed> */
  public function analyze(): array {
    $rows = $this->repository->decisionOutcomeRows();
    if ($rows === []) {
      return ['observations' => 0, 'status' => 'no_data'];
    }

    $overrideCount = 0;
    $wins = 0;
    $losses = 0;
    $overrideValue = 0.0;
    $modelAbsoluteError = 0.0;
    $people = [];

    foreach ($rows as $row) {
      $modelAbsoluteError += abs((float) $row['model_error']);
      $isOverride = strcasecmp((string) $row['selected_supplier'], (string) $row['recommended_supplier']) !== 0;
      if (!$isOverride) {
        continue;
      }
      $overrideCount++;
      $delta = (float) $row['economic_delta'];
      $outcome = (string) $row['outcome'];
      if ($outcome === 'human_override_won') {
        $wins++;
        $overrideValue += $delta;
      }
      elseif ($outcome === 'human_override_lost') {
        $losses++;
        $overrideValue -= max($delta, abs((float) $row['model_error']));
      }

      $uid = (int) $row['decided_by'];
      $people[$uid] ??= ['decisions' => 0, 'wins' => 0, 'losses' => 0, 'net_value' => 0.0];
      $people[$uid]['decisions']++;
      if ($outcome === 'human_override_won') {
        $people[$uid]['wins']++;
        $people[$uid]['net_value'] += $delta;
      }
      elseif ($outcome === 'human_override_lost') {
        $people[$uid]['losses']++;
        $people[$uid]['net_value'] -= max($delta, abs((float) $row['model_error']));
      }
    }

    foreach ($people as $uid => &$person) {
      $person['uid'] = $uid;
      $person['win_rate_pct'] = $person['decisions'] > 0 ? round(($person['wins'] / $person['decisions']) * 100, 1) : 0.0;
      $person['net_value'] = round($person['net_value'], 2);
      $person['confidence'] = $this->confidence((int) $person['decisions']);
    }
    unset($person);
    $people = array_values($people);
    usort($people, static fn(array $a, array $b): int => $b['net_value'] <=> $a['net_value']);

    return [
      'observations' => count($rows),
      'override_count' => $overrideCount,
      'override_wins' => $wins,
      'override_losses' => $losses,
      'override_win_rate_pct' => $overrideCount > 0 ? round(($wins / $overrideCount) * 100, 1) : 0.0,
      'human_override_net_value' => round($overrideValue, 2),
      'mean_model_absolute_error' => count($rows) > 0 ? round($modelAbsoluteError / count($rows), 2) : 0.0,
      'decision_makers' => $people,
      'governance_note' => 'Persoonsniveau is coaching- en controldata, geen automatische personeelsbeoordeling. Gebruik alleen bij voldoende waarnemingen en met inhoudelijke review.',
    ];
  }

  private function confidence(int $observations): string {
    return match (TRUE) {
      $observations >= 20 => 'hoog',
      $observations >= 10 => 'middel',
      $observations >= 5 => 'laag',
      default => 'onvoldoende',
    };
  }
}
