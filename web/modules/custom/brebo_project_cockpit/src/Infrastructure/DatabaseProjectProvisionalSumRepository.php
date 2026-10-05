<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Infrastructure;

use Drupal\brebo_project_cockpit\Contract\ProjectProvisionalSumRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseProjectProvisionalSumRepository implements ProjectProvisionalSumRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function available(): bool {
    return $this->database->schema()->tableExists('brebo_finance_provisional_sum');
  }

  public function projectContractId(int $projectId): ?int {
    if (!$this->database->schema()->tableExists('brebo_finance_project_contract')) return NULL;
    $id = $this->database->select('brebo_finance_project_contract', 'c')
      ->fields('c', ['id'])
      ->condition('project_nid', $projectId)
      ->execute()
      ->fetchField();
    return $id === FALSE ? NULL : (int) $id;
  }

  public function numberExists(int $projectId, string $number): bool {
    if (!$this->available()) return FALSE;
    return (int) $this->database->select('brebo_finance_provisional_sum', 's')
      ->condition('project_nid', $projectId)
      ->condition('provisional_sum_number', $number)
      ->countQuery()
      ->execute()
      ->fetchField() > 0;
  }

  public function create(array $fields): int {
    return (int) $this->database->insert('brebo_finance_provisional_sum')->fields($fields)->execute();
  }

}
