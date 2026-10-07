<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Infrastructure;

use Drupal\brebo_contract_control\Contract\ManagementTrendRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter for management trend snapshots. */
final class DatabaseManagementTrendRepository implements ManagementTrendRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function ensureStorage(): void {
    $schema = $this->database->schema();
    if ($schema->tableExists('brebo_management_snapshot')) {
      return;
    }

    $schema->createTable('brebo_management_snapshot', [
      'description' => 'Periodieke management control snapshots voor trendvergelijking.',
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
        'period_key' => ['type' => 'varchar', 'length' => 16, 'not null' => TRUE],
        'headline_json' => ['type' => 'text', 'size' => 'big', 'not null' => TRUE],
        'snapshot_hash' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE],
        'captured_at' => ['type' => 'int', 'not null' => TRUE],
      ],
      'primary key' => ['id'],
      'unique keys' => ['period_key' => ['period_key']],
      'indexes' => ['captured_at' => ['captured_at']],
    ]);
  }

  /** @return array<string, mixed>|null */
  public function findLatestSnapshot(int $start, int $end): ?array {
    $row = $this->database
      ->select('brebo_management_snapshot', 's')
      ->fields('s')
      ->condition('captured_at', $start, '>=')
      ->condition('captured_at', $end, '<=')
      ->orderBy('captured_at', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return $row ?: NULL;
  }

  /** @param array<string, mixed> $record */
  public function upsertSnapshot(string $periodKey, array $record): void {
    $existing = $this->database
      ->select('brebo_management_snapshot', 's')
      ->fields('s', ['id'])
      ->condition('period_key', $periodKey)
      ->execute()
      ->fetchField();

    if ($existing) {
      $this->database
        ->update('brebo_management_snapshot')
        ->fields($record)
        ->condition('id', (int) $existing)
        ->execute();
      return;
    }

    $this->database->insert('brebo_management_snapshot')->fields($record)->execute();
  }

}
