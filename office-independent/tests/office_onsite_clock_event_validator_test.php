<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteClockEventValidator.php';

use Brebo\Office\Inzet\OnSiteClockEventValidator;

$validator = new OnSiteClockEventValidator();
$valid = ['event_id' => 'onsite_test_00000001', 'project_id' => 1, 'person_id' => 2,
  'action' => 'in', 'occurred_at' => '2026-10-16T07:00:00+02:00',
  'latitude' => 51.4, 'longitude' => 4.5, 'accuracy_m' => 12.0];
$normalized = $validator->validate($valid);
if ($normalized['project_id'] !== 1 || $normalized['action'] !== 'in') throw new RuntimeException('Valid event rejected.');
$invalidCases = [
  ['event_id' => 'short'],
  ['action' => 'delete'],
  ['occurred_at' => '2026-10-16T07:00:00'],
  ['project_id' => 0],
  ['latitude' => 51.4, 'longitude' => null],
  ['accuracy_m' => -1],
];
foreach ($invalidCases as $case) {
  try {
    $validator->validate(array_replace($valid, $case));
    throw new RuntimeException('Invalid event accepted: ' . implode(',', array_keys($case)));
  } catch (InvalidArgumentException $expected) {
    // Expected rejection.
  }
}
echo "OnSite clock event validator OK\n";
