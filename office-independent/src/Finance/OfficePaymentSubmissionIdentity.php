<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/**
 * Keeps the immutable batch seal separate from the exact bank wire bytes.
 * No bank I/O; use the returned wire hash for the integration API request.
 */
final class OfficePaymentSubmissionIdentity {
  public static function fromXml(int $batchId, string $sealedHash, string $xml): array {
    if ($batchId < 1 || !preg_match('/^[a-f0-9]{64}$/D', $sealedHash)) {
      throw new \DomainException('Invalid sealed batch identity');
    }
    if (strlen($xml) < 100 || strlen($xml) > 200000 || !str_contains($xml, 'urn:iso:std:iso:20022:tech:xsd:pain.001')) {
      throw new \DomainException('Invalid bank wire payload');
    }
    $wireHash = hash('sha256', $xml);
    return [
      'batch_id' => $batchId,
      'sealed_batch_sha256' => $sealedHash,
      'payload_sha256' => $wireHash,
      'idempotency_key' => hash('sha256', 'brebo-office-bank-v2:' . $batchId . ':' . $sealedHash . ':' . $wireHash),
    ];
  }
}
