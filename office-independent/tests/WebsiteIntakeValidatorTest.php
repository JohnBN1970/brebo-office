<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteIntakeValidator;

require_once __DIR__ . '/../src/Intake/WebsiteIntakeValidator.php';

$validator = new WebsiteIntakeValidator();
$valid = [
  'request_id' => 'b94b5178-0606-4870-935d-4f1f89720773',
  'source' => 'website-europakozijn',
  'schema_version' => '1',
  'observed' => ['building' => [], 'rooms' => [['id' => 'room-1']], 'frames' => [['id' => 'frame-1']]],
  'detected' => [],
  'calculated' => [],
  'selected' => [],
];

$validator->validate($valid);
$cases = [
  'invalid_id' => array_replace($valid, ['request_id' => 'not-a-uuid']),
  'missing_source' => array_replace($valid, ['source' => '']),
  'missing_schema' => array_replace($valid, ['schema_version' => null]),
  'missing_building' => array_replace($valid, ['observed' => ['rooms' => [['id' => 'room-1']], 'frames' => [['id' => 'frame-1']]]]),
  'missing_rooms' => array_replace($valid, ['observed' => ['building' => [], 'rooms' => [], 'frames' => [['id' => 'frame-1']]]]),
  'missing_frames' => array_replace($valid, ['observed' => ['building' => [], 'rooms' => [['id' => 'room-1']], 'frames' => []]]),
];
foreach ($cases as $name => $payload) {
  try {
    $validator->validate($payload);
    throw new RuntimeException("Expected rejection: $name");
  }
  catch (InvalidArgumentException) {
    // Expected.
  }
}
echo "Website intake validation cases passed: " . (count($cases) + 1) . PHP_EOL;
