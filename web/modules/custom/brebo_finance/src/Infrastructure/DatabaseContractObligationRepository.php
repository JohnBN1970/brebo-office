<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\ContractObligationRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for contractual obligations. */
final class DatabaseContractObligationRepository implements ContractObligationRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function approvedContractExists(int $projectNid, int $contractId): bool {
    return (int) $this->database->select('brebo_finance_project_contract', 'c')
      ->condition('id', $contractId)
      ->condition('project_nid', $projectNid)
      ->condition('status', 'approved')
      ->countQuery()->execute()->fetchField() === 1;
  }

  public function create(array $fields): int {
    return (int) $this->database->insert('brebo_finance_contract_obligation')->fields($fields)->execute();
  }

  public function get(int $obligationId): ?array {
    $row = $this->database->select('brebo_finance_contract_obligation', 'o')
      ->fields('o')->condition('id', $obligationId)->execute()->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function update(int $obligationId, array $fields): void {
    $this->database->update('brebo_finance_contract_obligation')->fields($fields)->condition('id', $obligationId)->execute();
  }

  public function appendAudit(array $fields): void {
    $this->database->insert('brebo_finance_audit')->fields($fields)->execute();
  }

}
