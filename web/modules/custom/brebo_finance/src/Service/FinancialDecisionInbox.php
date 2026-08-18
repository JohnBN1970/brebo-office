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
        $authorization = [
          'level' => $level,
          'exposure_amount' => $exposure['exposure_amount'],
          'exposure_complete' => FALSE,
        ];
      }
      else {
        $level = $this->levelForExposure($exposure['exposure_amount']);
        $authorization = [
          'level' => $level,
          'exposure_amount' => $exposure['exposure_amount'],
          'exposure_complete' => TRUE,
        ];
      }

      $assignment = $this->assignmentResolver->resolve((string) $row['gate'], $level, (int) $row['requested_by']);
      $items[] = [
        'exception_id' => (int) $row['id'],
        'project_nid' => (int) $row['project_nid'],
        'gate' => (string) $row['gate'],
        'status' => (string) $row['status'],
        'reason' => (string) $row['reason'],
        'control_measure' => (string) $row['control_measure'],
        'requested_by' => (int) $row['requested_by'],
        'created' => (int) $row['created'],
        'expires_at' => (int) $row['expires_at'],
        'finding_ids' => $findingIds,
        'exposure' => $exposure,
        'authorization' => $authorization,
        'assignment' => $assignment,
        'attention' => $assignment['escalation_required'] ? 'escalation_required' : 'decision_required',
      ];
    }
    return $items;
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
