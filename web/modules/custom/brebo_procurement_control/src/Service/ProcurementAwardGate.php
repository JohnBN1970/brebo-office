<?php

declare(strict_types=1);

namespace Drupal\brebo_procurement_control\Service;

use Drupal\Core\Database\Connection;

/** Prevents award until the complete procurement control chain is satisfied. */
final class ProcurementAwardGate {

  public function __construct(
    private readonly Connection $database,
    private readonly ProcurementControlModeGate $controlModeGate,
  ) {}

  /** @param array<string, mixed> $context
   *  @return array<string, mixed>
   */
  public function award(int $decisionId, array $context, string $requestedMode, int $awardedBy, ?int $reviewedBy = NULL, ?int $now = NULL): array {
    $now ??= time();
    $decision = $this->database->select('brebo_procurement_decision', 'd')->fields('d')->condition('id', $decisionId)->execute()->fetchAssoc();
    if (!$decision) {
      throw new \InvalidArgumentException('Onbekende inkoopbeslissing.');
    }

    if (!in_array((string) $decision['decision_status'], ['approved_economic_best', 'approved_deviation'], TRUE)) {
      return ['awarded' => FALSE, 'status' => 'blocked_decision_not_approved', 'message' => 'Opdracht geblokkeerd: leverancierskeuze is nog niet formeel goedgekeurd.'];
    }

    $mode = $this->controlModeGate->assess($context, $requestedMode);
    if (!$mode['allowed']) {
      return ['awarded' => FALSE, 'status' => 'blocked_control_mode', 'control_mode' => $mode, 'message' => $mode['message']];
    }

    if (in_array($mode['required_mode'], ['hybrid_review', 'senior_human_review'], TRUE) && (!$reviewedBy || $reviewedBy <= 0)) {
      return ['awarded' => FALSE, 'status' => 'blocked_missing_review', 'control_mode' => $mode, 'message' => 'Opdracht geblokkeerd: vereiste menselijke review ontbreekt.'];
    }

    if ($mode['required_mode'] === 'senior_human_review' && $reviewedBy === $awardedBy) {
      return ['awarded' => FALSE, 'status' => 'blocked_four_eyes', 'control_mode' => $mode, 'message' => 'Opdracht geblokkeerd: senior review vereist een tweede persoon.'];
    }

    $existing = $this->database->select('brebo_procurement_award', 'a')->fields('a')->condition('decision_id', $decisionId)->execute()->fetchAssoc();
    if ($existing) {
      return ['awarded' => TRUE, 'status' => 'already_awarded', 'award_id' => (int) $existing['id'], 'message' => 'Deze inkoopbeslissing is al definitief verstrekt.'];
    }

    $id = (int) $this->database->insert('brebo_procurement_award')->fields([
      'decision_id' => $decisionId,
      'project_nid' => (int) $decision['project_nid'],
      'procurement_ref' => (string) $decision['procurement_ref'],
      'supplier_name' => (string) $decision['selected_supplier'],
      'requested_mode' => $requestedMode,
      'required_mode' => (string) $mode['required_mode'],
      'reviewed_by' => $reviewedBy,
      'awarded_by' => $awardedBy,
      'awarded_at' => $now,
      'status' => 'awarded',
    ])->execute();

    return [
      'awarded' => TRUE,
      'status' => 'awarded',
      'award_id' => $id,
      'supplier_name' => (string) $decision['selected_supplier'],
      'control_mode' => $mode,
      'message' => 'Alle inkoopcontroles zijn aantoonbaar doorlopen. Opdrachtverstrekking toegestaan.',
    ];
  }
}
