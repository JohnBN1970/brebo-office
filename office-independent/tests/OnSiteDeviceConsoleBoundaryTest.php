<?php
declare(strict_types=1);
$source = file_get_contents(__DIR__.'/../public/onsite-devices.php');
if ($source === false) throw new RuntimeException('Device console missing');
foreach (['OFFICE_ONSITE_ADMIN_ENABLED','OFFICE_ONSITE_SCHEMA_VERIFIED','OFFICE_CRM_SESSION_MODE','currentOperator()','onsite_enrollment_operator_grants','g'] as $needle) {
  if (!str_contains($source,$needle)) throw new RuntimeException('Missing device console boundary: '.$needle);
}
foreach ([
  __DIR__.'/../deployment/hostinger/index.php' => '/office/inzet/apparaten',
  __DIR__.'/../deployment/hostinger/office-test-index.php' => '/office-test/inzet/apparaten',
] as $path=>$route) {
  $router = file_get_contents($path);
  if ($router === false || !str_contains($router,$route)) throw new RuntimeException('Missing device console route');
}
echo "OnSiteDeviceConsoleBoundaryTest OK\n";
