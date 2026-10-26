<?php
declare(strict_types=1);

/**
 * Dependency and fail-closed contract for native OnSite bootstrap.
 * Static contract only; database authorization requires a separate MySQL test.
 */
$root = dirname(__DIR__);
$endpoint = file_get_contents($root . '/public/onsite-bootstrap.php');
$repository = file_get_contents($root . '/src/Inzet/OnSiteNativeBootstrapRepository.php');
$service = file_get_contents($root . '/src/Inzet/OnSiteBootstrapService.php');
if (!is_string($endpoint) || !is_string($repository) || !is_string($service)) {
  throw new RuntimeException('OnSite bootstrap source missing');
}
foreach ([
  "getenv('OFFICE_ONSITE_NATIVE_BUILDING_ZONES_VERIFIED') === 'enabled'",
  "getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED') !== 'enabled'",
  "getenv('OFFICE_NATIVE_BUILDING_ZONES_SCHEMA_VERIFIED') !== 'enabled'",
  'new \\Brebo\\Office\\Inzet\\OnSiteNativeBootstrapRepository($db)',
  'new \\Brebo\\Office\\Inzet\\OnSiteBootstrapRepository($db)',
  'new \\Brebo\\Office\\Inzet\\OnSiteDeviceTokenRepository($db)',
] as $required) {
  if (!str_contains($endpoint, $required)) {
    throw new RuntimeException('Bootstrap feature gate or legacy fallback missing: ' . $required);
  }
}
foreach ([
  'office_inzet_project_team',
  'person.active=1',
  'office_project_buildings',
  'office_building_zones',
  'z.active=1',
  't.person_id=?',
  'z.radius_metres <= 1000',
  'p.title AS project_name',
] as $required) {
  if (!str_contains($repository, $required)) {
    throw new RuntimeException('Native bootstrap access predicate missing: ' . $required);
  }
}
if (!str_contains($service, "'project_name'")) {
  throw new RuntimeException('Native project name not surfaced in iOS response');
}
echo "OnSite native bootstrap static contract OK\n";
