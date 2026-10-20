<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Infrastructure;

use Drupal\brebo_office_core\Contract\ProjectScopeDecisionRepositoryInterface;
use Drupal\brebo_office_core\Domain\ProjectScopeDecision;
use Drupal\Core\Database\Connection;

final class DatabaseProjectScopeDecisionRepository implements ProjectScopeDecisionRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function get(int $decisionId): ?array {
    $row = $this->database->select('brebo_project_scope_decision', 'd')
      ->fields('d')
      ->condition('id', $decisionId)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  public function currentForProject(int $projectId): array {
    return $this->database->select('brebo_project_scope_decision', 'd')
      ->fields('d')
      ->condition('project_id', $projectId)
      ->condition('status', 'active')
      ->orderBy('subject_key')
      ->orderBy('id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function historyForSubject(int $projectId, string $subjectKey): array {
    return $this->database->select('brebo_project_scope_decision', 'd')
      ->fields('d')
      ->condition('project_id', $projectId)
      ->condition('subject_key', $subjectKey)
      ->orderBy('id', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function save(ProjectScopeDecision $decision): int {
    return (int) $this->database->insert('brebo_project_scope_decision')
      ->fields([
        'project_id' => $decision->projectId,
        'subject_key' => trim($decision->subjectKey),
        'statement_type' => $decision->statementType->value,
        'disposition' => $decision->disposition->value,
        'summary' => trim($decision->summary),
        'source_type' => trim($decision->sourceType),
        'source_ref' => trim($decision->sourceRef),
        'source_occurred_at' => $decision->sourceOccurredAt,
        'source_excerpt' => $decision->sourceExcerpt,
        'element_ref' => $decision->elementRef,
        'calculation_fact_id' => $decision->calculationFactId,
        'supersedes_id' => $decision->supersedesId,
        'status' => 'active',
        'confirmed_by' => $decision->confirmedBy,
        'confirmed_at' => $decision->confirmedAt,
        'created' => $decision->confirmedAt,
      ])
      ->execute();
  }

  public function markSuperseded(int $decisionId, int $supersededById): void {
    $this->database->update('brebo_project_scope_decision')
      ->fields([
        'status' => 'superseded',
        'superseded_by_id' => $supersededById,
      ])
      ->condition('id', $decisionId)
      ->condition('status', 'active')
      ->execute();
  }

  public function transactional(callable $callback): mixed {
    $transaction = $this->database->startTransaction();
    try {
      return $callback();
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

}
