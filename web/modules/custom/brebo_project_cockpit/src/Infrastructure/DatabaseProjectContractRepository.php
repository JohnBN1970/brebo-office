<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Infrastructure;

use Drupal\brebo_project_cockpit\Contract\ProjectContractRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for project-contract persistence. */
final class DatabaseProjectContractRepository implements ProjectContractRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function contract(int $projectId): array {
    if (!$this->database->schema()->tableExists('brebo_finance_project_contract')) {
      return [];
    }
    $row = $this->database->select('brebo_finance_project_contract', 'c')
      ->fields('c')
      ->condition('project_nid', $projectId)
      ->execute()
      ->fetchAssoc();
    return is_array($row) ? $row : [];
  }

  public function obligations(int $projectId): array {
    if (!$this->database->schema()->tableExists('brebo_finance_contract_obligation')) {
      return [];
    }
    return array_values($this->database->select('brebo_finance_contract_obligation', 'o')
      ->fields('o')
      ->condition('project_nid', $projectId)
      ->orderBy('due_date', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC));
  }

  public function contractNumberUsedByOtherProject(string $contractNumber, int $projectId): bool {
    if (!$this->database->schema()->tableExists('brebo_finance_project_contract')) {
      return FALSE;
    }
    return (bool) $this->database->select('brebo_finance_project_contract', 'c')
      ->condition('contract_number', $contractNumber)
      ->condition('project_nid', $projectId, '<>')
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  public function saveDraft(int $projectId, array $fields, int $now, int $actorUid): bool {
    $current = $this->contract($projectId);
    if ($current !== []) {
      return $this->database->update('brebo_finance_project_contract')
        ->fields($fields)
        ->condition('project_nid', $projectId)
        ->condition('status', 'draft')
        ->execute() > 0;
    }

    $this->database->insert('brebo_finance_project_contract')
      ->fields($fields + [
        'project_nid' => $projectId,
        'created' => $now,
        'created_by' => $actorUid,
      ])
      ->execute();
    return TRUE;
  }

  public function commercialScheduleRecord(int $projectId): ?array {
    if (!$this->database->schema()->tableExists('brebo_project_commercial_instalment_schedule')) {
      return NULL;
    }
    $row = $this->database->select('brebo_project_commercial_instalment_schedule', 's')
      ->fields('s')
      ->condition('project_nid', $projectId)
      ->execute()
      ->fetchAssoc();
    return is_array($row) ? $row : NULL;
  }

  public function approveDraft(int $contractId, array $fields): bool {
    return $this->database->update('brebo_finance_project_contract')
      ->fields($fields)
      ->condition('id', $contractId)
      ->condition('status', 'draft')
      ->execute() > 0;
  }

}
