<?php

declare(strict_types=1);

namespace Drupal\brebo_procurement_control\Infrastructure;

use Drupal\brebo_procurement_control\Contract\ProcurementControlRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for procurement control persistence. */
final class DatabaseProcurementControlRepository implements ProcurementControlRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function decision(int $decisionId): ?array {
    $row = $this->database->select('brebo_procurement_decision', 'd')
      ->fields('d')
      ->condition('id', $decisionId)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  public function createDecision(array $fields): int {
    return (int) $this->database->insert('brebo_procurement_decision')->fields($fields)->execute();
  }

  public function awardByDecision(int $decisionId): ?array {
    $row = $this->database->select('brebo_procurement_award', 'a')
      ->fields('a')
      ->condition('decision_id', $decisionId)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  public function createAward(array $fields): int {
    return (int) $this->database->insert('brebo_procurement_award')->fields($fields)->execute();
  }

  public function outcomeExists(int $decisionId): bool {
    return (bool) $this->database->select('brebo_procurement_outcome', 'o')
      ->condition('decision_id', $decisionId)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  public function createOutcome(array $fields): int {
    return (int) $this->database->insert('brebo_procurement_outcome')->fields($fields)->execute();
  }

  public function upsertOutcome(int $decisionId, array $fields): void {
    $this->database->merge('brebo_procurement_outcome')
      ->key(['decision_id' => $decisionId])
      ->fields($fields)
      ->execute();
  }

  public function outcomes(): array {
    if (!$this->database->schema()->tableExists('brebo_procurement_outcome')) {
      return [];
    }
    return $this->database->select('brebo_procurement_outcome', 'o')
      ->fields('o')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function decisionOutcomeRows(): array {
    if (
      !$this->database->schema()->tableExists('brebo_procurement_decision')
      || !$this->database->schema()->tableExists('brebo_procurement_outcome')
    ) {
      return [];
    }

    $query = $this->database->select('brebo_procurement_decision', 'd');
    $query->join('brebo_procurement_outcome', 'o', 'o.decision_id = d.id');
    $query->fields('d', ['id', 'selected_supplier', 'recommended_supplier', 'economic_delta', 'decision_status', 'decided_by', 'approved_by']);
    $query->fields('o', ['actual_economic_cost', 'model_error', 'outcome', 'actual_failure_cost', 'actual_delay_cost', 'actual_warranty_cost']);
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function upsertDecisionContext(int $decisionId, array $fields): void {
    $this->database->merge('brebo_procurement_decision_context')
      ->key(['decision_id' => $decisionId])
      ->fields($fields)
      ->execute();
  }

  public function contextOutcomeRows(): array {
    if (!$this->database->schema()->tableExists('brebo_procurement_decision_context')) {
      return [];
    }

    $query = $this->database->select('brebo_procurement_decision_context', 'c');
    $query->join('brebo_procurement_decision', 'd', 'd.id = c.decision_id');
    $query->join('brebo_procurement_outcome', 'o', 'o.decision_id = c.decision_id');
    $query->fields('c', ['context_json']);
    $query->fields('d', ['selected_supplier', 'recommended_supplier']);
    $query->fields('o', ['outcome', 'model_error']);
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function createModelReview(array $fields): int {
    return (int) $this->database->insert('brebo_procurement_model_review')->fields($fields)->execute();
  }

  public function modelReview(int $reviewId): ?array {
    $row = $this->database->select('brebo_procurement_model_review', 'r')
      ->fields('r')
      ->condition('id', $reviewId)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  public function updateModelReview(int $reviewId, array $fields): void {
    $this->database->update('brebo_procurement_model_review')
      ->fields($fields)
      ->condition('id', $reviewId)
      ->execute();
  }

}
