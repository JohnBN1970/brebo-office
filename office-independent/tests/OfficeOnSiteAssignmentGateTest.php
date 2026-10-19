<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OnSiteAssignmentRepository.php';
require_once dirname(__DIR__) . '/src/Inzet/OnSiteAssignmentGate.php';

use Brebo\Office\Inzet\OnSiteAssignmentRepository;
use Brebo\Office\Inzet\OnSiteAssignmentGate;

$repository = new class implements OnSiteAssignmentRepository {
  public function isAssigned(string $employee, string $project, string $zone): bool {
    return $employee === 'verified-7' && $project === '12' && $zone === '3';
  }
};
$gate = new OnSiteAssignmentGate($repository);
$event = ['project_id'=>'12', 'zone_id'=>'3', 'kind'=>'in'];
if (!$gate->authorize('verified-7', $event)) throw new RuntimeException('Valid assignment rejected');
if ($gate->authorize('other', $event)) throw new RuntimeException('Unassigned employee accepted');
if ($gate->authorize('verified-7', array_replace($event, ['zone_id'=>'4']))) {
  throw new RuntimeException('Unassigned zone accepted');
}
if ($gate->authorize('', $event)) throw new RuntimeException('Empty identity accepted');
try {
  $gate->authorize('verified-7', array_replace($event, ['employee_id'=>'verified-7']));
  throw new RuntimeException('Client identity accepted');
} catch (InvalidArgumentException) {
  // Expected.
}
echo "OnSite assignment gate checks passed\n";
