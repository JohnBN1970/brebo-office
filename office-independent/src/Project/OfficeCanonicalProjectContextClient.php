<?php

declare(strict_types=1);

namespace Brebo\Office\Project;

use RuntimeException;

/** Server-to-server client. Caller must authorize operator before calling. */
final class OfficeCanonicalProjectContextClient {
  public function __construct(private readonly string $baseUrl, private readonly string $sharedSecret) {
    if (!preg_match('~^https://[a-z0-9.-]+(?::443)?$~iD', $baseUrl) || strlen($sharedSecret) < 32) {
      throw new RuntimeException('Canonical project endpoint is not securely configured.');
    }
  }

  /** Matches the existing Drupal CalculationWorkbenchContextController signature. */
  public static function signGet(string $path, string $timestamp, string $requestId, string $secret): string {
    if (!preg_match('~^/api/workbench/v1/projects/[1-9][0-9]*/calculation-context$~D', $path)
      || !ctype_digit($timestamp)
      || !preg_match('/^[0-9a-fA-F-]{36}$/D', $requestId)
      || strlen($secret) < 32) {
      throw new RuntimeException('Invalid canonical signing input.');
    }
    $canonical = "GET\n{$path}\n" . hash('sha256', '') . "\n{$timestamp}\n{$requestId}";
    return 'v1=' . hash_hmac('sha256', $canonical, $secret);
  }

  public function get(int $projectId): OfficeCanonicalProjectContext {
    if ($projectId < 1) {
      throw new RuntimeException('Invalid project ID.');
    }
    $path = '/api/workbench/v1/projects/' . $projectId . '/calculation-context';
    $timestamp = (string) time();
    $requestId = sprintf('%s-%s-%s-%s-%s', bin2hex(random_bytes(4)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(2)), bin2hex(random_bytes(6)));
    $signature = self::signGet($path, $timestamp, $requestId, $this->sharedSecret);
    $context = stream_context_create(['http' => [
      'method' => 'GET', 'timeout' => 5, 'ignore_errors' => true,
      'follow_location' => 0,
      'header' => "Accept: application/json\r\nX-BREBO-Timestamp: {$timestamp}\r\nX-BREBO-Request-Id: {$requestId}\r\nX-BREBO-Signature: {$signature}\r\n",
    ]]);
    $body = @file_get_contents($this->baseUrl . $path, false, $context);
    $headers = $http_response_header ?? [];
    if (!is_string($body) || strlen($body) > 65536 || !isset($headers[0]) || !preg_match('~^HTTP/\S+ 200(?:\s|$)~', $headers[0])) {
      throw new RuntimeException('Canonical project context unavailable.');
    }
    $payload = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) {
      throw new RuntimeException('Invalid canonical project context.');
    }
    return OfficeCanonicalProjectContext::fromPayload($payload, $projectId);
  }
}
