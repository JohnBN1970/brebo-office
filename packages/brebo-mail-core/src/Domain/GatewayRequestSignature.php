<?php

declare(strict_types=1);

namespace Brebo\Mail\Domain;

use InvalidArgumentException;

final readonly class GatewayRequestSignature {

  public function __construct(
    public string $keyId,
    public string $signature,
    public int $timestamp,
  ) {
    if (trim($keyId) === '' || trim($signature) === '' || $timestamp <= 0) {
      throw new InvalidArgumentException('Ongeldige gateway request signature.');
    }
  }

  public static function sign(string $keyId, string $secret, int $timestamp, string $method, string $path, string $body): self {
    if (trim($secret) === '') {
      throw new InvalidArgumentException('Gateway signing secret ontbreekt.');
    }
    $canonical = strtoupper(trim($method)) . "\n" . $path . "\n" . $timestamp . "\n" . hash('sha256', $body);
    return new self($keyId, hash_hmac('sha256', $canonical, $secret), $timestamp);
  }

  /** @return array<string,string> */
  public function headers(): array {
    return [
      'X-Brebo-Key-Id' => $this->keyId,
      'X-Brebo-Timestamp' => (string) $this->timestamp,
      'X-Brebo-Signature' => $this->signature,
    ];
  }
}
