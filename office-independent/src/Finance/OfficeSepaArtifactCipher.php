<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/** Encrypt exact internal SEPA bytes; never expose plaintext through web routes. */
final class OfficeSepaArtifactCipher {
  public static function encrypt(string $xml, string $key, string $keyId): array {
    if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES || $keyId === '' || strlen($keyId) > 120) {
      throw new \DomainException('SEPA encryption configuration unavailable');
    }
    if (strlen($xml) < 100 || strlen($xml) > 200000
      || !str_contains($xml, 'urn:iso:std:iso:20022:tech:xsd:pain.001')) {
      throw new \DomainException('Invalid SEPA artifact');
    }
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return [
      'payload_sha256' => hash('sha256', $xml),
      'ciphertext' => sodium_crypto_secretbox($xml, $nonce, $key),
      'encryption_nonce' => $nonce,
      'encryption_key_id' => $keyId,
    ];
  }

  public static function decrypt(array $artifact, string $key): string {
    if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES
      || !is_string($artifact['ciphertext'] ?? null)
      || !is_string($artifact['encryption_nonce'] ?? null)
      || strlen($artifact['encryption_nonce']) !== SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
      || !is_string($artifact['payload_sha256'] ?? null)) {
      throw new \DomainException('Invalid encrypted SEPA artifact');
    }
    $plain = sodium_crypto_secretbox_open($artifact['ciphertext'], $artifact['encryption_nonce'], $key);
    if ($plain === false || !hash_equals($artifact['payload_sha256'], hash('sha256', $plain))) {
      throw new \DomainException('SEPA artifact integrity verification failed');
    }
    return $plain;
  }
}
