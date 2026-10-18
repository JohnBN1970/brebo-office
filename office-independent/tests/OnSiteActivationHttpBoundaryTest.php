<?php
declare(strict_types=1);
$endpoint = file_get_contents(__DIR__ . '/../public/onsite-activate.php');
if ($endpoint === false) throw new RuntimeException('Activation endpoint missing');
foreach ([
  'OFFICE_ONSITE_ACTIVATION_ENABLED','OFFICE_ONSITE_SCHEMA_VERIFIED',
  'OFFICE_PUBLIC_HOST','REQUEST_METHOD','CONTENT_TYPE','activation_token',
  'OnSiteActivationService','payload_too_large',
] as $needle) {
  if (!str_contains($endpoint, $needle)) throw new RuntimeException('Missing activation boundary: '.$needle);
}
foreach ([
  __DIR__ . '/../deployment/hostinger/index.php' => "'/api/onsite/v1/activate'",
  __DIR__ . '/../deployment/hostinger/office-test-index.php' => "'/office-test/api/onsite/v1/activate'",
  __DIR__ . '/../deployment/hostinger/.htaccess' => '^api/onsite/v1/activate/',
] as $path=>$needle) {
  $text = file_get_contents($path);
  if ($text === false || !str_contains($text, $needle)) throw new RuntimeException('Activation route missing: '.$path);
}
echo "OnSiteActivationHttpBoundaryTest OK\n";
