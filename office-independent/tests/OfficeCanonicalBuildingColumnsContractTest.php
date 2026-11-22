<?php
declare(strict_types=1);

// Canonical building columns must agree across schema, project and buildings views.
// Static regression check: actual VPS schema must be verified separately.
$root = dirname(__DIR__);
$schema = file_get_contents($root.'/src/Buildings/OfficeBuildingSchema.php');
$project = file_get_contents($root.'/public/office-project.php');
$buildings = file_get_contents($root.'/public/office-buildings.php');
$calcProjectContext = file_get_contents($root.'/public/office-calc-project-context.php');
$vpsPreflight = file_get_contents($root.'/deployment/vps/crm-direct-project-preflight.php');
foreach (['building_name VARCHAR(160)', 'street_address VARCHAR(255)', 'office_project_buildings'] as $term) {
    if (!str_contains($schema, $term)) {
        throw new RuntimeException('Canonical building schema changed: '.$term);
    }
}
foreach ([
    'b.building_name AS name',
    'b.street_address AS address_line',
    'b.building_name, ht.id AS housing_type_id',
    'ORDER BY b.building_name, b.id',
] as $term) {
    if (!str_contains($project, $term)) {
        throw new RuntimeException('Project building view uses noncanonical columns: '.$term);
    }
}
foreach (['b.building_name, b.street_address', 'GROUP BY b.id, b.building_name', 'ORDER BY b.building_name, b.id'] as $term) {
    if (!str_contains($buildings, $term)) {
        throw new RuntimeException('Buildings view uses noncanonical columns: '.$term);
    }
}
if (!str_contains($calcProjectContext, 'b.building_name AS name')) {
    throw new RuntimeException('Calc project context must read canonical building_name');
}
foreach ([$project, $buildings, $calcProjectContext] as $source) {
    foreach (['b.name', 'b.address_line'] as $legacy) {
        if (str_contains($source, $legacy)) {
            throw new RuntimeException('Legacy building column still queried: '.$legacy);
        }
    }
}
foreach (['CANONICAL_BUILDING_SCHEMA_', "'office_buildings' => ['id','building_name','street_address','postal_code','city']", "'office_project_buildings' => ['project_id','building_id']"] as $term) {
    if (!str_contains($vpsPreflight, $term)) {
        throw new RuntimeException('VPS canonical schema preflight missing: '.$term);
    }
}
echo "OFFICE_CANONICAL_BUILDING_COLUMNS_OK\n";
