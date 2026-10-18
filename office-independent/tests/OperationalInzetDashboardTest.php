<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Inzet/OperationalInzetDashboard.php';

use Brebo\Office\Inzet\OperationalInzetDashboard;

$rows = [
  ['work_date'=>'2026-10-18','project_id'=>'P1','person_id'=>'M1','planned_minutes'=>480,'registered_minutes'=>420,'state'=>'submitted'],
  ['work_date'=>'2026-10-18','project_id'=>'<script>alert(1)</script>','person_id'=>'M2','planned_minutes'=>480,'registered_minutes'=>480,'state'=>'approved'],
];
$html = OperationalInzetDashboard::render($rows, 'uitvoerder');
foreach (['16:00','15:00','8:00','Open beoordelingen','-1:00','&lt;script&gt;alert(1)&lt;/script&gt;'] as $expected) {
  if (!str_contains($html, $expected)) throw new RuntimeException('Missing dashboard content: ' . $expected);
}
if (str_contains($html, '<script>')) throw new RuntimeException('Unescaped project label');
try {
  OperationalInzetDashboard::render([], 'guest');
  throw new RuntimeException('Invalid role accepted');
} catch (InvalidArgumentException $e) {
  if ($e->getMessage() !== 'Unknown dashboard role') throw $e;
}
echo "OperationalInzetDashboardTest OK\n";
