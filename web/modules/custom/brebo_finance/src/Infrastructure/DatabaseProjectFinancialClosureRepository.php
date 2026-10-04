<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\Core\Database\Connection;
use Drupal\brebo_finance\Contract\ProjectFinancialClosureRepositoryInterface;

/** Drupal database adapter for immutable financial project closure. */
final class DatabaseProjectFinancialClosureRepository implements ProjectFinancialClosureRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function latestForecast(int $projectNid): ?array {
    $row = $this->database->select('brebo_finance_forecast_snapshot', 'f')->fields('f')
      ->condition('project_nid', $projectNid)
      ->orderBy('snapshot_date', 'DESC')->orderBy('id', 'DESC')->range(0, 1)
      ->execute()->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function countOpenByStatus(int $projectNid, string $table, array $closedStatuses): int {
    if (!$this->database->schema()->tableExists($table)) {
      return 0;
    }
    return (int) $this->database->select($table, 't')
      ->condition('project_nid', $projectNid)
      ->condition('status', $closedStatuses, 'NOT IN')
      ->countQuery()->execute()->fetchField();
  }

  public function closure(int $projectNid): ?array {
    $row = $this->database->select('brebo_finance_project_closure', 'c')->fields('c')
      ->condition('project_nid', $projectNid)->execute()->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function createClosure(array $payload): array {
    $id = (int) $this->database->insert('brebo_finance_project_closure')->fields($payload)->execute();
    $row = $this->database->select('brebo_finance_project_closure', 'c')->fields('c')
      ->condition('id', $id)->execute()->fetchAssoc();
    return $row === FALSE ? $payload : $row;
  }

  public function financialSourceStateHash(int $projectNid, array $sourceTables): string {
    $schema = $this->database->schema();
    $state = [];
    foreach ($sourceTables as $table) {
      if (!$schema->tableExists($table) || !$schema->fieldExists($table, 'project_nid')) {
        continue;
      }
      $query = $this->database->select($table, 't')->fields('t')->condition('project_nid', $projectNid);
      if ($schema->fieldExists($table, 'id')) {
        $query->orderBy('id', 'ASC');
      }
      $state[$table] = $query->execute()->fetchAll();
    }

    if ($schema->tableExists('brebo_finance_budget_line') && $schema->tableExists('brebo_finance_budget')) {
      $query = $this->database->select('brebo_finance_budget_line', 'l');
      $query->join('brebo_finance_budget', 'b', 'b.id = l.budget_id');
      $query->fields('l')->condition('b.project_nid', $projectNid)->orderBy('l.id', 'ASC');
      $state['brebo_finance_budget_line'] = $query->execute()->fetchAll();
    }

    return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
  }

}
