<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Demo/OfficeCommercialCalcHandoff.php';
require_once __DIR__ . '/../src/Demo/OfficeCommercialMarginProfile.php';
use Brebo\Office\Demo\OfficeCommercialCalcHandoff as H;
use Brebo\Office\Demo\OfficeCommercialMarginProfile as M;
$profiles = [];
foreach (H::POSITIONS as $position) {
    $profiles[$position] = ['version' => 'v1', 'default' => ['material_markup_pct' => 12, 'labor_markup_pct' => 20],
        'categories' => ['montage' => ['material_markup_pct' => 5, 'labor_markup_pct' => 25]]];
    $snapshot = H::snapshot(['commercial_position' => $position, 'work_categories' => ['montage', 'glas']], 'crm-1', 'v1');
    $resolved = M::resolve($snapshot, $profiles, ['glas' => ['material_markup_pct' => 15]]);
    if ($resolved['categories']['montage']['labor_markup_pct'] !== 25.0
        || $resolved['categories']['glas']['material_markup_pct'] !== 15.0
        || $resolved['categories']['glas']['labor_markup_pct'] !== 20.0) throw new RuntimeException('Incorrect margin profile');
}
try {
    $snapshot['profile_version'] = 'v2';
    M::resolve($snapshot, $profiles);
    throw new RuntimeException('Profile mismatch accepted');
} catch (InvalidArgumentException $expected) {}
echo "Commercial margin profile contract OK\n";
