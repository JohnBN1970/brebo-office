<?php

declare(strict_types=1);

namespace Drupal\brebo_office_core\Service;

/**
 * Ondertekent Integration API-verzoeken volgens BREBO HMAC v1.
 */
final class IntegrationApiRequestSigner {

  /**
   * @return array<string, string>
   *   De authenticatieheaders, zonder het gedeelde secret prijs te geven.
   */
  public function sign(
    string $method,
    string $path,
    string $body,
    string $sharedSecret,
    ?int $timestamp = NULL,
    ?string $requestId = NULL,
  ): array {
    $timestamp ??= time();
    $requestId ??= $this->generateUuidV4();
    $timestampText = (string) $timestamp;
    $canonical = implode("\n", [
      strtoupper($method),
      $path,
      hash('sha256', $body),
      $timestampText,
      $requestId,
    ]);

    return [
      'X-BREBO-Timestamp' => $timestampText,
      'X-BREBO-Request-Id' => $requestId,
      'X-BREBO-Signature' => 'v1=' . hash_hmac('sha256', $canonical, $sharedSecret),
    ];
  }

  private function generateUuidV4(): string {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);

    return sprintf(
      '%s-%s-%s-%s-%s',
      substr($hex, 0, 8),
      substr($hex, 8, 4),
      substr($hex, 12, 4),
      substr($hex, 16, 4),
      substr($hex, 20, 12),
    );
  }

}
