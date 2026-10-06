<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\CollectionProviderRuntimeConfigInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

final class DrupalNlLegalRuntimeConfig implements CollectionProviderRuntimeConfigInterface {

  private const DEFAULT_BASE_URL = 'https://nl.legal/api/v1';

  public function __construct(private readonly ConfigFactoryInterface $configFactory) {}

  public function apiKey(): string {
    $env = trim((string) getenv('NLLEGAL_API_KEY'));
    if ($env !== '') {
      return $env;
    }
    return trim((string) $this->configFactory->get('brebo_finance.collection')->get('nllegal_api_key'));
  }

  public function baseUrl(): string {
    $configured = trim((string) $this->configFactory->get('brebo_finance.collection')->get('nllegal_base_url'));
    return rtrim($configured !== '' ? $configured : self::DEFAULT_BASE_URL, '/');
  }

}
