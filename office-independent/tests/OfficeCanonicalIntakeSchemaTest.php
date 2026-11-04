<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Intake/OfficeCanonicalIntakeSchema.php';
use Brebo\Office\Intake\OfficeCanonicalIntakeSchema;

$statements = OfficeCanonicalIntakeSchema::statements();
if (count($statements) !== 3) {
    throw new RuntimeException('expected_three_additive_tables');
}
$all = implode("\n", $statements);
foreach ([
    'office_intake_requests', 'office_intake_request_disciplines',
    'office_intake_events', 'UNIQUE KEY uq_intake_source_request',
    'FOREIGN KEY (building_id) REFERENCES office_buildings(id)',
    'FOREIGN KEY (relation_id) REFERENCES office_crm_relations(id)',
    'blueprint_version', 'building_resolution'
] as $expected) {
    if (!str_contains($all, $expected)) {
        throw new RuntimeException('missing_contract_' . $expected);
    }
}
if (str_contains(strtoupper($all), 'DROP TABLE') || str_contains(strtoupper($all), 'ALTER TABLE')) {
    throw new RuntimeException('non_additive_schema_detected');
}
echo "OfficeCanonicalIntakeSchemaTest OK (11 checks)\n";
