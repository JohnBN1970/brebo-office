<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/**
 * Prepare a bank handoff without network I/O.
 * The caller must commit the durable reservation before calling the bank API.
 */
final class OfficePaymentSubmissionPreparation {
  public static function prepare(
    array $sealedPayload,
    int $operatorId,
    callable $canSubmit,
    callable $isRecipientUnchanged,
    array $mandate,
    OfficeSepaArtifactRepository $artifacts,
    OfficeBankSubmissionRepository $submissions,
    callable $resolveKey
  ): array {
    $batch = $sealedPayload['batch'] ?? null;
    if (!is_array($batch) || !isset($batch['id']) || (int)$batch['id'] < 1) {
      throw new \DomainException('Invalid payment batch');
    }
    // The repository enforces independent access before releasing plaintext.
    $artifact = $artifacts->forAuthorizedSubmission(
      (int)$batch['id'],
      $operatorId,
      static fn(int $actor, int $batchId): bool => $canSubmit($actor) === true,
      $resolveKey
    );
    $identity = OfficePaymentBatchSubmissionGuard::authorize(
      $sealedPayload, $operatorId, $canSubmit, $isRecipientUnchanged,
      $mandate, $artifact['pain001_xml']
    );
    if (!hash_equals($artifact['payload_sha256'], $identity['payload_sha256'])) {
      throw new \DomainException('Stored SEPA artifact differs from bank payload');
    }
    // Unique DB constraints block repeated attempts, including unknown outcomes.
    $submissions->reserve($identity, $operatorId);
    return [
      'batch_id' => $identity['batch_id'],
      'payload_sha256' => $identity['payload_sha256'],
      'idempotency_key' => $identity['idempotency_key'],
      'pain001_xml' => $artifact['pain001_xml'],
    ];
  }
}
