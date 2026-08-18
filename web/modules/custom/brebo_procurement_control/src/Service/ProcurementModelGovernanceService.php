<?php

declare(strict_types=1);

namespace Drupal\brebo_procurement_control\Service;

use Drupal\Core\Database\Connection;

/**
 * Creates auditable parameter proposals; never changes live model silently.
 */
final class ProcurementModelGovernanceService {

  public function __construct(
    private readonly Connection $database,
    private readonly ProcurementOutcomeLearningService $learning,
  ) {}

  /** @return array<string, mixed> */
  public function propose(string $fromVersion, string $toVersion, array $changes, string $reason, int $proposedBy, ?int $now = NULL): array {
    $now ??= time();
    $diagnostics = $this->learning->diagnostics();
    if (($diagnostics['learning_status'] ?? '') !== 'voldoende_voor_parameterreview') {
      return [
        'created' => FALSE,
        'status' => 'blocked_insufficient_evidence',
        'diagnostics' => $diagnostics,
        'message' => 'Parameterwijziging geblokkeerd: eerst voldoende afgesloten inkoopuitkomsten verzamelen.',
      ];
    }
    if ($fromVersion === $toVersion || $changes === [] || trim($reason) === '') {
      throw new \InvalidArgumentException('Versies, wijzigingen en reden zijn verplicht.');
    }

    $id = (int) $this->database->insert('brebo_procurement_model_review')->fields([
      'from_version' => $fromVersion,
      'to_version' => $toVersion,
      'changes_json' => json_encode($changes, JSON_THROW_ON_ERROR),
      'reason' => $reason,
      'evidence_json' => json_encode($diagnostics, JSON_THROW_ON_ERROR),
      'status' => 'proposed',
      'proposed_by' => $proposedBy,
      'proposed_at' => $now,
    ])->execute();

    return ['created' => TRUE, 'review_id' => $id, 'status' => 'proposed', 'diagnostics' => $diagnostics];
  }

  /** @return array<string, mixed> */
  public function approve(int $reviewId, int $approvedBy, string $approvalNote, ?int $now = NULL): array {
    $now ??= time();
    $review = $this->database->select('brebo_procurement_model_review', 'r')->fields('r')
      ->condition('id', $reviewId)->execute()->fetchAssoc();
    if (!$review) {
      throw new \InvalidArgumentException('Onbekende modelreview.');
    }
    if ((string) $review['status'] !== 'proposed') {
      throw new \LogicException('Alleen voorgestelde modelreviews kunnen worden goedgekeurd.');
    }
    if ((int) $review['proposed_by'] === $approvedBy) {
      throw new \LogicException('Vier-ogenprincipe: indiener mag eigen modelwijziging niet goedkeuren.');
    }
    if (trim($approvalNote) === '') {
      throw new \InvalidArgumentException('Goedkeuringsnotitie is verplicht.');
    }

    $this->database->update('brebo_procurement_model_review')->fields([
      'status' => 'approved_for_implementation',
      'approved_by' => $approvedBy,
      'approved_at' => $now,
      'approval_note' => $approvalNote,
    ])->condition('id', $reviewId)->execute();

    return [
      'review_id' => $reviewId,
      'status' => 'approved_for_implementation',
      'automatic_activation' => FALSE,
      'message' => 'Review goedgekeurd. Implementatie blijft een aparte, traceerbare technische wijziging.',
    ];
  }
}
