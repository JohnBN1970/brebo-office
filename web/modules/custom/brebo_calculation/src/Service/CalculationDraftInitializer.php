<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalculationDraftRepositoryInterface;
/** Creates the first editable domain version for a newly created calculation. */
final class CalculationDraftInitializer {

  public function __construct(private readonly CalculationDraftRepositoryInterface $repository) {}

  /** @param array<string,mixed> $start */
  public function ensure(int $calculationId, array $start = []): string {
    if ($calculationId <= 0) {
      throw new \InvalidArgumentException('A saved BREBO calculation id is required.');
    }
    $existing = $this->repository->latestVersion($calculationId);
    if (is_string($existing) && $existing !== '') {
      return $existing;
    }

    $version = trim((string) ($start['version'] ?? '1.0'));
    $version = $version !== '' ? mb_substr($version, 0, 32) : '1.0';
    $generalCost = max(0.0, (float) ($start['general_cost_pct'] ?? 0));
    $risk = max(0.0, (float) ($start['risk_pct'] ?? 0));
    $profit = max(0.0, (float) ($start['profit_pct'] ?? 0));
    $adjustment = max(0.0, (float) ($start['commercial_adjustment'] ?? 0));
    $priceDate = trim((string) ($start['price_date'] ?? '')) ?: NULL;

    $payload = [
      'calculation_id' => $calculationId,
      'version' => $version,
      'status' => 'draft',
      'classification_system' => 'nl_sfb',
      'pricing_mode' => 'closed',
      'commercial_method' => 'tail_costs',
      'general_cost_pct' => $generalCost,
      'risk_pct' => $risk,
      'profit_pct' => $profit,
      'single_margin_pct' => 0.0,
      'commercial_adjustment' => $adjustment,
      'price_date' => $priceDate,
    ];

    $this->repository->insertVersion([
      'calculation_id' => $calculationId,
      'version' => $version,
      'status' => 'draft',
      'classification_system' => 'nl_sfb',
      'pricing_mode' => 'closed',
      'commercial_method' => 'tail_costs',
      'general_cost_pct' => $generalCost,
      'risk_pct' => $risk,
      'profit_pct' => $profit,
      'single_margin_pct' => 0,
      'commercial_adjustment' => $adjustment,
      'price_date' => $priceDate,
      'price_level' => NULL,
      'locked_at' => NULL,
      'locked_by' => NULL,
      'content_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION)),
    ]);

    return $version;
  }


}
