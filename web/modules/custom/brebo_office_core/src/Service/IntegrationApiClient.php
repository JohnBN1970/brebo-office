<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Beveiligde client voor de centrale BREBO Integration API.
 */
final class IntegrationApiClient implements IntegrationApiClientInterface {

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly LoggerInterface $logger,
    private readonly IntegrationApiRequestSigner $requestSigner,
  ) {}

  public function status(): array {
    $checkedAt = gmdate('c');
    $configuration = $this->getConfiguration();

    if ($configuration === NULL) {
      return [
        'state' => 'not_configured',
        'http_status' => NULL,
        'response_time_ms' => NULL,
        'checked_at' => $checkedAt,
      ];
    }

    $started = microtime(TRUE);

    try {
      $response = $this->httpClient->request(
        'GET',
        $configuration['base_url'] . '/health/status',
        [
          'headers' => $this->authenticatedHeaders(
            'GET',
            '/health/status',
            '',
            $configuration['shared_secret'],
          ),
          'allow_redirects' => FALSE,
          'connect_timeout' => 2.0,
          'timeout' => 4.0,
          'http_errors' => FALSE,
        ],
      );

      $elapsed = (int) round((microtime(TRUE) - $started) * 1000);
      $status = $response->getStatusCode();

      return [
        'state' => $status >= 200 && $status < 300
          ? 'healthy'
          : 'degraded',
        'http_status' => $status,
        'response_time_ms' => $elapsed,
        'checked_at' => $checkedAt,
      ];
    }
    catch (\Throwable $exception) {
      $this->logger->warning(
        'BREBO Integration API-statuscontrole mislukt ({exception_class}).',
        ['exception_class' => $exception::class],
      );

      return [
        'state' => 'unreachable',
        'http_status' => NULL,
        'response_time_ms' => (int) round(
          (microtime(TRUE) - $started) * 1000,
        ),
        'checked_at' => $checkedAt,
      ];
    }
  }

  public function analyzeTestCommunication(array $communication): array {
    $checkedAt = gmdate('c');
    $configuration = $this->getConfiguration();

    if ($configuration === NULL) {
      return [
        'state' => 'not_configured',
        'http_status' => NULL,
        'response_time_ms' => NULL,
        'checked_at' => $checkedAt,
        'analysis' => NULL,
      ];
    }

    $channel = trim((string) ($communication['channel'] ?? ''));
    $subject = trim((string) ($communication['subject'] ?? ''));
    $message = trim((string) ($communication['message'] ?? ''));

    if (
      $channel === '' ||
      $subject === '' ||
      $message === '' ||
      mb_strlen($channel) > 50 ||
      mb_strlen($subject) > 200 ||
      mb_strlen($message) > 4000
    ) {
      return [
        'state' => 'invalid_input',
        'http_status' => NULL,
        'response_time_ms' => NULL,
        'checked_at' => $checkedAt,
        'analysis' => NULL,
      ];
    }

    $started = microtime(TRUE);

    try {
      $body = $this->encodeJson([
        'test_mode' => TRUE,
        'contains_real_data' => FALSE,
        'communication' => [
          'channel' => $channel,
          'subject' => $subject,
          'message' => $message,
        ],
      ]);
      $response = $this->httpClient->request(
        'POST',
        $configuration['base_url'] . '/v1/communications/analyze',
        [
          'headers' => $this->authenticatedHeaders(
            'POST',
            '/v1/communications/analyze',
            $body,
            $configuration['shared_secret'],
            TRUE,
          ),
          'body' => $body,
          'allow_redirects' => FALSE,
          'connect_timeout' => 5.0,
          'timeout' => 45.0,
          'http_errors' => FALSE,
        ],
      );

      $elapsed = (int) round((microtime(TRUE) - $started) * 1000);
      $httpStatus = $response->getStatusCode();

      if ($httpStatus < 200 || $httpStatus >= 300) {
        return [
          'state' => 'rejected',
          'http_status' => $httpStatus,
          'response_time_ms' => $elapsed,
          'checked_at' => $checkedAt,
          'analysis' => NULL,
        ];
      }

      $result = json_decode(
        (string) $response->getBody(),
        TRUE,
        512,
        JSON_THROW_ON_ERROR,
      );

      if (
        !is_array($result) ||
        ($result['status'] ?? NULL) !== 'ok' ||
        ($result['mode'] ?? NULL) !== 'test' ||
        ($result['stored'] ?? NULL) !== FALSE ||
        ($result['sent'] ?? NULL) !== FALSE ||
        !is_array($result['analysis'] ?? NULL) ||
        ($result['analysis']['human_review_required'] ?? NULL) !== TRUE
      ) {
        $this->logger->warning(
          'BREBO Integration API gaf een ongeldige testanalyse-respons.',
        );

        return [
          'state' => 'invalid_response',
          'http_status' => $httpStatus,
          'response_time_ms' => $elapsed,
          'checked_at' => $checkedAt,
          'analysis' => NULL,
        ];
      }

      return [
        'state' => 'completed',
        'http_status' => $httpStatus,
        'response_time_ms' => $elapsed,
        'checked_at' => $checkedAt,
        'analysis' => $result['analysis'],
      ];
    }
    catch (\Throwable $exception) {
      $this->logger->warning(
        'BREBO testcommunicatie-analyse mislukt ({exception_class}).',
        ['exception_class' => $exception::class],
      );

      return [
        'state' => 'unreachable',
        'http_status' => NULL,
        'response_time_ms' => (int) round(
          (microtime(TRUE) - $started) * 1000,
        ),
        'checked_at' => $checkedAt,
        'analysis' => NULL,
      ];
    }
  }

  public function analyzeCommunication(array $communication): array {
    $checkedAt = gmdate('c');
    $configuration = $this->getConfiguration();
    if ($configuration === NULL) {
      return $this->analysisFailure('not_configured', $checkedAt);
    }

    $communicationId = (int) ($communication['communication_id'] ?? 0);
    $projectId = isset($communication['project_id'])
      ? (int) $communication['project_id']
      : NULL;
    $channel = trim((string) ($communication['channel'] ?? ''));
    $subject = trim((string) ($communication['subject'] ?? ''));
    $message = trim((string) ($communication['message'] ?? ''));
    if (
      $communicationId <= 0 || $channel === '' || $subject === '' || $message === '' ||
      mb_strlen($channel) > 50 || mb_strlen($subject) > 200 ||
      mb_strlen($message) > 12000
    ) {
      return $this->analysisFailure('invalid_input', $checkedAt);
    }

    $started = microtime(TRUE);
    try {
      $body = $this->encodeJson([
        'test_mode' => FALSE,
        'contains_real_data' => TRUE,
        'human_review_required' => TRUE,
        'communication' => [
          'id' => $communicationId,
          'project_id' => $projectId,
          'channel' => $channel,
          'subject' => $subject,
          'message' => $message,
        ],
      ]);
      $response = $this->httpClient->request(
        'POST',
        $configuration['base_url'] . '/v1/communications/analyze',
        [
          'headers' => $this->authenticatedHeaders(
            'POST',
            '/v1/communications/analyze',
            $body,
            $configuration['shared_secret'],
            TRUE,
          ),
          'body' => $body,
          'allow_redirects' => FALSE,
          'connect_timeout' => 5.0,
          'timeout' => 45.0,
          'http_errors' => FALSE,
        ],
      );
      $elapsed = (int) round((microtime(TRUE) - $started) * 1000);
      $httpStatus = $response->getStatusCode();
      if ($httpStatus < 200 || $httpStatus >= 300) {
        return $this->analysisFailure('rejected', $checkedAt, $httpStatus, $elapsed);
      }

      $result = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
      if (
        !is_array($result) || ($result['status'] ?? NULL) !== 'ok' ||
        ($result['stored'] ?? NULL) !== FALSE || ($result['sent'] ?? NULL) !== FALSE ||
        !is_array($result['analysis'] ?? NULL) ||
        ($result['analysis']['human_review_required'] ?? NULL) !== TRUE
      ) {
        $this->logger->warning('BREBO Integration API gaf een ongeldige communicatieanalyse-respons.');
        return $this->analysisFailure('invalid_response', $checkedAt, $httpStatus, $elapsed);
      }

      return [
        'state' => 'completed',
        'http_status' => $httpStatus,
        'response_time_ms' => $elapsed,
        'checked_at' => $checkedAt,
        'analysis' => $result['analysis'],
      ];
    }
    catch (\Throwable $exception) {
      $this->logger->warning(
        'BREBO communicatieanalyse mislukt ({exception_class}).',
        ['exception_class' => $exception::class],
      );
      return $this->analysisFailure(
        'unreachable',
        $checkedAt,
        NULL,
        (int) round((microtime(TRUE) - $started) * 1000),
      );
    }
  }

  /**
   * Builds a consistent failed-analysis result.
   */
  private function analysisFailure(
    string $state,
    string $checkedAt,
    ?int $httpStatus = NULL,
    ?int $responseTime = NULL,
  ): array {
    return [
      'state' => $state,
      'http_status' => $httpStatus,
      'response_time_ms' => $responseTime,
      'checked_at' => $checkedAt,
      'analysis' => NULL,
    ];
  }

  /**
   * @return array<string, string>
   */
  private function authenticatedHeaders(
    string $method,
    string $path,
    string $body,
    string $sharedSecret,
    bool $json = FALSE,
  ): array {
    $headers = [
      'Accept' => 'application/json',
      ...$this->requestSigner->sign($method, $path, $body, $sharedSecret),
    ];
    if ($json) {
      $headers['Content-Type'] = 'application/json';
    }

    return $headers;
  }

  private function encodeJson(array $data): string {
    return json_encode(
      $data,
      JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
    );
  }

  /**
   * Leest de API-configuratie zonder waarden te loggen of terug te geven.
   *
   * @return array{base_url: string, shared_secret: string}|null
   */
  private function getConfiguration(): ?array {
    $baseUrl = rtrim(trim((string) Settings::get(
      'brebo_integration_api_url',
      getenv('BREBO_INTEGRATION_API_URL') ?: '',
    )), '/');

    $sharedSecret = trim((string) Settings::get(
      'brebo_shared_secret',
      getenv('BREBO_SHARED_SECRET') ?: '',
    ));

    if ($baseUrl === '' || $sharedSecret === '') {
      return NULL;
    }

    return [
      'base_url' => $baseUrl,
      'shared_secret' => $sharedSecret,
    ];
  }

}
