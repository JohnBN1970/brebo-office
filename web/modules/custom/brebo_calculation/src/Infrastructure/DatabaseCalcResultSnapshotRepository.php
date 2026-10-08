<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Infrastructure;

use Drupal\brebo_calculation\Contract\CalcResultSnapshotRepositoryInterface;
use Drupal\Core\Database\Connection;

/** Drupal database adapter; replaceable without changing Calc publication rules. */
final class DatabaseCalcResultSnapshotRepository implements CalcResultSnapshotRepositoryInterface {

  public function __construct(private readonly Connection $database) {}

  public function publish(int $calculationId, string $officeVersion, string $calcVersion, array $canonical, string $json, string $hash, int $actorId): array {
    $existing = $this->database->select('brebo_calculation_calc_result_snapshot', 's')
      ->fields('s', ['id', 'published_at'])
      ->condition('calculation_id', $calculationId)
      ->condition('content_hash', $hash)
      ->execute()
      ->fetchAssoc();
    if ($existing) {
      return ['snapshot_id' => (int) $existing['id'], 'content_hash' => $hash, 'created' => FALSE];
    }

    $publishedAt = time();
    $id = $this->database->insert('brebo_calculation_calc_result_snapshot')
      ->fields([
        'calculation_id' => $calculationId,
        'office_version' => $officeVersion,
        'calc_version' => $calcVersion,
        'content_hash' => $hash,
        'direct_cost' => $canonical['commercial_summary']['purchase'],
        'markup_amount' => $canonical['commercial_summary']['margin'],
        'sales_price' => $canonical['commercial_summary']['sales'],
        'payload_json' => $json,
        'published_by' => $actorId,
        'published_at' => $publishedAt,
      ])
      ->execute();

    return ['snapshot_id' => (int) $id, 'content_hash' => $hash, 'created' => TRUE, 'published_at' => $publishedAt];
  }

  public function latest(int $calculationId): ?array {
    $row = $this->database->select('brebo_calculation_calc_result_snapshot', 's')
      ->fields('s')
      ->condition('calculation_id', $calculationId)
      ->orderBy('published_at', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!$row) return NULL;
    $payload = json_decode((string) $row['payload_json'], TRUE, 512, JSON_THROW_ON_ERROR);
    return [
      'snapshot_id' => (int) $row['id'],
      'content_hash' => (string) $row['content_hash'],
      'published_by' => (int) $row['published_by'],
      'published_at' => (int) $row['published_at'],
      'payload' => $payload,
    ];
  }

}
