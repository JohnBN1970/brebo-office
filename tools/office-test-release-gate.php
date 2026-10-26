#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * BREBO Office-test release gate.
 * Usage: php tools/office-test-release-gate.php /absolute/path/to/release/private
 * Read-only: no database connection, filesystem writes, or deploy.
 * This is a minimum safety gate, not a substitute for integration tests.
 */
$zoneOnly = ($argv[1] ?? '') === '--zone-only';
$root = $argv[$zoneOnly ? 2 : 1] ?? '';
if ($root === '' || !is_dir($root)) {
    fwrite(STDERR, "Usage: php tools/office-test-release-gate.php <private-release-root>\n");
    exit(2);
}
$checks = [
    'CRM manual opportunity entry' => ['src/Crm/OfficeCrmRepository.php', 'function createManualOpportunity'],
    'CRM opportunity preflight' => ['src/Crm/OfficeCrmSchemaPreflight.php', 'function requireOpportunityReady'],
    'Project read authorization' => ['src/Project/OfficeNativeProjectAccess.php', 'function canRead'],
    'OnSite verified identity bridge' => ['public/office-onsite-invitation.php', 'OnSiteActivationIdentityBridge::verifiedPerson'],
    'OnSite invitation landing' => ['public/office-onsite-invitation.php', 'OnSiteOfficeActivationPage::landing'],
    'CRM opportunity route' => ['public/crm-workspace.php', 'create-opportunity'],
    'Zone write uses verified test DB' => ['public/office-onsite-zone-create.php', 'u213420663_office_test'],
    'Zone write uses server-side CSRF' => ['public/office-onsite-zone-create.php', 'OnSiteOfficeZonePostAction'],
    'Zone edit route' => ['public/office-onsite-project-map.php', '/office-test/inzet/zone/bijwerken'],
    'Zone idempotency request key' => ['src/Buildings/OfficeBuildingZoneRepository.php', 'requestKey'],
    'Zone compare-and-swap update' => ['src/Buildings/OfficeBuildingZoneRepository.php', 'function update'],
    'Zone update handler' => ['public/office-onsite-zone-update.php', 'OnSiteOfficeZoneUpdatePostAction'],
    'Zone schema readiness' => ['src/Buildings/OfficeBuildingZoneReadiness.php', 'function requireReady'],
    'Zone edit token' => ['src/Inzet/OnSiteOfficeZoneFormToken.php', 'class OnSiteOfficeZoneFormToken'],
];
if ($zoneOnly) {
    $checks = array_filter($checks, static fn(string $name): bool =>
        $name === 'Project read authorization' || in_array($name, [
            'Zone edit route', 'Zone idempotency request key', 'Zone compare-and-swap update',
            'Zone update handler', 'Zone schema readiness', 'Zone edit token',
        ], true), ARRAY_FILTER_USE_KEY);
}
$failures = 0;
foreach ($checks as $name => [$path, $needle]) {
    $file = rtrim($root, '/').'/'.$path;
    $source = is_file($file) ? file_get_contents($file) : false;
    $passed = is_string($source) && str_contains($source, $needle);
    echo ($passed ? 'PASS' : 'FAIL')." | ".$name." | ".$path."\n";
    if (!$passed) $failures++;
}
if ($failures) {
    fwrite(STDERR, "BLOCKED: ".$failures." missing release contract(s). No deploy.\n");
    exit(1);
}
echo "Minimum static release contracts present. Run integration/security tests before deploy.\n";
