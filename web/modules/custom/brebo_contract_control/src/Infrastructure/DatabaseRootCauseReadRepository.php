<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Infrastructure;

use Drupal\brebo_contract_control\Contract\RootCauseReadRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for root-cause source reads. */
final class DatabaseRootCauseReadRepository implements RootCauseReadRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  /** @return array<int, array<string, mixed>> */
  public function findManagementActions(): array {
    if (!$this->database->schema()->tableExists('brebo_management_action')) {
      return [];
    }

    return $this->database
      ->select('brebo_management_action', 'a')
      ->fields('a')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

}
