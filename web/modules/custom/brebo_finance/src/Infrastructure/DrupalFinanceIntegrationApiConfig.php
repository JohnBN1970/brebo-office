<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\FinanceIntegrationApiConfigInterface;
use Drupal\Core\Site\Settings;

final class DrupalFinanceIntegrationApiConfig implements FinanceIntegrationApiConfigInterface {

  public function configuration(): array {
    return [
      'base_url' => rtrim(trim((string) Settings::get('brebo_integration_api_url', '')), '/'),
      'shared_secret' => trim((string) Settings::get('brebo_integration_shared_secret', '')),
    ];
  }

}
