<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/**
 * Declarative Office permission catalog for payment batch administration.
 * This class never authenticates an operator, releases a batch, or sends money.
 * Authorization must be enforced by the Office server against persisted grants.
 */
final class OfficeFinanceBatchPermissionCatalog {
  public const CREATE = 'finance.payment_batch.create';
  public const REVIEW = 'finance.payment_batch.review';
  public const RELEASE = 'finance.payment_batch.release';

  public static function permissions(): array {
    return [self::CREATE, self::REVIEW, self::RELEASE];
  }

  /** Defaults are proposals for role administration, not automatic grants. */
  public static function suggestedRoleGrants(): array {
    return [
      'administration' => [self::CREATE, self::REVIEW],
      'financial_manager' => [self::CREATE, self::REVIEW, self::RELEASE],
      'directie' => [self::CREATE, self::REVIEW, self::RELEASE],
      'projectleider' => [],
      'medewerker' => [],
      'digital_secretary' => [self::CREATE, self::REVIEW],
    ];
  }

  /** Fail closed: do not interpret a client-provided role label as authorization. */
  public static function hasGrant(array $serverResolvedGrants, string $permission): bool {
    return in_array($permission, self::permissions(), true)
      && in_array($permission, $serverResolvedGrants, true);
  }
}
