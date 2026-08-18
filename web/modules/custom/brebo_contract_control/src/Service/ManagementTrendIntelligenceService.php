<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\Core\Database\Connection;

/** Builds period-over-period management trends from control snapshots. */
final class ManagementTrendIntelligenceService {

  public function __construct(private readonly Connection $database) {}

  /** @return array<string, mixed> */
  public function compare(array $currentHeadline, ?int $now = NULL): array {
    $this->ensureStorage();
    $now ??= time();
    $currentStart = strtotime('first day of this month 00:00:00', $now);
    $previousStart = strtotime('first day of last month 00:00:00', $now);
    $previousEnd = $currentStart - 1;
    $previous = $this->latestSnapshot($previousStart, $previousEnd);
    if ($previous === NULL) {
      return ['available' => FALSE, 'period' => 'month', 'message' => 'Nog geen vorige maand-snapshot beschikbaar.'];
    }
    $metrics = [];
    foreach (['blocked_payment_value', 'controller_case_exposure', 'critical_controller_cases', 'overdue_contract_obligations', 'portfolio_risk_score', 'suppliers_below_c_rating'] as $key) {
      $current = (float) ($currentHeadline[$key] ?? 0);
      $old = (float) ($previous[$key] ?? 0);
      $delta = $current - $old;
      $metrics[$key] = ['current' => $current, 'previous' => $old, 'delta' => round($delta, 2), 'direction' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'), 'risk_direction' => $delta > 0 ? 'worse' : ($delta < 0 ? 'better' : 'stable')];
    }
    return ['available' => TRUE, 'period' => 'month', 'previous_period_start' => $previousStart, 'previous_period_end' => $previousEnd, 'metrics' => $metrics];
  }

  public function record(array $headline, ?int $now = NULL): void {
    $this->ensureStorage();
    $now ??= time();
    $periodKey = gmdate('Y-m', $now);
    $encoded = json_encode($headline, JSON_THROW_ON_ERROR);
    $existing = $this->database->select('brebo_management_snapshot', 's')->fields('s', ['id'])->condition('period_key', $periodKey)->execute()->fetchField();
    $fields = ['period_key' => $periodKey, 'headline_json' => $encoded, 'snapshot_hash' => hash('sha256', $encoded), 'captured_at' => $now];
    if ($existing) { $this->database->update('brebo_management_snapshot')->fields($fields)->condition('id', (int) $existing)->execute(); }
    else { $this->database->insert('brebo_management_snapshot')->fields($fields)->execute(); }
  }

  public function ensureStorage(): void {
    $schema = $this->database->schema();
    if ($schema->tableExists('brebo_management_snapshot')) { return; }
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
  private function latestSnapshot(int $start, int $end): ?array {
    $row = $this->database->select('brebo_management_snapshot', 's')->fields('s')->condition('captured_at', $start, '>=')->condition('captured_at', $end, '<=')->orderBy('captured_at', 'DESC')->range(0, 1)->execute()->fetchAssoc();
    if (!$row) { return NULL; }
    $decoded = json_decode((string) $row['headline_json'], TRUE);
    return is_array($decoded) ? $decoded : NULL;
  }
}
