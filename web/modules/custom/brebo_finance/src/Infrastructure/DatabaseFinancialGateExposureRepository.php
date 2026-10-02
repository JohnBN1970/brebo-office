<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\FinancialGateExposureRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Database adapter for Finance control-finding exposure payloads. */
final class DatabaseFinancialGateExposureRepository implements FinancialGateExposureRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function findings(int $projectId, array $findingIds): array {
    if ($findingIds === []) {
      return [];
    }
    return array_values($this->database->select('brebo_finance_control_finding', 'f')
      ->fields('f', ['id', 'project_nid', 'control_code', 'source_type', 'source_id', 'payload'])
      ->condition('project_nid', $projectId)
      ->condition('id', $findingIds, 'IN')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC));
  }

}
