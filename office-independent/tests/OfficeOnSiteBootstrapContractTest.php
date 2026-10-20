<?php
declare(strict_types=1);

// Static cross-client contract gate. Runtime database behavior is verified separately.
$root = dirname(__DIR__);
$client = file_get_contents(dirname($root) . '/ios/CrewPOC/Sources/OnSiteAPIClient.swift');
$service = file_get_contents($root . '/src/Inzet/OnSiteBootstrapService.php');
$repository = file_get_contents($root . '/src/Inzet/OnSiteBootstrapRepository.php');
if (!is_string($client) || !is_string($service) || !is_string($repository)) {
  throw new RuntimeException('Missing bootstrap contract source');
}
foreach (['employee','projects','zones','radius_metres','latitude','longitude','display_name'] as $field) {
  if (!str_contains($client, $field) || (!str_contains($service, $field) && !str_contains($repository, $field))) {
    throw new RuntimeException('Bootstrap contract field mismatch: ' . $field);
  }
}
foreach (['is_finite','abs($lat) > 90','abs($lon) > 180','$radius <= 0'] as $guard) {
  if (!str_contains($service, $guard)) {
    throw new RuntimeException('Missing bootstrap geometry validation: ' . $guard);
  }
}
echo "OnSite static bootstrap contract checks passed\n";
