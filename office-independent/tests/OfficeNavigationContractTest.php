<?php
declare(strict_types=1);

// Run: php office-independent/tests/OfficeNavigationContractTest.php
require_once dirname(__DIR__) . '/src/Intake/OfficeNavigation.php';

use Brebo\Office\Intake\OfficeNavigation;

function expectNav(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
}

$original = '<html><head></head><body><aside><nav aria-label="Hoofdnavigatie">Old</nav></aside><main><h1>Projecten</h1><nav class="office-tabs" aria-label="Module"><a href="/office-test/projecten">Projecten</a><a href="/office-test/projecten?view=inzet">Inzet</a></nav></main></body></html>';
$result = OfficeNavigation::decorate($original, '/office-test/projecten', [], 200, 'text/html; charset=utf-8');
expectNav(str_contains($result, 'aria-label="Module"'), 'Module tabs must remain');
expectNav(str_contains($result, 'view=inzet'), 'Inzet tab must remain');
expectNav(substr_count($result, 'aria-label="Hoofdnavigatie"') === 1, 'Exactly one global navigation');
foreach ([
    ['/office-test/inloggen', 200, 'text/html'],
    ['/office-test/uitloggen', 200, 'text/html'],
    ['/office-test/api/onsite/v1/presence', 200, 'text/html'],
    ['/office-test/projecten', 403, 'text/html'],
    ['/office-test/projecten', 200, 'application/json'],
] as [$path, $status, $type]) {
    expectNav(OfficeNavigation::decorate($original, $path, [], $status, $type) === $original, 'Must not decorate ' . $path);
}
$detail = OfficeNavigation::decorate('<html><head></head><body><main><h1>Project</h1></main></body></html>', '/office-test/projecten/detail', ['id' => '42'], 200, 'text/html');
expectNav(str_contains($detail, 'aria-label="Projectnavigatie"'), 'Project navigation must be present');
expectNav(OfficeNavigation::projectId('/office-test/projecten/detail', ['id' => 'abc']) === null, 'Reject invalid project ID');
echo "OfficeNavigationContractTest OK" . PHP_EOL;
