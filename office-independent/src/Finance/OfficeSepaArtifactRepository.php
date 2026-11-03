<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/** Private persistence boundary; caller commits before any bank I/O. */
final class OfficeSepaArtifactRepository {
  public function __construct(private readonly \PDO $pdo) {}

  public function store(int $batchId, int $operatorId, string $xml, string $key, string $keyId): array {
    if ($batchId < 1 || $operatorId < 1) throw new \DomainException('Authenticated batch owner required');
    $artifact = OfficeSepaArtifactCipher::encrypt($xml, $key, $keyId);
    $stmt = $this->pdo->prepare(
      'INSERT INTO office_sepa_batch_artifacts
       (batch_id, payload_sha256, ciphertext, encryption_nonce, encryption_key_id, created_by, created_at)
       VALUES (:batch_id, :payload_sha256, :ciphertext, :encryption_nonce, :encryption_key_id, :created_by, UTC_TIMESTAMP())'
    );
    try {
      $stmt->bindValue(':batch_id', $batchId, \PDO::PARAM_INT);
      $stmt->bindValue(':payload_sha256', $artifact['payload_sha256']);
      $stmt->bindValue(':ciphertext', $artifact['ciphertext'], \PDO::PARAM_LOB);
      $stmt->bindValue(':encryption_nonce', $artifact['encryption_nonce'], \PDO::PARAM_LOB);
      $stmt->bindValue(':encryption_key_id', $keyId);
      $stmt->bindValue(':created_by', $operatorId, \PDO::PARAM_INT);
      $stmt->execute();
    } catch (\PDOException $e) {
      throw new \DomainException('SEPA artifact already exists or storage unavailable', 0, $e);
    }
    return ['batch_id' => $batchId, 'payload_sha256' => $artifact['payload_sha256']];
  }

  /** Decryption is restricted to a caller-authorized server-side submission. */
  public function forAuthorizedSubmission(int $batchId, int $operatorId, callable $canSubmit, callable $resolveKey): array {
    if ($batchId < 1 || $operatorId < 1 || $canSubmit($operatorId, $batchId) !== true) {
      throw new \DomainException('SEPA submission authorization required');
    }
    $stmt = $this->pdo->prepare(
      'SELECT payload_sha256, ciphertext, encryption_nonce, encryption_key_id
       FROM office_sepa_batch_artifacts WHERE batch_id = :batch_id'
    );
    $stmt->execute(['batch_id' => $batchId]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (!is_array($row)) throw new \DomainException('SEPA artifact unavailable');
    $key = $resolveKey($row['encryption_key_id']);
    if (!is_string($key)) throw new \DomainException('SEPA encryption key unavailable');
    $xml = OfficeSepaArtifactCipher::decrypt($row, $key);
    return ['batch_id' => $batchId, 'payload_sha256' => $row['payload_sha256'], 'pain001_xml' => $xml];
  }
}
