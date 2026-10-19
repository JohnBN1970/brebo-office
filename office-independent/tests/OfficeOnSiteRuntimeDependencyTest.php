<?php
declare(strict_types=1);
/**
 * Fail closed if a public OnSite endpoint references absent PHP runtime files.
 * Never mark OnSite release-ready based solely on unrelated Inzet CI.
 */
$root = dirname(__DIR__);
$endpoints = [
  'onsite-activate.php' => ['OnSiteDeviceTokenRepository','OnSiteActivationService'],
  'onsite-bootstrap.php' => ['OnSiteDeviceTokenRepository','OnSiteBootstrapRepository','OnSiteBootstrapService'],
  'onsite-presence.php' => [
    'OnSiteAutomaticEventValidator','OnSiteEventInbox','OnSiteAutomaticIngest',
    'OnSiteDeviceTokenRepository','OnSiteAssignmentRepository',
    'OnSitePresenceApiHandler','OnSitePresenceRequestService','OnSitePresenceApplication',
  ],
];
$missing = [];
foreach ($endpoints as $endpoint => $classes) {
  $source = file_get_contents($root . '/public/' . $endpoint);
  if (!is_string($source)) throw new RuntimeException('Missing OnSite endpoint: ' . $endpoint);
  foreach ($classes as $class) {
    if (!str_contains($source, $class)) {
      throw new RuntimeException('Dependency no longer referenced; review contract: ' . $class);
    }
    $file = $root . '/src/Inzet/' . $class . '.php';
    if (!is_file($file)) $missing[] = $endpoint . ' requires ' . $class . '.php';
  }
}
if ($missing !== []) {
  fwrite(STDERR, "OnSite runtime release BLOCKED; missing files:\n" . implode("\n", array_unique($missing)) . "\n");
  exit(1);
}
echo "OnSite public endpoint dependencies present\n";
