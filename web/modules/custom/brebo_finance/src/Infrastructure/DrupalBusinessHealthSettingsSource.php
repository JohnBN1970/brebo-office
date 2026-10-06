<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\BusinessHealthSettingsSourceInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

final class DrupalBusinessHealthSettingsSource implements BusinessHealthSettingsSourceInterface {

  public function __construct(private readonly ConfigFactoryInterface $configFactory) {}

  public function fixedCostCategories(): ?array {
    $value = $this->configFactory->get('brebo_finance.business_health')->get('fixed_cost_categories');
    return is_array($value) ? $value : NULL;
  }

  public function liquidityThresholds(): array {
    $config = $this->configFactory->get('brebo_finance.business_health');
    return [
      'red' => max(0.0, (float) ($config->get('liquidity.red_months') ?? 1)),
      'orange' => max(0.0, (float) ($config->get('liquidity.orange_months') ?? 2)),
    ];
  }

  public function bankAccountRoles(): array {
    $roles = $this->configFactory->get('brebo_finance.business_health')->get('bank_account_roles');
    return is_array($roles) ? array_map('strval', $roles) : [];
  }

}
