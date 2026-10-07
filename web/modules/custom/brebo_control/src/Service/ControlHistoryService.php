<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Service;

use Drupal\brebo_control\Contract\ControlActionRepositoryInterface;
use Drupal\brebo_control\Contract\ControlHistoryRepositoryInterface;
use Drupal\brebo_control\Contract\ControlProjectAnalysisSourceInterface;

/**
 * Captures project control history and detects persistent deterioration.
 */
final class ControlHistoryService {

  public function __construct(
    private readonly ControlHistoryRepositoryInterface $historyRepository,
    private readonly ControlProjectAnalysisSourceInterface $projectAnalysis,
    private readonly ControlActionRepositoryInterface $actions,
  ) {}

  public function capture(int $projectId, int $now): bool {
    $snapshot = $this->projectAnalysis->historySnapshot($projectId);
    if ($snapshot === NULL) {
      return FALSE;
    }
    $last = $this->historyRepository->latestCapturedAt($projectId);
    if ($last !== NULL && $now - $last < 6 * 3600) {
      return FALSE;
    }

    $warning = (array) $snapshot['warning'];
    $financial = (array) ($warning['financial_snapshot'] ?? []);
    $finance = (array) $snapshot['finance'];
    $openActions = $this->actions->countOpenForProject($projectId);

    $this->historyRepository->append($projectId, $now, [
      'risk_score' => (int) $warning['score'],
      'forecast_cost' => (float) $financial['forecast_cost'],
      'forecast_revenue' => (float) $financial['forecast_revenue'],
      'expected_result' => (float) $financial['expected_result'],
      'expected_margin_pct' => (float) $financial['expected_margin_pct'],
      'margin_delta_pct' => (float) $financial['margin_delta_pct'],
      'actual_hours' => (float) $finance['actual_hours'],
      'forecast_hours' => (float) $finance['forecast_hours'],
      'blocked_invoices' => (int) $finance['blocked_invoices'],
      'open_actions' => $openActions,
    ]);
    return TRUE;
  }

  /** @return array<string, mixed> */
  public function trend(int $projectId, int $limit = 12): array {
    $rows = $this->historyRepository->snapshots($projectId, $limit);
    if (count($rows) < 3) {
      return ['status' => 'insufficient_data', 'signals' => [], 'snapshots' => $rows];
    }

    $first = $rows[0];
    $last = $rows[array_key_last($rows)];
    $signals = [];
    $riskDelta = (int) $last['risk_score'] - (int) $first['risk_score'];
    $marginDelta = (float) $last['expected_margin_pct'] - (float) $first['expected_margin_pct'];
    $resultDelta = (float) $last['expected_result'] - (float) $first['expected_result'];
    $hoursDelta = (float) $last['forecast_hours'] - (float) $first['forecast_hours'];

    if ($riskDelta >= 10) {
      $signals[] = 'Risicoscore verslechtert structureel: +' . $riskDelta . ' punten over de meetperiode.';
    }
    if ($marginDelta <= -1.0) {
      $signals[] = 'Verwachte marge daalt structureel: ' . number_format(abs($marginDelta), 2, ',', '.') . ' procentpunt verlies over de meetperiode.';
    }
    if ($resultDelta < -1000) {
      $signals[] = 'Verwacht projectresultaat verslechterde met € ' . number_format(abs($resultDelta), 2, ',', '.') . '.';
    }
    if ($hoursDelta > 8) {
      $signals[] = 'Urenprognose loopt op: +' . number_format($hoursDelta, 1, ',', '.') . ' uur over de meetperiode.';
    }

    $consecutiveMarginDown = $this->consecutiveDirection($rows, 'expected_margin_pct', -1);
    $consecutiveRiskUp = $this->consecutiveDirection($rows, 'risk_score', 1);
    if ($consecutiveMarginDown >= 3) {
      $signals[] = 'Marge is in ' . $consecutiveMarginDown . ' opeenvolgende meetrondes gedaald.';
    }
    if ($consecutiveRiskUp >= 3) {
      $signals[] = 'Risicoscore is in ' . $consecutiveRiskUp . ' opeenvolgende meetrondes gestegen.';
    }

    return [
      'status' => $signals ? 'deteriorating' : 'stable',
      'risk_delta' => $riskDelta,
      'margin_delta_pct' => round($marginDelta, 2),
      'result_delta' => round($resultDelta, 2),
      'forecast_hours_delta' => round($hoursDelta, 2),
      'signals' => $signals,
      'snapshots' => $rows,
    ];
  }

  /** @param array<int, array<string, mixed>> $rows */
  private function consecutiveDirection(array $rows, string $field, int $direction): int {
    $count = 0;
    for ($i = count($rows) - 1; $i > 0; $i--) {
      $delta = (float) $rows[$i][$field] - (float) $rows[$i - 1][$field];
      if (($direction > 0 && $delta > 0) || ($direction < 0 && $delta < 0)) {
        $count++;
      }
      else {
        break;
      }
    }
    return $count + ($count > 0 ? 1 : 0);
  }

}
