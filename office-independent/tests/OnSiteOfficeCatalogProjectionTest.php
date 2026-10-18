<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Inzet/OnSiteOfficeCatalogProjection.php';
$source = file_get_contents(__DIR__ . '/../src/Inzet/OnSiteOfficeCatalogProjection.php');
if (!is_string($source)) throw new RuntimeException('Cannot read Office projection');
foreach (['onsite_employees','onsite_projects','onsite_zones','ON DUPLICATE KEY UPDATE','VALUES (?,?,0)','VALUES (?,?,?,?,?,?,0)'] as $expected) {
  if (!str_contains($source,$expected)) throw new RuntimeException('Missing projection contract: ' . $expected);
}
$projection = new Brebo\Office\Inzet\OnSiteOfficeCatalogProjection(new PDO('sqlite::memory:'));
foreach ([
  static fn() => $projection->employee('','Missing'),
  static fn() => $projection->project('','Missing'),
  static fn() => $projection->zone('z','p','Bad',91.0,4.0,100.0),
  static fn() => $projection->zone('z','p','Bad',51.0,4.0,0.0),
] as $invalid) {
  try { $invalid(); throw new RuntimeException('Invalid projection accepted'); }
  catch (InvalidArgumentException $expected) {}
}
foreach (['UPDATE onsite_project_zone_assignments SET active = 0 WHERE project_id = ?',
  'UPDATE onsite_zones SET active = 0 WHERE project_id = ?',
  'beginTransaction()', 'commit()', 'rollBack()'] as $contract) {
  if (!str_contains($source,$contract)) throw new RuntimeException('Missing transactional project revocation: ' . $contract);
}
if (!str_contains($source,'UPDATE onsite_projects SET active = 0 WHERE project_id = ?'))
  throw new RuntimeException('Missing explicit Office project revocation');
try {
  $projection->deactivateProject('');
  throw new RuntimeException('Empty Office project revocation accepted');
} catch (InvalidArgumentException $expected) {}
echo "OnSiteOfficeCatalogProjectionTest OK\n";
