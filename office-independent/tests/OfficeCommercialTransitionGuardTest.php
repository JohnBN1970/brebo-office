<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Workflow/OfficeCommercialWorkflow.php';
require_once __DIR__ . '/../src/Workflow/OfficeCommercialTransitionGuard.php';

use Brebo\Office\Workflow\OfficeCommercialTransitionGuard as Guard;

function expectDenied(callable $action): void {
    try { $action(); } catch (DomainException $expected) { return; }
    throw new RuntimeException('Expected transition denial');
}
if (Guard::authorize('intake', 'qualify', true, true, false, 'system') !== 'qualified') {
    throw new RuntimeException('Safe system transition failed');
}
if (Guard::authorize('opportunity', 'register_direct_order', true, true, true) !== 'accepted') {
    throw new RuntimeException('Direct order transition failed');
}
expectDenied(fn() => Guard::authorize('opportunity', 'register_direct_order', true, true, false));
expectDenied(fn() => Guard::authorize('quotation_sent', 'accept', true, true, true, 'system'));
expectDenied(fn() => Guard::authorize('quotation_review', 'approve', true, true, false));
expectDenied(fn() => Guard::authorize('intake', 'qualify', false, true, false));
expectDenied(fn() => Guard::authorize('intake', 'qualify', true, false, false));
expectDenied(fn() => Guard::authorize('quotation_approved', 'send', true, true, false, 'system'));
expectDenied(fn() => Guard::authorize('lost', 'approve', true, true, true));
expectDenied(fn() => Guard::authorize('intake', 'qualify', true, true, false, 'ai'));
echo "Commercial transition guard OK\n";
