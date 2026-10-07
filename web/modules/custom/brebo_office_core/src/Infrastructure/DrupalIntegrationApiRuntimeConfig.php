<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Infrastructure;

use Drupal\brebo_office_core\Contract\IntegrationApiRuntimeConfigInterface;
use Drupal\Core\Site\Settings;

final class DrupalIntegrationApiRuntimeConfig implements IntegrationApiRuntimeConfigInterface {

  public function configuration(): ?array {
    $baseUrl = rtrim(trim((string) Settings::get('brebo_integration_api_url', getenv('BREBO_INTEGRATION_API_URL') ?: '')), '/');
    $sharedSecret = trim((string) Settings::get('brebo_shared_secret', getenv('BREBO_SHARED_SECRET') ?: ''));
    if ($baseUrl === '' || $sharedSecret === '') {
      return NULL;
    }
    return ['base_url' => $baseUrl, 'shared_secret' => $sharedSecret];
  }

}
