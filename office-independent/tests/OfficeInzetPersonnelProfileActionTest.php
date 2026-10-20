<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetPersonnelProfileCsrf.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetPersonnelProfileRepository.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetPersonnelProfileAction.php';
use Brebo\Office\Inzet\OfficeInzetPersonnelProfileAction;
use Brebo\Office\Inzet\OfficeInzetPersonnelProfileCsrf;
use Brebo\Office\Inzet\OfficeInzetPersonnelProfileRepository;
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  echo "SQLite unavailable; profile action test skipped\n";
  exit(0);
}
$db = new PDO('sqlite::memory:');
$action = new OfficeInzetPersonnelProfileAction(new OfficeInzetPersonnelProfileRepository($db));
$session = str_repeat('a',64);
$secret = str_repeat('b',48);
$valid = ['csrf_token'=>OfficeInzetPersonnelProfileCsrf::token($session,$secret),'project_id'=>'1','person_id'=>'2','personnel_number'=>'BR-002'];
foreach ([
  array_merge($valid,['csrf_token'=>'wrong']),
  array_merge($valid,['project_id'=>'0']),
  array_merge($valid,['person_id'=>['2']]),
  array_merge($valid,['personnel_number'=>['BR-002']]),
] as $invalid) {
  try {
    $action->update($invalid,'verified-operator',$session,$secret);
    throw new RuntimeException('Unverified personnel action accepted');
  } catch (DomainException|InvalidArgumentException $expected) {}
}
echo "Personnel profile action request validation OK\n";
