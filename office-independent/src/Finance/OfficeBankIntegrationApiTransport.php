<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/** HMAC transport matching integration-api/src/index.ts authenticate(). */
final class OfficeBankIntegrationApiTransport {
  public static function post(string $url, string $path, string $body, string $clientId, string $secret): array {
    if (!function_exists('curl_init') || !str_starts_with($url, 'https://')
      || parse_url($url, PHP_URL_PATH) !== $path || $secret === '') {
      throw new \DomainException('Secure bank transport unavailable');
    }
    $requestId = self::uuid();
    $timestamp = (string)time();
    $canonical = implode("\n", ['POST', $path, hash('sha256', $body), $timestamp, $requestId]);
    $signature = hash_hmac('sha256', $canonical, $secret);
    $handle = curl_init($url);
    if ($handle === false) throw new \RuntimeException('Bank transport initialization failed');
    try {
      curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
          'Content-Type: application/json',
          'X-BREBO-Timestamp: ' . $timestamp,
          'X-BREBO-Request-Id: ' . $requestId,
          'X-BREBO-Signature: v1=' . $signature,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
      ]);
      $raw = curl_exec($handle);
      $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
      if (!is_string($raw) || $status !== 200) {
        throw new \RuntimeException('Bank integration outcome unverified; no automatic retry');
      }
      $response = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
      if (!is_array($response)) throw new \RuntimeException('Invalid bank integration response');
      return $response;
    } finally {
      curl_close($handle);
    }
  }

  private static function uuid(): string {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
  }
}
