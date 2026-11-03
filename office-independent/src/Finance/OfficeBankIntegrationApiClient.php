<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/**
 * Server-side signed transport for the disabled-by-default Integration API.
 * No browser exposure of SEPA XML or signing credentials.
 */
final class OfficeBankIntegrationApiClient {
  public function __construct(
    private readonly string $baseUrl,
    private readonly string $signingSecret,
    private readonly string $clientId,
    private readonly \Closure $transport
  ) {}

  public function submit(array $payment): array {
    foreach (['pain001_xml', 'payload_sha256', 'idempotency_key'] as $field) {
      if (!is_string($payment[$field] ?? null) || $payment[$field] === '') {
        throw new \DomainException('Invalid payment transport payload');
      }
    }
    if (!hash_equals(hash('sha256', $payment['pain001_xml']), $payment['payload_sha256'])) {
      throw new \DomainException('Payment XML checksum mismatch');
    }
    if (!preg_match('/^[a-f0-9]{64}$/D', $payment['idempotency_key'])) {
      throw new \DomainException('Invalid payment idempotency key');
    }
    if (!str_starts_with($this->baseUrl, 'https://') || $this->signingSecret === '' || $this->clientId === '') {
      throw new \DomainException('Secure bank integration configuration required');
    }
    $path = '/v1/banking/payments/submit';
    $body = json_encode($payment, JSON_THROW_ON_ERROR);
    $response = ($this->transport)($this->baseUrl . $path, $path, $body, $this->clientId, $this->signingSecret);
    if (!is_array($response) || ($response['status'] ?? null) !== 'ok'
      || ($response['provider'] ?? null) !== 'abnamro'
      || ($response['state'] ?? null) !== 'submitted_for_authorization'
      || !is_string($response['bank_reference'] ?? null)
      || trim($response['bank_reference']) === '') {
      throw new \RuntimeException('Unverified bank integration response');
    }
    return $response;
  }
}
