<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OfficeApprovedLabourCostPolicy.php';
use Brebo\Office\Inzet\OfficeApprovedLabourCostPolicy as P;

$cases = [
    [3600, 4250, 4250],
    [21600, 4250, 25500],
    [1800, 4251, 2126],
    [1, 1800, 1],
];
foreach ($cases as [$seconds, $rate, $expected]) {
    if (P::calculate($seconds, $rate) !== $expected) {
        throw new RuntimeException('Approved labour cost valuation mismatch');
    }
}
foreach ([[0, 4250], [-1, 4250], [86401, 4250], [3600, null], [3600, 0], [3600, -1]] as [$seconds, $rate]) {
    try {
        P::calculate($seconds, $rate);
        throw new RuntimeException('Expected invalid labour valuation to be rejected');
    } catch (DomainException $expected) {}
}
try {
    P::calculate(86400, PHP_INT_MAX);
    throw new RuntimeException('Expected overflow rejection');
} catch (OverflowException $expected) {}
echo "APPROVED_LABOUR_COST_POLICY_OK\n";
