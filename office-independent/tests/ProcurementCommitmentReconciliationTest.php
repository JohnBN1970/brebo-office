<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/WorkingBudget/ProcurementCommitmentReconciliation.php';
use Brebo\Office\WorkingBudget\ProcurementCommitmentReconciliation as Reconcile;

$result = Reconcile::reconcile([
    ['order_id' => 'A', 'awarded_cents' => 10000, 'recognized_actual_cents' => 4000],
    ['order_id' => 'B', 'awarded_cents' => 5000, 'recognized_actual_cents' => 7000],
]);
if ($result['awarded_cents'] !== 15000
    || $result['recognized_actual_cents'] !== 11000
    || $result['open_commitment_cents'] !== 6000
    || $result['lines'][1]['overrun_cents'] !== 2000) {
    throw new RuntimeException('Incorrect reconciliation or double-counting.');
}
foreach ([
    [['order_id' => 'A', 'awarded_cents' => 1, 'recognized_actual_cents' => 0], ['order_id' => 'A', 'awarded_cents' => 1, 'recognized_actual_cents' => 0]],
    [['order_id' => 'A', 'awarded_cents' => -1, 'recognized_actual_cents' => 0]],
    [['order_id' => 'A', 'awarded_cents' => 1.5, 'recognized_actual_cents' => 0]],
] as $invalid) {
    $failed = false;
    try { Reconcile::reconcile($invalid); } catch (InvalidArgumentException $e) { $failed = true; }
    if (!$failed) throw new RuntimeException('Invalid procurement data accepted.');
}
echo "PROCUREMENT_COMMITMENT_RECONCILIATION_TEST=PASS\n";
