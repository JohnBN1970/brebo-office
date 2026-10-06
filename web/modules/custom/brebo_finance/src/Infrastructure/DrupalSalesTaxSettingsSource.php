<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\SalesTaxSettingsSourceInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/** Drupal config adapter for Finance sales tax settings. */
final class DrupalSalesTaxSettingsSource implements SalesTaxSettingsSourceInterface {

  public function __construct(private readonly ConfigFactoryInterface $configFactory) {}

  public function salesSettings(): array {
    $config = $this->configFactory->get('brebo_finance.sales');
    return [
      'vat_rates' => $config->get('vat_rates'),
      'g_account' => [
        'enabled' => $config->get('g_account.enabled'),
        'default_percentage' => $config->get('g_account.default_percentage'),
        'regular_iban' => $config->get('g_account.regular_iban'),
        'g_iban' => $config->get('g_account.g_iban'),
      ],
    ];
  }

}
