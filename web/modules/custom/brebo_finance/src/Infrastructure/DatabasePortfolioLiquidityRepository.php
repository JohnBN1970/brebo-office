<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\PortfolioLiquidityRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabasePortfolioLiquidityRepository implements PortfolioLiquidityRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function cashEventSchemaAvailable(): bool {
    $schema = $this->database->schema();
    if (!$schema->tableExists('brebo_finance_cash_event')) return FALSE;
    foreach (['project_nid', 'direction', 'account_bucket', 'amount_inc_vat', 'due_date', 'status'] as $field) {
      if (!$schema->fieldExists('brebo_finance_cash_event', $field)) return FALSE;
    }
    return TRUE;
  }

  public function events(array $projectIds, string $endDate, array $statuses): array {
    if ($projectIds === []) return [];
    $query = $this->database->select('brebo_finance_cash_event', 'e');
    $query->fields('e', ['project_nid', 'direction', 'account_bucket', 'amount_inc_vat', 'due_date', 'status', 'confidence', 'source_system', 'source_type', 'source_id']);
    $query->condition('project_nid', $projectIds, 'IN');
    $query->condition('status', $statuses, 'IN');
    $query->condition('due_date', $endDate, '<=');
    $query->orderBy('due_date', 'ASC');
    $query->orderBy('id', 'ASC');
    return array_values($query->execute()->fetchAll(\PDO::FETCH_ASSOC));
  }

}
