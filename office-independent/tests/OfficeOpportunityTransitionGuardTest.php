<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Workflow/OfficeOpportunityWorkflow.php';
require_once dirname(__DIR__) . '/src/Workflow/OfficeOpportunityStageMap.php';
require_once dirname(__DIR__) . '/src/Workflow/OfficeOpportunityTransitionGuard.php';

use Brebo\Office\Workflow\OfficeOpportunityTransitionGuard as Guard;

$assertDenied = static function (string $from, string $to, array $checks): void {
  try {
    Guard::requireAllowed($from, $to, $checks);
  } catch (DomainException) {
    return;
  }
  throw new RuntimeException("Unexpected transition allowed: $from -> $to");
};

Guard::requireAllowed('prospect', 'in_behandeling', ['owner_assigned', 'scope_reviewed']);
$assertDenied('prospect', 'in_behandeling', []);
$assertDenied('prospect', 'opdracht', ['acceptance_confirmed']);
$assertDenied('unknown', 'in_behandeling', ['owner_assigned', 'scope_reviewed']);
$assertDenied('nieuw', 'unknown', []);
$assertDenied('nieuw', 'nieuw', ['source_recorded']);
if (Guard::requiredChecks('offerte') !== ['offer_recorded']) {
  throw new RuntimeException('Required checks mismatch');
}
echo "Opportunity transition guard: OK\n";
