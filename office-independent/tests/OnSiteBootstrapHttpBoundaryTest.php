<?php
declare(strict_types=1);
$entry = file_get_contents(__DIR__ . '/../public/onsite-bootstrap.php');
if ($entry === false) throw new RuntimeException('Bootstrap HTTP endpoint missing');
foreach (['OFFICE_ONSITE_BOOTSTRAP_ENABLED','OFFICE_ONSITE_SCHEMA_VERIFIED','HTTP_AUTHORIZATION','OnSiteBootstrapService','OnSiteBootstrapRepository','OFFICE_PUBLIC_HOST','REQUEST_METHOD'] as $required) {
  if (!str_contains($entry, $required)) throw new RuntimeException('Bootstrap security requirement missing: '.$required);
}
foreach ([
  __DIR__.'/../deployment/hostinger/index.php' => "'/api/onsite/v1/bootstrap'",
  __DIR__.'/../deployment/hostinger/office-test-index.php' => "'/office-test/api/onsite/v1/bootstrap'",
  __DIR__.'/../deployment/hostinger/.htaccess' => 'api/onsite/v1/bootstrap',
] as $file=>$needle) {
  $source = file_get_contents($file);
  if ($source === false || !str_contains($source,$needle)) throw new RuntimeException('Bootstrap route missing: '.$file);
}
echo "OnSiteBootstrapHttpBoundaryTest OK\n";
