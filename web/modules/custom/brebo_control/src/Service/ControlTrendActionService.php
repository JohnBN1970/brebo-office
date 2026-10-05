<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Service;

use Drupal\brebo_control\Contract\ControlActionRepositoryInterface;
use Drupal\node\NodeInterface;

/**
 * Persists trend deterioration as its own controller action.
 */
final class ControlTrendActionService {

  public function __construct(
    private readonly ControlActionRepositoryInterface $actions,
    private readonly ControlHistoryService $history,
  ) {}

  /**
   * Synchronize a trend deterioration signal for one project.
   *
   * @return array<string, mixed>|null
   */
  public function synchronize(NodeInterface $project, int $now): ?array {
    $trend = $this->history->trend((int) $project->id());
    $existing = $this->actions->byProjectDriver((int) $project->id(), 'trend_deterioration');

    if (($trend['status'] ?? '') !== 'deteriorating') {
      if ($existing && in_array($existing['status'], ['open', 'reopened', 'in_progress', 'escalated'], TRUE)) {
        $this->actions->update((int) $existing['id'], [
          'status' => 'auto_resolved',
          'resolution' => 'Historische projecttrend is niet langer verslechterend.',
          'completed_at' => $now,
          'changed' => $now,
        ]);
      }
      return NULL;
    }

    $points = $this->riskPoints($trend);
    $sourceValue = abs((float) ($trend['result_delta'] ?? 0));
    $urgency = $points >= 20 ? 'kritiek' : ($points >= 10 ? 'vandaag' : 'deze_week');
    $signals = implode(' ', (array) ($trend['signals'] ?? []));
    $values = [
      'title' => 'Projecttrend verslechtert structureel',
      'instruction' => 'Voer een trendreview uit over marge, resultaat, risicoscore en urenprognose. Bepaal welke oorzaken meerdere meetrondes achter elkaar verslechteren en leg per oorzaak een herstelmaatregel, eigenaar en financieel effect vast. ' . $signals,
      'done_when' => 'De verslechterende trend is gestopt of bestuurlijk geaccepteerd met een actuele prognose en aantoonbare herstelmaatregelen.',
      'owner_role' => 'Projectleider / Controller',
      'urgency' => $urgency,
      'risk_points' => $points,
      'source_value' => round($sourceValue, 2),
      'due_at' => $this->dueAt($urgency, $now),
      'changed' => $now,
    ];

    if ($existing) {
      if (in_array($existing['status'], ['completed', 'resolved', 'auto_resolved'], TRUE)) {
        $values['status'] = 'reopened';
        $values['completed_by'] = NULL;
        $values['completed_at'] = NULL;
        $values['resolution'] = NULL;
      }
      $this->actions->update((int) $existing['id'], $values);
      $id = (int) $existing['id'];
    }
    else {
      $id = $this->actions->create((int) $project->id(), 'trend_deterioration', $values + [
        'status' => 'open',
        'escalation_level' => 0,
        'created' => $now,
      ]);
    }

    return $this->actions->byId($id);
  }

  /** @param array<string, mixed> $trend */
  private function riskPoints(array $trend): int {
    $points = 0;
    $points += min(10, max(0, (int) ($trend['risk_delta'] ?? 0)));
    $points += min(10, (int) ceil(abs(min(0.0, (float) ($trend['margin_delta_pct'] ?? 0))) * 3));
    $resultDelta = (float) ($trend['result_delta'] ?? 0);
    if ($resultDelta < -10000) {
      $points += 10;
    }
    elseif ($resultDelta < -1000) {
      $points += 5;
    }
    $hoursDelta = (float) ($trend['forecast_hours_delta'] ?? 0);
    if ($hoursDelta > 40) {
      $points += 8;
    }
    elseif ($hoursDelta > 8) {
      $points += 4;
    }
    return min(30, max(5, $points));
  }

  private function dueAt(string $urgency, int $now): int {
    return match ($urgency) {
      'kritiek' => $now + 4 * 3600,
      'vandaag' => strtotime('today 17:00', $now) ?: $now + 8 * 3600,
      default => $now + 5 * 86400,
    };
  }

}
