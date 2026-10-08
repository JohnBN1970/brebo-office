<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalcResultSnapshotRepositoryInterface;

/**
 * Stores immutable results produced by the external Calc engine.
 */
final class CalcResultSnapshotService {

  public function __construct(private readonly CalcResultSnapshotRepositoryInterface $repository) {}

  /** @param array<string,mixed> $payload */
  public function publish(int $calculationId, array $payload, int $actorId): array {
    $officeVersion = trim((string) ($payload['office_version'] ?? ''));
    $calcVersion = trim((string) ($payload['calc_version'] ?? ''));
    $summary = $payload['commercial_summary'] ?? NULL;
    if ($calculationId <= 0 || $actorId <= 0 || $officeVersion === '' || $calcVersion === '' || !is_array($summary)) {
      throw new \InvalidArgumentException('calculation, office_version, calc_version and commercial_summary are required.');
    }

    foreach (['purchase', 'sales', 'margin', 'margin_pct', 'vat', 'total_incl_vat'] as $key) {
      if (!array_key_exists($key, $summary) || !is_numeric($summary[$key])) {
        throw new \InvalidArgumentException('commercial_summary.' . $key . ' must be numeric.');
      }
    }
    $vatRate = array_key_exists('vat_rate', $summary) && $summary['vat_rate'] !== NULL
      ? (float) $summary['vat_rate']
      : NULL;
    $expectedTotalInclVat = (float) $summary['sales'] + (float) $summary['vat'];
    if (abs((float) $summary['total_incl_vat'] - $expectedTotalInclVat) > 0.01) {
      throw new \InvalidArgumentException('commercial_summary.total_incl_vat must equal sales + vat.');
    }

    $vatBreakdown = [];
    $rawBreakdown = $summary['vat_breakdown'] ?? [];
    if (!is_array($rawBreakdown)) {
      throw new \InvalidArgumentException('commercial_summary.vat_breakdown must be an array.');
    }
    foreach ($rawBreakdown as $index => $item) {
      if (!is_array($item)) {
        throw new \InvalidArgumentException('commercial_summary.vat_breakdown[' . $index . '] must be an object.');
      }
      foreach (['code', 'label', 'taxable_base', 'vat_amount', 'reverse_charged'] as $key) {
        if (!array_key_exists($key, $item)) {
          throw new \InvalidArgumentException('commercial_summary.vat_breakdown[' . $index . '].' . $key . ' is required.');
        }
      }
      if (!is_numeric($item['taxable_base']) || !is_numeric($item['vat_amount'])) {
        throw new \InvalidArgumentException('commercial_summary.vat_breakdown amounts must be numeric.');
      }
      $rate = array_key_exists('rate', $item) && $item['rate'] !== NULL ? (float) $item['rate'] : NULL;
      $vatBreakdown[] = [
        'code' => trim((string) $item['code']),
        'label' => trim((string) $item['label']),
        'rate' => $rate,
        'taxable_base' => (float) $item['taxable_base'],
        'vat_amount' => (float) $item['vat_amount'],
        'reverse_charged' => (bool) $item['reverse_charged'],
      ];
    }
    usort($vatBreakdown, static fn(array $a, array $b): int => [$a['code'], $a['label']] <=> [$b['code'], $b['label']]);

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
        'total_incl_vat' => (float) $summary['total_incl_vat'],
        'vat_rate' => $vatRate,
        'vat_breakdown' => $vatBreakdown,
      ],
    ];
    $json = json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $hash = hash('sha256', $json);

    return $this->repository->publish($calculationId, $officeVersion, $calcVersion, $canonical, $json, $hash, $actorId);
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
        'total_incl_vat' => (float) ($row['sales_price'] ?? 0),
        'vat_rate' => NULL,
        'vat_breakdown' => [],
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
        'total_incl_vat' => (float) ($summary['total_incl_vat'] ?? ((float) ($summary['sales'] ?? 0) + (float) ($summary['vat'] ?? 0))),
        'vat_rate' => isset($summary['vat_rate']) ? (float) $summary['vat_rate'] : NULL,
        'vat_breakdown' => is_array($summary['vat_breakdown'] ?? NULL) ? $summary['vat_breakdown'] : [],
      ],
    ];
  }

  public function latest(int $calculationId): ?array {
    return $this->repository->latest($calculationId);
  }

}
