<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Inzet/OperationalInzet.php';
require_once __DIR__ . '/../src/Inzet/OperationalInzetStore.php';

use Brebo\Office\Inzet\OperationalInzet;
use Brebo\Office\Inzet\OperationalInzetStore;

if (!extension_loaded('pdo_sqlite')) {
  fwrite(STDERR, "pdo_sqlite required\n");
  exit(1);
}

$db = new PDO('sqlite::memory:');
$store = new OperationalInzetStore($db);
$store->migrate();

$store->addPlanning('plan-101', 'project-101', 'worker-101', '2026-10-18', 480, 'planner-101');
$store->submit('reg-101', 'plan-101', 420, 'Kozijnen monteren', 'worker-101');

$query = $db->prepare('SELECT p.project_id, p.person_id, p.work_date, p.planned_minutes, r.registered_minutes, r.state, r.revision
  FROM inzet_registration r JOIN inzet_planning p ON p.id = r.planning_id WHERE r.id = ?');
$query->execute(['reg-101']);
$row = $query->fetch(PDO::FETCH_ASSOC);
if (!is_array($row)) throw new RuntimeException('Registration missing');
$plan = ['project_id'=>$row['project_id'], 'person_id'=>$row['person_id'], 'work_date'=>$row['work_date'], 'planned_minutes'=>(int)$row['planned_minutes']];
$actual = ['project_id'=>$row['project_id'], 'person_id'=>$row['person_id'], 'work_date'=>$row['work_date'], 'registered_minutes'=>(int)$row['registered_minutes']];
$comparison = OperationalInzet::compare($plan, $actual);
if ($comparison['variance_minutes'] !== -60 || $comparison['issues'] !== ['variance']) {
  throw new RuntimeException('Variance not detected correctly');
}
if ($row['state'] !== 'submitted') throw new RuntimeException('Unexpected initial state');
$store->decide('reg-101', (int)$row['revision'], 'approved', 'supervisor-101', 'Afwijking gecontroleerd');
$query->execute(['reg-101']);
$approved = $query->fetch(PDO::FETCH_ASSOC);
if (!is_array($approved) || $approved['state'] !== 'approved' || (int)$approved['revision'] !== 2) {
  throw new RuntimeException('Approval not persisted');
}
$audits = (int)$db->query('SELECT COUNT(*) FROM inzet_operational_audit')->fetchColumn();
if ($audits !== 3) throw new RuntimeException('Audit events missing');
echo "OperationalInzetFlowTest OK\n";
