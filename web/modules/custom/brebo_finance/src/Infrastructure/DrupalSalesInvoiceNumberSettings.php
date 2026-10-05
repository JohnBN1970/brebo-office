<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\SalesInvoiceNumberSettingsInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/** Drupal config adapter for BREBO sales-invoice numbering. */
final class DrupalSalesInvoiceNumberSettings implements SalesInvoiceNumberSettingsInterface {

  public function __construct(private readonly ConfigFactoryInterface $configFactory) {}

  public function settings(): array {
    $config = $this->configFactory->get('brebo_finance.sales');
    return [
      'prefix' => (string) ($config->get('numbering.prefix') ?? 'VF-'),
      'separator' => (string) ($config->get('numbering.separator') ?? '-'),
      'digits' => max(1, min(10, (int) ($config->get('numbering.digits') ?? 4))),
      'include_year' => (bool) ($config->get('numbering.include_year') ?? TRUE),
      'start_number' => max(1, (int) ($config->get('numbering.start_number') ?? 1)),
      'reset_yearly' => (bool) ($config->get('numbering.reset_yearly') ?? TRUE),
    ];
  }

}
