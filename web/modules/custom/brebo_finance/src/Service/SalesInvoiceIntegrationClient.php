<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;

/** Sends authenticated sales-invoice commands to the BREBO integration API. */
final class SalesInvoiceIntegrationClient {

  private const PATH = '/v1/accounting/sales-invoices';

  public function __construct(private readonly ClientInterface $httpClient) {}

  /**
   * @return array<string, mixed>
   */
  public function dispatch(string $idempotencyKey, array $salesInvoice): array {
    $baseUrl = rtrim((string) Settings::get('brebo_integration_api_url', ''), '/');
    $secret = (string) Settings::get('brebo_integration_shared_secret', '');
    if ($baseUrl === '' || $secret === '') {
      throw new \RuntimeException('BREBO integration API configuration is incomplete.');
    }

    $body = json_encode([
      'idempotency_key' => $idempotencyKey,
      'sales_invoice' => $salesInvoice,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);

    $requestId = $this->uuidV4();
    $timestamp = (string) time();
    $bodyHash = hash('sha256', $body);
    $canonical = implode("\n", ['POST', self::PATH, $bodyHash, $timestamp, $requestId]);
    $signature = hash_hmac('sha256', $canonical, $secret);

    $response = $this->httpClient->request('POST', $baseUrl . self::PATH, [
      'headers' => [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'X-BREBO-Request-Id' => $requestId,
        'X-BREBO-Timestamp' => $timestamp,
        'X-BREBO-Signature' => 'v1=' . $signature,
      ],
      'body' => $body,
      'timeout' => 20,
      'connect_timeout' => 5,
      'http_errors' => FALSE,
    ]);

    $status = $response->getStatusCode();
    $decoded = json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
    if ($status < 200 || $status >= 300 || !is_array($decoded) || ($decoded['status'] ?? NULL) !== 'ok') {
      $code = is_array($decoded) ? (string) ($decoded['error']['code'] ?? 'integration_error') : 'integration_error';
      throw new \RuntimeException(sprintf('BREBO integration API rejected sales invoice command (%d, %s).', $status, $code));
    }
    return $decoded;
  }

  private function uuidV4(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
  }

}
