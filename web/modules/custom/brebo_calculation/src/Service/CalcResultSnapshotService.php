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

  public function latestReadModel(int $calculationId): ?array {
    $snapshot = $this->latest($calculationId);
    if ($snapshot === NULL) {
      return NULL;
    }
    $payload = is_array($snapshot['payload'] ?? NULL) ? $snapshot['payload'] : [];
    $totals = is_array($payload['totals'] ?? NULL) ? $payload['totals'] : [];
    $lines = is_array($payload['lines'] ?? NULL) ? array_values($payload['lines']) : [];

    return [
      'snapshot_id' => (int) $snapshot['snapshot_id'],
      'content_hash' => (string) $snapshot['content_hash'],
      'published_by' => (int) $snapshot['published_by'],
      'published_at' => (int) $snapshot['published_at'],
      'calculation_id' => (int) ($payload['calculation_id'] ?? $calculationId),
      'office_version' => (string) ($payload['office_version'] ?? ''),
      'calc_version' => (string) ($payload['calc_version'] ?? ''),
      'totals' => [
        'direct_cost' => (float) ($totals['direct_cost'] ?? 0),
        'markup_amount' => (float) ($totals['markup_amount'] ?? 0),
        'sales_price' => (float) ($totals['sales_price'] ?? 0),
      ],
      'lines' => array_map(static function (mixed $line): array {
        $line = is_array($line) ? $line : [];
        $unitCosts = is_array($line['unit_costs'] ?? NULL) ? $line['unit_costs'] : [];
        $source = is_array($line['source'] ?? NULL) ? $line['source'] : [];
        return [
          'sort_order' => (int) ($line['sort_order'] ?? 0),
          'line_type' => (string) ($line['line_type'] ?? ''),
          'parent_ref' => $line['parent_ref'] ?? NULL,
          'code' => $line['code'] ?? NULL,
          'description' => (string) ($line['description'] ?? ''),
          'unit' => $line['unit'] ?? NULL,
          'quantity' => $line['quantity'] ?? NULL,
          'labour_norm' => $line['labour_norm'] ?? NULL,
          'labour_total_hours' => $line['labour_total_hours'] ?? NULL,
          'labour_hours_input_mode' => $line['labour_hours_input_mode'] ?? NULL,
          'unit_costs' => [
            'labour' => (float) ($unitCosts['labour'] ?? 0),
            'material' => (float) ($unitCosts['material'] ?? 0),
            'equipment' => (float) ($unitCosts['equipment'] ?? 0),
            'subcontracting' => (float) ($unitCosts['subcontracting'] ?? 0),
            'other' => (float) ($unitCosts['other'] ?? 0),
          ],
          'source' => [
            'type' => (string) ($source['type'] ?? 'manual'),
            'office_source_id' => $source['office_source_id'] ?? NULL,
            'reference' => $source['reference'] ?? NULL,
            'supplier' => $source['supplier'] ?? NULL,
            'unit_price' => $source['unit_price'] ?? NULL,
            'price_date' => $source['price_date'] ?? NULL,
            'document_id' => $source['document_id'] ?? NULL,
            'details' => $source['details'] ?? NULL,
          ],
        ];
      }, $lines),
      'source' => is_array($payload['source'] ?? NULL) ? $payload['source'] : [],
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
