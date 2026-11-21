<?php
declare(strict_types=1);

// Test-only project building link must reuse canonical repository and existing ACL/CSRF.
$root = dirname(__DIR__);
$page = file_get_contents($root.'/public/office-project.php');
$repo = file_get_contents($root.'/src/Buildings/OfficeBuildingRepository.php');
foreach ([
    'building_link_action',
    'link_existing_building',
    'OfficeNativeProjectAccess($db))->canManage',
    'OfficeMount::isTest()',
    "hash_equals($cookie, $csrf)",
    'OfficeBuildingRepository($db))->linkProject',
    'SELECT id, building_name, street_address, postal_code, city FROM office_buildings',
    'office_project_buildings WHERE project_id=?',
    'name="building_id"',
    'Gebouw koppelen',
] as $term) {
    if (!str_contains($page, $term)) throw new RuntimeException('Missing existing building link contract: '.$term);
}
if (!str_contains($repo, 'authorizedProject($projectId)!==true')
    || !str_contains($repo, 'id=? AND active=1 ON DUPLICATE KEY UPDATE')
    || !str_contains($repo, 'belongsToProject($projectId,$buildingId)')) {
    throw new RuntimeException('Canonical building writer no longer enforces authorized, active, idempotent linking');
}
$handler = strpos($page, "isset($_POST['building_link_action'])");
$generic = strpos($page, "$writeMessage = null;");
if ($handler === false || $generic === false || $handler >= $generic) {
    throw new RuntimeException('Building link must be handled before generic project POST writes');
}
echo "OFFICE_PROJECT_EXISTING_BUILDING_LINK_CONTRACT_OK\n";
