<?php

declare(strict_types=1);

foreach (['OfficeDisciplineCatalog.php', 'OfficeProcessBlueprint.php', 'OfficeIntakeProcessPlan.php'] as $file) {
    require_once dirname(__DIR__) . '/src/Intake/' . $file;
}

use Brebo\Office\Intake\OfficeIntakeProcessPlan;

$plan = OfficeIntakeProcessPlan::prepare([
    'request_id' => 'REQ-2026-1',
    'building_id' => '42',
    'disciplines' => ['montage en levering', 'expertise'],
]);
if ($plan['building_resolution'] !== 'linked' || count($plan['processes']) !== 2
    || $plan['processes'][0]['blueprint_key'] !== 'brebo.levering_montage') {
    throw new RuntimeException('Intake plan incorrect');
}
$unresolved = OfficeIntakeProcessPlan::prepare(['request_id' => 'REQ-2', 'disciplines' => ['bouw']]);
if ($unresolved['building_resolution'] !== 'required') {
    throw new RuntimeException('Missing building must require resolution');
}
try {
    OfficeIntakeProcessPlan::prepare(['request_id' => 'REQ-3', 'disciplines' => ['mjop']]);
    throw new RuntimeException('MJOP unexpectedly enabled');
} catch (InvalidArgumentException $expected) {
}
echo "OfficeIntakeProcessPlanTest OK\n";
