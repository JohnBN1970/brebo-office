<?php
declare(strict_types=1);

namespace Brebo\Office\Workflow;

use DomainException;

/**
 * Validates proposed CRM stage changes before any database write.
 * Legacy stages are resolved without changing the stored value.
 * This class performs no writes and grants no authorization.
 */
final class OfficeOpportunityTransitionGuard {
  /** @param list<string> $completedChecks */
  public static function requireAllowed(string $storedFrom, string $to, array $completedChecks): void {
    $from = OfficeOpportunityStageMap::workflowStage($storedFrom);
    if ($from === null || !isset(OfficeOpportunityWorkflow::stages()[$to])) {
      throw new DomainException('Onbekende CRM-workflowstatus');
    }
    if (!OfficeOpportunityWorkflow::canTransition($from, $to, $completedChecks)) {
      throw new DomainException('Statusovergang niet toegestaan of controles ontbreken');
    }
  }

  /**
   * Only accept evidence verified by trusted Office server-side repositories.
   * Never pass user-submitted checkboxes as verified evidence.
   *
   * @param array<string,bool> $verifiedEvidence
   * @return list<string>
   */
  public static function verifiedChecks(string $targetStage, array $verifiedEvidence): array {
    $required = self::requiredChecks($targetStage);
    if ($required === []) {
      throw new DomainException('Geen verifieerbare statusvoorwaarden geconfigureerd');
    }
    foreach ($required as $check) {
      if (($verifiedEvidence[$check] ?? false) !== true) {
        throw new DomainException('Vereiste CRM-controle niet bevestigd');
      }
    }
    return $required;
  }

  /** @return list<string> */
  public static function requiredChecks(string $to): array {
    return OfficeOpportunityWorkflow::stages()[$to]['checks'] ?? [];
  }
}
