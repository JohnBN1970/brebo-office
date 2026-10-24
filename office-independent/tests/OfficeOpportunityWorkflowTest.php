<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Workflow/OfficeOpportunityWorkflow.php';

use Brebo\Office\Workflow\OfficeOpportunityWorkflow as Workflow;

function verify(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$stages = Workflow::stages();
verify(count($stages) === 6, 'Expected six opportunity stages');
verify(Workflow::nextStages('unknown') === [], 'Unknown stages must be rejected');
verify(!Workflow::canTransition('nieuw', 'opdracht', ['acceptance_confirmed']), 'Skipping stages must be blocked');
verify(!Workflow::canTransition('nieuw', 'in_behandeling', []), 'Required checks must be enforced');
verify(Workflow::canTransition('nieuw', 'in_behandeling', ['owner_assigned', 'scope_reviewed']), 'Valid transition should be accepted');
verify(!Workflow::canTransition('opdracht', 'nieuw', ['source_recorded']), 'Completed opportunity must not reset');
verify(Workflow::canTransition('offerte', 'opdracht', ['acceptance_confirmed']), 'Accepted offer may become an order');
echo "Office opportunity workflow policy: OK\n";
