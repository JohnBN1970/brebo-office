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
    $summary = $payload['commercial_summary'] ?? NULL;
    if ($calculationId <= 0 || $actorId <= 0 || $officeVersion === '' || $calcVersion === '' || !is_array($summary)) {
      throw new \InvalidArgumentException('calculation, office_version, calc_version and commercial_summary are required.');
    }

    foreach (['purchase', 'sales', 'margin', 'margin_pct', 'vat'] as $key) {
      if (!array_key_exists($key, $summary) || !is_numeric($summary[$key])) {
        throw new \InvalidArgumentException('commercial_summary.' . $key . ' must be numeric.');
      }
    }
    $vatRate = array_key_exists('vat_rate', $summary) && $summary['vat_rate'] !== NULL
      ? (float) $summary['vat_rate']
      : NULL;

    $canonical = [
      'contract' => 'brebo-calc-commercial-summary-v1',
      'calculation_id' => $calculationId,
      'office_version' => $officeVersion,
      'calc_version' => $calcVersion,
      'commercial_summary' => [
        'purchase' => (float) $summary['purchase'],
        'sales' => (float) $summary['sales'],
        'margin' => (float) $summary['margin'],
        'margin_pct' => (float) $summary['margin_pct'],
        'vat' => (float) $summary['vat'],
        'vat_rate' => $vatRate,
      ],
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

  public function latestReadModel(int $calculationId): ?array {
    $snapshot = $this->latest($calculationId);
    if ($snapshot === NULL) {
      return NULL;
    }
    $payload = is_array($snapshot['payload'] ?? NULL) ? $snapshot['payload'] : [];
    $summary = is_array($payload['commercial_summary'] ?? NULL) ? $payload['commercial_summary'] : [];

    // Backward-compatible read of older Calc snapshots while new publications
    // expose only the commercial summary to Office.
    if ($summary === []) {
      $totals = is_array($payload['totals'] ?? NULL) ? $payload['totals'] : [];
      $purchase = (float) ($totals['direct_cost'] ?? 0);
      $sales = (float) ($totals['sales_price'] ?? 0);
      $margin = $sales - $purchase;
      $summary = [
        'purchase' => $purchase,
        'sales' => $sales,
        'margin' => $margin,
        'margin_pct' => $sales != 0.0 ? ($margin / $sales) * 100.0 : 0.0,
        'vat' => 0.0,
        'vat_rate' => NULL,
      ];
    }

    return [
      'snapshot_id' => (int) $snapshot['snapshot_id'],
      'content_hash' => (string) $snapshot['content_hash'],
      'published_by' => (int) $snapshot['published_by'],
      'published_at' => (int) $snapshot['published_at'],
      'calculation_id' => (int) ($payload['calculation_id'] ?? $calculationId),
      'office_version' => (string) ($payload['office_version'] ?? ''),
      'calc_version' => (string) ($payload['calc_version'] ?? ''),
      'commercial_summary' => [
        'purchase' => (float) ($summary['purchase'] ?? 0),
        'sales' => (float) ($summary['sales'] ?? 0),
        'margin' => (float) ($summary['margin'] ?? 0),
        'margin_pct' => (float) ($summary['margin_pct'] ?? 0),
        'vat' => (float) ($summary['vat'] ?? 0),
        'vat_rate' => isset($summary['vat_rate']) ? (float) $summary['vat_rate'] : NULL,
      ],
    ];
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
