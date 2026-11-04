<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/**
 * Office-only payment entrypoint. No network call occurs unless all independent
 * authorization and reservation checks pass. Disabled unless explicitly enabled.
 */
final class OfficePaymentSubmissionApplication {
  /** Resolve operator-specific authority from the database, never from a browser form. */
  public static function submitWithOperatorMandate(
    array $sealedPayload,
    int $operatorId,
    callable $canSubmit,
    callable $isRecipientUnchanged,
    OfficePaymentMandateRepository $mandates,
    OfficeSepaArtifactRepository $artifacts,
    OfficeBankSubmissionRepository $submissions,
    callable $resolveKey,
    \PDO $pdo,
    OfficeBankIntegrationApiClient $client
  ): array {
    if (getenv('OFFICE_BANK_PAYMENTS_ENABLED') !== 'enabled') {
      throw new \DomainException('Office bank payments disabled');
    }
    if ($operatorId < 1 || $canSubmit($operatorId) !== true) {
      throw new \DomainException('Payment submission permission required');
    }
    $mandate = $mandates->forOperator($operatorId);
    return self::submit($sealedPayload, $operatorId, $canSubmit, $isRecipientUnchanged,
      $mandate, $artifacts, $submissions, $resolveKey, $pdo, $client, true);
  }

  public static function submit(
    array $sealedPayload,
    int $operatorId,
    callable $canSubmit,
    callable $isRecipientUnchanged,
    array $mandate,
    OfficeSepaArtifactRepository $artifacts,
    OfficeBankSubmissionRepository $submissions,
    callable $resolveKey,
    \PDO $pdo,
    OfficeBankIntegrationApiClient $client,
    bool $enabled = false
  ): array {
    if (!$enabled) throw new \DomainException('Office bank payments disabled');
    if ($pdo->inTransaction()) throw new \DomainException('Payment preparation requires committed database state');
    $pdo->beginTransaction();
    try {
      $prepared = OfficePaymentSubmissionPreparation::prepare(
        $sealedPayload, $operatorId, $canSubmit, $isRecipientUnchanged,
        $mandate, $artifacts, $submissions, $resolveKey
      );
      $pdo->commit();
    } catch (\Throwable $error) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      throw $error;
    }
    // The dispatch method atomically claims the committed reservation.
    return OfficePaymentSubmissionDispatch::sendViaIntegrationApi(
      $prepared, $pdo, $submissions, $client
    );
  }
}
