<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/** Workbook access is separate from project, CRM and device permissions. */
final class OfficeInzetReviewAccess {
  public static function allows(string $operatorId, mixed $configuredReviewer): bool {
    return is_string($configuredReviewer)
      && $configuredReviewer !== '' && strlen($configuredReviewer) <= 128
      && hash_equals($configuredReviewer, $operatorId);
  }

  /** Bind the review form to the exact authenticated Office login session. */
  public static function sessionBinding(string $operatorId, string $sessionId): string {
    return hash('sha256', json_encode([$operatorId, $sessionId], JSON_THROW_ON_ERROR));
  }
}
