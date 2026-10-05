<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Brebo\Mail\Contract\GatewayHttpClientInterface;
use Brebo\Mail\Domain\GatewayRequestSignature;
use Drupal\Core\Http\ClientFactory;
use RuntimeException;

final class CoreGatewayHttpClientAdapter implements GatewayHttpClientInterface {

  public function __construct(private readonly ClientFactory $httpClientFactory) {}

  public function post(string $path, array $payload): array {
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    return $this->request('POST', $path, $body);
  }

  public function get(string $path): array {
    return $this->request('GET', $path, '');
  }

  /** @return array<string,mixed> */
  private function request(string $method, string $path, string $body): array {
    $baseUrl = rtrim(trim((string) getenv('BREBO_MAIL_GATEWAY_URL')), '/');
    $keyId = trim((string) getenv('BREBO_MAIL_GATEWAY_KEY_ID'));
    $secret = (string) getenv('BREBO_MAIL_GATEWAY_SECRET');

    if ($baseUrl === '' || $keyId === '' || $secret === '') {
      throw new RuntimeException('BREBO Mail Gateway runtimeconfiguratie is niet compleet.');
    }
    if (!str_starts_with($baseUrl, 'https://')) {
      throw new RuntimeException('BREBO Mail Gateway vereist HTTPS.');
    }

    $timestamp = time();
    $signature = GatewayRequestSignature::sign($keyId, $secret, $timestamp, $method, $path, $body);
    $headers = $signature->headers() + [
      'Accept' => 'application/json',
      'Content-Type' => 'application/json',
    ];

    $client = $this->httpClientFactory->fromOptions([
      'base_uri' => $baseUrl,
      'timeout' => 10,
      'connect_timeout' => 5,
      'http_errors' => FALSE,
    ]);

    $response = $client->request($method, $path, [
      'headers' => $headers,
      'body' => $body !== '' ? $body : NULL,
    ]);

    $status = $response->getStatusCode();
    $decoded = json_decode((string) $response->getBody(), TRUE);
    if (!is_array($decoded)) {
      $decoded = [];
    }

    if ($status < 200 || $status >= 300) {
      throw new RuntimeException('BREBO Mail Gateway antwoordde met HTTP ' . $status . '.');
    }

    return $decoded;
  }
}
