<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$schema = file_get_contents($root . '/src/Inzet/OfficePersonnelDetailsSchema.php');
$repository = file_get_contents($root . '/src/Inzet/OfficePersonnelDetailsRepository.php');
if (!is_string($schema) || !is_string($repository)) throw new RuntimeException('Personnel details files missing');
foreach (['office_inzet_personnel_details','office_inzet_personnel_skills','REFERENCES office_inzet_people(id)','UNIQUE KEY uniq_inzet_personnel_phone'] as $token) {
  if (!str_contains($schema, $token)) throw new RuntimeException('Missing schema contract: ' . $token);
}
foreach (["m.permission = 'manage'","a.active = 1","beginTransaction()","rollBack()","function find(","function save(","ON DUPLICATE KEY UPDATE"] as $token) {
  if (!str_contains($repository, $token)) throw new RuntimeException('Missing repository contract: ' . $token);
}
if (str_contains($repository, 'CREATE TABLE') || str_contains($repository, 'ALTER TABLE')) throw new RuntimeException('Repository must not run DDL');
echo "Personnel details storage contract OK\n";
