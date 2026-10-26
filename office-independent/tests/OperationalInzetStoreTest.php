<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Inzet/OperationalInzetStore.php';

use Brebo\Office\Inzet\OperationalInzetStore;

if (!extension_loaded('pdo_sqlite')) {
  fwrite(STDERR, "pdo_sqlite required\n");
  exit(1);
}
$db = new PDO('sqlite::memory:');
$store = new OperationalInzetStore($db);
$store->migrate();
$store->addPlanning('plan-1', 'project-1', 'person-1', '2026-10-18', 480, 'planner-1');
$store->submit('registration-1', 'plan-1', 420, 'Gevelwerk', 'person-1');
$store->decide('registration-1', 1, 'approved', 'reviewer-1', 'Werk gecontroleerd');
$state = $db->query("SELECT state, revision FROM inzet_registration WHERE id='registration-1'")->fetch(PDO::FETCH_ASSOC);
if ($state !== ['state'=>'approved', 'revision'=>2]) throw new RuntimeException('Approval failed');
try {
  $store->decide('registration-1', 1, 'rejected', 'reviewer-1', 'Te laat');
  throw new RuntimeException('Stale revision was accepted');
} catch (RuntimeException $e) {
  if ($e->getMessage() !== 'Stale or invalid registration state') throw $e;
}
$count = (int)$db->query('SELECT COUNT(*) FROM inzet_operational_audit')->fetchColumn();
if ($count !== 3) throw new RuntimeException('Unexpected audit count: ' . $count);
echo "OperationalInzetStoreTest OK\n";
