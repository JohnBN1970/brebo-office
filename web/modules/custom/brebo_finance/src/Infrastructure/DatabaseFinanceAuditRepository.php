<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\FinanceAuditRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseFinanceAuditRepository implements FinanceAuditRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function available(): bool {
    return $this->database->schema()->tableExists('brebo_finance_audit');
  }

  public function append(array $fields): void {
    $this->database->insert('brebo_finance_audit')->fields($fields)->execute();
  }

}
