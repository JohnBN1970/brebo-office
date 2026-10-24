<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Inzet/OperationalInzet.php';

use Brebo\Office\Inzet\OperationalInzet;

$plan = ['project_id'=>'P1','person_id'=>'M1','work_date'=>'2026-10-18','planned_minutes'=>480];
$actual = ['project_id'=>'P1','person_id'=>'M1','work_date'=>'2026-10-18','registered_minutes'=>420];
$result = OperationalInzet::compare($plan, $actual);
assert($result['variance_minutes'] === -60);
if ($result['variance_minutes'] !== -60 || $result['issues'] !== ['variance']) {
  throw new RuntimeException('Expected one-hour shortfall');
}
$actual['project_id'] = 'P2';
$result = OperationalInzet::compare($plan, $actual);
if (!in_array('mismatch_project_id', $result['issues'], true)) {
  throw new RuntimeException('Project mismatch not detected');
}
$actual['registered_minutes'] = -5;
$result = OperationalInzet::compare($plan, $actual);
if (!in_array('invalid_registered_minutes', $result['issues'], true)) {
  throw new RuntimeException('Invalid duration not detected');
}
echo "OperationalInzetTest OK\n";
