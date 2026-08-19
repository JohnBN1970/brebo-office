<?php

declare(strict_types=1);

namespace Drupal\brebo_control\Service;

use Drupal\brebo_office_core\Service\ProjectEarlyWarningService;
use Drupal\Core\Database\Connection;
use Drupal\node\NodeInterface;

/**
 * Captures project control history and detects persistent deterioration.
 */
final class ControlHistoryService {

  public function __construct(
    private readonly Connection $database,
    private readonly ProjectEarlyWarningService $earlyWarning,
  ) {}

  public function capture(NodeInterface $project, int $now): bool {
    if (!$this->database->schema()->tableExists('brebo_control_snapshot')) {
      return FALSE;
    }
    $last = $this->database->select('brebo_control_snapshot', 's')
      ->fields('s', ['captured_at'])->condition('project_nid', (int) $project->id())
      ->orderBy('captured_at', 'DESC')->range(0, 1)->execute()->fetchField();
    if ($last && $now - (int) $last < 6 * 3600) {
      return FALSE;
    }

    $warning = $this->earlyWarning->analyze($project);
    $financial = $warning['financial_snapshot'];
    $openActions = (int) $this->database->select('brebo_control_action', 'a')
      ->condition('project_nid', (int) $project->id())
      ->condition('status', ['open', 'reopened', 'in_progress', 'escalated'], 'IN')
      ->countQuery()->execute()->fetchField();

    $finance = \Drupal::service('brebo_office_core.project_financial_control')->analyze($project);
    $this->database->insert('brebo_control_snapshot')->fields([
      'project_nid' => (int) $project->id(),
      'captured_at' => $now,
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
    ])->execute();
    return TRUE;
  }

  /** @return array<string, mixed> */
  public function trend(int $projectId, int $limit = 12): array {
    if (!$this->database->schema()->tableExists('brebo_control_snapshot')) {
      return ['status' => 'insufficient_data', 'signals' => [], 'snapshots' => []];
    }
    $rows = $this->database->select('brebo_control_snapshot', 's')->fields('s')
      ->condition('project_nid', $projectId)->orderBy('captured_at', 'DESC')
      ->range(0, max(2, $limit))->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $rows = array_reverse($rows);
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
