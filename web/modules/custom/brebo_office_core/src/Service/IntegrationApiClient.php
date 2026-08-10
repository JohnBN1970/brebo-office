<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Read-only client for the central BREBO Integration API health endpoint.
 */
final class IntegrationApiClient implements IntegrationApiClientInterface {

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly LoggerInterface $logger,
  ) {}

  public function status(): array {
    $checkedAt = gmdate('c');
    $baseUrl = rtrim(trim((string) Settings::get(
      'brebo_integration_api_base_url',
      getenv('BREBO_INTEGRATION_API_BASE_URL') ?: '',
    )), '/');

    if ($baseUrl === '') {
      return [
        'state' => 'not_configured',
        'http_status' => NULL,
        'response_time_ms' => NULL,
        'checked_at' => $checkedAt,
      ];
    }

    $started = microtime(TRUE);
    try {
      $response = $this->httpClient->request('GET', $baseUrl . '/health/status', [
        'headers' => ['Accept' => 'application/json'],
        'allow_redirects' => FALSE,
        'connect_timeout' => 2.0,
        'timeout' => 4.0,
        'http_errors' => FALSE,
      ]);
      $elapsed = (int) round((microtime(TRUE) - $started) * 1000);
      $status = $response->getStatusCode();

      return [
        'state' => $status >= 200 && $status < 300 ? 'healthy' : 'degraded',
        'http_status' => $status,
        'response_time_ms' => $elapsed,
        'checked_at' => $checkedAt,
      ];
    }
    catch (\Throwable $exception) {
      $this->logger->warning('BREBO Integration API statuscontrole mislukt ({exception_class}).', [
        'exception_class' => $exception::class,
      ]);
      return [
        'state' => 'unreachable',
        'http_status' => NULL,
        'response_time_ms' => (int) round((microtime(TRUE) - $started) * 1000),
        'checked_at' => $checkedAt,
      ];
    }
  }

}
