<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$repo = file_get_contents($root . '/src/Inzet/OfficeInzetManageablePeopleRepository.php');
$page = file_get_contents($root . '/public/office-personnel.php');
if (!is_string($repo) || !is_string($page)) throw new RuntimeException('Personnel files missing');
foreach ([
  "public function listForOperator(",
  "AND p.active = 1",
  "public function listForOperatorByStatus(",
  "'active', 'inactive', 'all'",
  "m.permission = 'manage'",
  "a.active = 1",
] as $required) {
  if (!str_contains($repo, $required)) throw new RuntimeException('Personnel ACL/status contract missing: ' . $required);
}
foreach ([
  "listForOperatorByStatus(",
  "name=\"status\"",
  "value=\"inactive\"",
  "value=\"all\"",
  "'Actief' : 'Inactief'",
  "OfficePersonnelProfileForm::render",
] as $required) {
  if (!str_contains($page, $required)) throw new RuntimeException('Personnel page contract missing: ' . $required);
}
if (str_contains($page, 'listForOperator($operator->operatorId')) {
  throw new RuntimeException('Personnel page must use explicit status selector');
}
echo "Personnel status filter contract OK\n";
