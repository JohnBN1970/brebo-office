<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\CashFlowForecastRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for cash events and immutable forecast snapshots. */
final class DatabaseCashFlowForecastRepository implements CashFlowForecastRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function cashEvent(string $sourceSystem, string $sourceType, string $sourceId, string $accountBucket): ?array {
    $row = $this->database->select('brebo_finance_cash_event', 'e')
      ->fields('e')
      ->condition('source_system', $sourceSystem)
      ->condition('source_type', $sourceType)
      ->condition('source_id', $sourceId)
      ->condition('account_bucket', $accountBucket)
      ->execute()
      ->fetchAssoc();
    return $row === FALSE ? NULL : $row;
  }

  public function createCashEvent(array $fields): int {
    return (int) $this->database->insert('brebo_finance_cash_event')->fields($fields)->execute();
  }

  public function updateCashEvent(int $eventId, array $fields): void {
    $this->database->update('brebo_finance_cash_event')->fields($fields)->condition('id', $eventId)->execute();
  }

  public function snapshotExists(int $projectNid, string $snapshotDate, string $scenario): bool {
    return (int) $this->database->select('brebo_finance_cash_forecast_snapshot', 's')
      ->condition('project_nid', $projectNid)
      ->condition('snapshot_date', $snapshotDate)
      ->condition('scenario', $scenario)
      ->countQuery()->execute()->fetchField() > 0;
  }

  public function events(int $projectNid, string $endDate, array $statuses): array {
    return $this->database->select('brebo_finance_cash_event', 'e')
      ->fields('e')
      ->condition('project_nid', $projectNid)
      ->condition('status', $statuses, 'IN')
      ->condition('due_date', $endDate, '<=')
      ->orderBy('due_date')
      ->orderBy('id')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function createSnapshot(array $fields): int {
    return (int) $this->database->insert('brebo_finance_cash_forecast_snapshot')->fields($fields)->execute();
  }

  public function appendAudit(array $fields): void {
    $this->database->insert('brebo_finance_audit')->fields($fields)->execute();
  }

}
