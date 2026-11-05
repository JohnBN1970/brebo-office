<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/** Server-side authenticated bank-only API client. No invoice imports. */
final class OfficeMoneybirdBankApiClient {
  public static function fetch(): array {
    $endpoint = trim((string)(getenv('OFFICE_MONEYBIRD_BANK_API_URL') ?: ''));
    $secret = (string)(getenv('OFFICE_INTEGRATION_SHARED_SECRET') ?: '');
    if ($endpoint !== 'https://brebo-integration-api.john-boon.workers.dev/v1/accounting/bank-data'
        || strlen($secret) < 24 || !function_exists('curl_init')) {
      throw new \RuntimeException('Bank integration not configured');
    }
    $path = '/v1/accounting/bank-data';
    $timestamp = (string)time();
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    $id = substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
    $canonical = implode("\n", ['GET',$path,hash('sha256',''),$timestamp,$id]);
    $signature = hash_hmac('sha256', $canonical, $secret);
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_TIMEOUT=>12,
      CURLOPT_FOLLOWLOCATION=>false, CURLOPT_MAXREDIRS=>0,
      CURLOPT_HTTPHEADER=>[
        'Accept: application/json','X-BREBO-Request-Id: '.$id,
        'X-BREBO-Timestamp: '.$timestamp,'X-BREBO-Signature: v1='.$signature,
      ],
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($status !== 200 || !is_string($body) || strlen($body) > 2097152) {
      throw new \RuntimeException('Bank integration unavailable');
    }
    $payload = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) throw new \RuntimeException('Invalid bank response');
    return OfficeMoneybirdBankSnapshot::fromIntegrationApi($payload);
  }
}
