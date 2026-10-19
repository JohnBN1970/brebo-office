<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetPersonnelProfileRepository.php';

use Brebo\Office\Inzet\OfficeInzetPersonnelProfileRepository;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  echo "SQLite unavailable; profile validation test skipped\n";
  exit(0);
}
$repo = new OfficeInzetPersonnelProfileRepository(new PDO('sqlite::memory:'));
foreach ([
  ['', 1, 1, 'BR-01'],
  ['operator', 0, 1, 'BR-01'],
  ['operator', 1, 0, 'BR-01'],
  ['operator', 1, 1, ''],
  ['operator', 1, 1, '<script>'],
  ['operator', 1, 1, str_repeat('A', 41)],
] as [$operator, $project, $person, $number]) {
  try {
    $repo->updatePersonnelNumber($operator, $project, $person, $number);
    throw new RuntimeException('Invalid personnel profile update accepted');
  } catch (InvalidArgumentException $expected) {
    // Rejected before database access.
  }
}
echo "Office personnel profile input validation OK\n";
