<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/**
 * Authenticated POST boundary. The browser may supply only the batch ID and
 * CSRF token; payment amounts, recipients, mandate and XML stay server-side.
 * No bank network call occurs unless all checks pass.
 */
final class OfficePaymentSubmissionRequest {
  public static function handle(
    string $method,
    array $post,
    string $sessionCsrf,
    int $operatorId,
    callable $canSubmit,
    callable $isRecipientUnchanged,
    OfficePaymentCanonicalBatchRepository $canonical,
    OfficePaymentMandateRepository $mandates,
    OfficeSepaArtifactRepository $artifacts,
    OfficeBankSubmissionRepository $submissions,
    callable $resolveKey,
    \PDO $pdo,
    OfficeBankIntegrationApiClient $client
  ): array {
    if ($method !== 'POST') throw new \DomainException('Payment submission requires POST');
    if (getenv('OFFICE_BANK_PAYMENTS_ENABLED') !== 'enabled') {
      throw new \DomainException('Office bank payments disabled');
    }
    if ($operatorId < 1 || $canSubmit($operatorId) !== true) {
      throw new \DomainException('Payment submission permission required');
    }
    if ($sessionCsrf === '' || !is_string($post['csrf'] ?? null)
      || !hash_equals($sessionCsrf, $post['csrf'])) {
      throw new \DomainException('Payment submission CSRF validation failed');
    }
    if (array_diff(array_keys($post), ['csrf','batch_id']) !== []) {
      throw new \DomainException('Unexpected payment submission fields');
    }
    return OfficePaymentSubmissionApplication::submitTrustedBatch(
      $post['batch_id'] ?? null, $canonical->resolver(), $operatorId,
      $canSubmit, $isRecipientUnchanged, $mandates, $artifacts,
      $submissions, $resolveKey, $pdo, $client
    );
  }
}
