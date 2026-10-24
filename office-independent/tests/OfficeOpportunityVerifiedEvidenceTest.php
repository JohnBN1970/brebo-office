<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Workflow/OfficeOpportunityWorkflow.php';
require_once dirname(__DIR__) . '/src/Workflow/OfficeOpportunityStageMap.php';
require_once dirname(__DIR__) . '/src/Workflow/OfficeOpportunityTransitionGuard.php';

use Brebo\Office\Workflow\OfficeOpportunityTransitionGuard as Guard;

$assertRejected = static function (string $target, array $evidence): void {
  try {
    Guard::verifiedChecks($target, $evidence);
    throw new RuntimeException('Expected evidence rejection: ' . $target);
  } catch (DomainException) {
    // Missing or untrusted evidence must fail closed.
  }
};

$assertRejected('in_behandeling', []);
$assertRejected('in_behandeling', ['owner_assigned' => true]);
$assertRejected('in_behandeling', ['owner_assigned' => 'yes', 'scope_reviewed' => true]);
$assertRejected('unknown', ['owner_assigned' => true]);
$checks = Guard::verifiedChecks('in_behandeling', [
  'owner_assigned' => true,
  'scope_reviewed' => true,
  'unrelated' => true,
]);
if ($checks !== ['owner_assigned', 'scope_reviewed']) {
  throw new RuntimeException('Verified evidence mismatch');
}
$assertRejected('nieuw', ['source_recorded' => false]);
if (Guard::verifiedChecks('nieuw', ['source_recorded' => true]) !== ['source_recorded']) {
  throw new RuntimeException('Persisted source evidence mismatch');
}
echo "CRM verified evidence gate: OK\n";
