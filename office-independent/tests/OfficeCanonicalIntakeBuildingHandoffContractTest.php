<?php
declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__) . '/src/Intake/OfficeCanonicalIntakeBuildingHandoff.php');
if (!is_string($source)) {
    throw new RuntimeException('handoff_unavailable');
}
foreach ([
    'OfficeCanonicalIntakeAdapter::map',
    '$this->buildings->resolve',
    '$this->requests->register',
    "'building_resolution' =>",
    "'candidates' =>",
] as $expected) {
    if (!str_contains($source, $expected)) {
        throw new RuntimeException('handoff_contract_missing');
    }
}
foreach (['CREATE TABLE', 'DROP TABLE', 'INSERT INTO office_buildings', 'UPDATE office_buildings'] as $forbidden) {
    if (str_contains($source, $forbidden)) {
        throw new RuntimeException('building_mutation_forbidden');
    }
}
echo "OfficeCanonicalIntakeBuildingHandoffContractTest OK\n";
