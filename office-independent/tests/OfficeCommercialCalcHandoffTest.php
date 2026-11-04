<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Demo/OfficeCommercialCalcHandoff.php';
use Brebo\Office\Demo\OfficeCommercialCalcHandoff as H;
foreach (H::POSITIONS as $position) {
    $snapshot = H::snapshot(['commercial_position' => $position, 'work_categories' => ['montage', 'glas'], 'work_scope' => 'plaatsen'], 'crm-123', 'v1');
    if ($snapshot['commercial_position'] !== $position || $snapshot['pricing_mode'] !== 'resolve_in_calculatie' || $snapshot['snapshot_policy'] !== 'immutable_until_explicit_reprice') {
        throw new RuntimeException('Incorrect CRM handoff');
    }
}
if (H::shouldPush(false) || !H::shouldPush(true)) throw new RuntimeException('Calculatie must remain optional');
foreach (['unknown', ''] as $position) {
    try { H::snapshot(['commercial_position' => $position, 'work_categories' => ['montage']], 'crm-1', 'v1'); throw new RuntimeException('Invalid position accepted'); }
    catch (InvalidArgumentException $expected) {}
}
echo "Commercial CRM-Calc handoff contract OK\n";
