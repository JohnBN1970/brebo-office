<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Project/OfficeProjectKickstartPolicy.php';
use Brebo\Office\Project\OfficeProjectKickstartPolicy as P;

function assertResult(bool $actual, bool $expected, string $label): void {
    if ($actual !== $expected) { throw new RuntimeException($label); }
}
$items = array_fill_keys(P::REQUIRED_ITEMS, true);
$dossier = [
    'status' => 'awaiting_second_approval', 'revision' => 3,
    'items' => $items, 'critical_open_points' => 0,
    'first_approver_role' => 'projectleider',
];
$first = ['decision' => 'approved', 'approver_id' => 17, 'dossier_revision' => 3];
assertResult(P::evaluateSecondApproval($dossier, $first, 18, true)['allowed'], true, 'two distinct authorized people');
assertResult(P::evaluateSecondApproval($dossier, $first, 17, true)['allowed'], false, 'self approval');
assertResult(P::evaluateSecondApproval($dossier, $first, 18, false)['allowed'], false, 'unauthorized reviewer');
assertResult(P::evaluateSecondApproval($dossier, null, 18, true)['allowed'], false, 'missing first approval');
$changed = $dossier; $changed['revision'] = 4;
assertResult(P::evaluateSecondApproval($changed, $first, 18, true)['allowed'], false, 'stale approval');
$blocked = $dossier; $blocked['critical_open_points'] = 1;
assertResult(P::evaluateSecondApproval($blocked, $first, 18, true)['allowed'], false, 'critical open points');
$missing = $dossier; $missing['items']['werkinstructie'] = false;
assertResult(P::evaluateSecondApproval($missing, $first, 18, true)['allowed'], false, 'missing instruction');
$invalid = $dossier; $invalid['status'] = 'draft';
assertResult(P::evaluateSecondApproval($invalid, $first, 18, true)['allowed'], false, 'wrong state');
assertResult(P::needsNewApproval('veiligheid'), true, 'safety changes');
assertResult(P::needsNewApproval('opmerking'), false, 'minor comment');
echo "KICKSTART_FOUR_EYES_POLICY_TESTS_OK\n";
