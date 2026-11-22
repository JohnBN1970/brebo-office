<?php
declare(strict_types=1);
$path = __DIR__.'/../public/office-buildings.php';
$source = file_get_contents($path);
if (!is_string($source)) throw new RuntimeException('Building route unavailable');
$required = [
    "OFFICE_TEST_CL204_OPNAMES_READ_ENABLED",
    "OFFICE_TEST_CL204_OPNAMES_WRITE_ENABLED",
    "OFFICE_CL204_CSRF_SECRET",
    "hash_hmac('sha256'",
    "hash_equals(\$cl204Csrf",
    "OfficeCl204BuildingDraftService",
    "OfficeCl204FormView::render",
    "loadBuildingOpname",
    "FILTER_VALIDATE_INT",
    "303",
    '$cl204CanManage',
    '$cl204RegisterReady',
    'if ($cl204RegisterReady && $cl204CsrfReady)',
];
foreach ($required as $contract) {
    if (!str_contains($source,$contract)) {
        throw new RuntimeException('Missing CL-204 building route guard: '.$contract);
    }
}
if (!str_contains($source,"getenv('OFFICE_BASE_PATH') === '/office-test'")) {
    throw new RuntimeException('CL-204 writes must remain test-only');
}
$preRenderGuard = strpos($source, '// Validate opname selection before writing any HTML');
$pageRender = strpos($source, "echo '<!doctype html>");
if ($preRenderGuard === false || $pageRender === false || $preRenderGuard >= $pageRender) {
    throw new RuntimeException('Invalid opname ID must be rejected before HTML rendering');
}
if (!str_contains($source, 'loadBuildingOpname((int)$buildingId, $requestedCl204OpnameId)')) {
    throw new RuntimeException('Opname lookup must be scoped to the selected building');
}
echo "CL-204 building route contract OK\n";
