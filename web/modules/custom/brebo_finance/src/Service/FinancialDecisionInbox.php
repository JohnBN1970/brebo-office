<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;

/** Builds the central financial decision inbox from pending gate exceptions. */
final class FinancialDecisionInbox {

  public function __construct(
    private readonly Connection $database,
    private readonly FinancialGateExposureResolver $exposureResolver,
    private readonly FinancialApprovalMatrix $approvalMatrix,
    private readonly FinancialDecisionAssignmentResolver $assignmentResolver,
  ) {}

  /** @return list<array<string, mixed>> */
  public function pending(?int $projectNid = NULL): array {
    $query = $this->database->select('brebo_finance_phase_gate_exception', 'e')
      ->fields('e')
      ->condition('status', 'requested')
      ->condition('expires_at', time(), '>')
      ->orderBy('created', 'ASC');
    if ($projectNid !== NULL) $query->condition('project_nid', $projectNid);

    $items = [];
    foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      $findingIds = json_decode((string) $row['finding_ids'], TRUE, 512, JSON_THROW_ON_ERROR);
      $findingIds = array_values(array_map('intval', is_array($findingIds) ? $findingIds : []));
      $exposure = $this->exposureResolver->resolve((int) $row['project_nid'], $findingIds);

      if ($exposure['unresolved'] !== []) {
        $level = 'executive_unresolved_exposure';
        $authorization = ['level' => $level, 'exposure_amount' => $exposure['exposure_amount'], 'exposure_complete' => FALSE];
      }
      else {
        $level = $this->levelForExposure($exposure['exposure_amount']);
        $authorization = ['level' => $level, 'exposure_amount' => $exposure['exposure_amount'], 'exposure_complete' => TRUE];
      }

      $assignment = $this->assignmentResolver->resolve((string) $row['gate'], $level, (int) $row['requested_by']);
      $priority = $this->priority((string) $row['gate'], $exposure, $assignment, (int) $row['expires_at']);
      $items[] = [
        'exception_id' => (int) $row['id'], 'project_nid' => (int) $row['project_nid'], 'gate' => (string) $row['gate'],
        'status' => (string) $row['status'], 'reason' => (string) $row['reason'], 'control_measure' => (string) $row['control_measure'],
        'requested_by' => (int) $row['requested_by'], 'created' => (int) $row['created'], 'expires_at' => (int) $row['expires_at'],
        'finding_ids' => $findingIds, 'exposure' => $exposure, 'authorization' => $authorization, 'assignment' => $assignment,
        'attention' => $assignment['escalation_required'] ? 'escalation_required' : 'decision_required',
        'priority' => $priority,
      ];
    }

    usort($items, static fn(array $a, array $b): int => ($b['priority']['score'] <=> $a['priority']['score']) ?: ($a['expires_at'] <=> $b['expires_at']));
    return $items;
  }

  /** @return array<string, mixed> */
  private function priority(string $gate, array $exposure, array $assignment, int $expiresAt): array {
    $now = time();
    $secondsLeft = $expiresAt - $now;
    $amountCents = $this->toCents((string) ($exposure['exposure_amount'] ?? '0'));
    $score = 0;
    $reasons = [];

    if (($exposure['unresolved'] ?? []) !== []) {
      $score += 45;
      $reasons[] = 'Financiële exposure is niet volledig vastgesteld.';
    }
    if (!empty($assignment['escalation_required'])) {
      $score += 40;
      $reasons[] = 'Er is geen bevoegde beoordelaar toegewezen.';
    }
    if ($secondsLeft <= 4 * 3600) {
      $score += 35;
      $reasons[] = 'De beslissing verloopt binnen vier uur.';
    }
    elseif ($secondsLeft <= 24 * 3600) {
      $score += 20;
      $reasons[] = 'De beslissing verloopt binnen 24 uur.';
    }
    elseif ($secondsLeft <= 3 * 86400) {
      $score += 8;
      $reasons[] = 'De beslissing verloopt binnen drie dagen.';
    }

    if ($amountCents > 10000000) {
      $score += 30;
      $reasons[] = 'De financiële exposure is hoger dan EUR 100.000.';
    }
    elseif ($amountCents > 2500000) {
      $score += 18;
      $reasons[] = 'De financiële exposure is hoger dan EUR 25.000.';
    }

    $gateWeight = match ($gate) {
      'payment_release' => 18,
      'execution_start' => 15,
      'billing_release' => 12,
      'procurement_release' => 10,
      'project_closeout' => 6,
      default => 0,
    };
    $score += $gateWeight;
    if ($gateWeight >= 15) $reasons[] = 'Deze fasepoort kan direct geld of uitvoering vrijgeven.';

    $band = $score >= 70 ? 'now' : ($score >= 40 ? 'today' : 'this_week');
    return [
      'score' => $score,
      'band' => $band,
      'label' => match ($band) { 'now' => 'Nu handelen', 'today' => 'Vandaag', default => 'Deze week' },
      'reasons' => $reasons,
      'explanation' => $reasons === [] ? 'Reguliere financiële beslissing binnen de geldende termijn.' : implode(' ', $reasons),
    ];
  }

  private function levelForExposure(string $amount): string {
    $cents = $this->toCents($amount);
    if ($cents <= 2500000) return 'gate_approver';
    if ($cents <= 10000000) return 'finance_controller';
    return 'executive';
  }

  private function toCents(string $amount): int {
    [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
    return ((int) $whole * 100) + (int) substr(str_pad($fraction, 2, '0'), 0, 2);
  }
}
