<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\Core\Database\Connection;
use Drupal\brebo_finance\Contract\FinancialEuroTraceFindingRepositoryInterface;

/** Drupal database adapter for Euro Trace control findings. */
final class DatabaseFinancialEuroTraceFindingRepository implements FinancialEuroTraceFindingRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function activeFinding(int $projectNid, string $controlCode): ?array {
    $row = $this->database->select('brebo_finance_control_finding', 'f')->fields('f')
      ->condition('project_nid', $projectNid)->condition('control_code', $controlCode)
      ->condition('status', ['resolved_verified', 'resolved_automatically'], 'NOT IN')
      ->execute()->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function updateFinding(int $findingId, array $fields): void {
    $this->database->update('brebo_finance_control_finding')->fields($fields)->condition('id', $findingId)->execute();
  }

  public function createFinding(array $fields): int {
    return (int) $this->database->insert('brebo_finance_control_finding')->fields($fields)->execute();
  }

  public function staleFindings(int $projectNid, array $knownCodes, array $activeCodes): array {
    $query = $this->database->select('brebo_finance_control_finding', 'f')->fields('f', ['id', 'control_code'])
      ->condition('project_nid', $projectNid)->condition('control_code', $knownCodes, 'IN')
      ->condition('status', ['resolved_verified', 'resolved_automatically'], 'NOT IN');
    if ($activeCodes !== []) {
      $query->condition('control_code', $activeCodes, 'NOT IN');
    }
    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

}
