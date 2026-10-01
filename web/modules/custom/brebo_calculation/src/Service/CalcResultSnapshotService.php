<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;

/**
 * Stores immutable results produced by the external Calc engine.
 */
final class CalcResultSnapshotService {

  public function __construct(private readonly Connection $database) {}

  /** @param array<string,mixed> $payload */
  public function publish(int $calculationId, array $payload, int $actorId): array {
    $officeVersion = trim((string) ($payload['office_version'] ?? ''));
    $calcVersion = trim((string) ($payload['calc_version'] ?? ''));
    $lines = $payload['lines'] ?? NULL;
    $totals = $payload['totals'] ?? NULL;
    if ($calculationId <= 0 || $actorId <= 0 || $officeVersion === '' || $calcVersion === '' || !is_array($lines) || !is_array($totals)) {
      throw new \InvalidArgumentException('calculation, office_version, calc_version, lines and totals are required.');
    }
    if (count($lines) > 5000) {
      throw new \InvalidArgumentException('Calc result contains too many lines.');
    }

    foreach (['direct_cost', 'markup_amount', 'sales_price'] as $key) {
      if (!isset($totals[$key]) || !is_numeric($totals[$key])) {
        throw new \InvalidArgumentException('totals.' . $key . ' must be numeric.');
      }
    }

    $canonical = [
      'contract' => 'brebo-calc-result-snapshot-v1',
      'calculation_id' => $calculationId,
      'office_version' => $officeVersion,
      'calc_version' => $calcVersion,
      'lines' => array_values($lines),
      'totals' => [
        'direct_cost' => (float) $totals['direct_cost'],
        'markup_amount' => (float) $totals['markup_amount'],
        'sales_price' => (float) $totals['sales_price'],
      ],
      'source' => is_array($payload['source'] ?? NULL) ? $payload['source'] : [],
    ];
    $json = json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $hash = hash('sha256', $json);

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
        'direct_cost' => $canonical['totals']['direct_cost'],
        'markup_amount' => $canonical['totals']['markup_amount'],
        'sales_price' => $canonical['totals']['sales_price'],
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
