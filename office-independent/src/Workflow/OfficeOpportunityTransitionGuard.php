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

  /** @return list<string> */
  public static function requiredChecks(string $to): array {
    return OfficeOpportunityWorkflow::stages()[$to]['checks'] ?? [];
  }
}
