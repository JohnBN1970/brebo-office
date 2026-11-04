<?php
declare(strict_types=1);

foreach ([
    'OfficeDisciplineCatalog.php',
    'OfficeProcessBlueprint.php',
    'OfficeIntakeProcessPlan.php',
    'OfficeCanonicalIntakeAdapter.php',
] as $file) {
    require_once dirname(__DIR__) . '/src/Intake/' . $file;
}

use Brebo\Office\Intake\OfficeCanonicalIntakeAdapter;

$mapped = OfficeCanonicalIntakeAdapter::map([
    'source' => 'website',
    'request_id' => '123e4567-e89b-12d3-a456-426614174000',
    'disciplines' => ['levering + montage', 'expertise', 'expertise'],
    'building_id' => '42',
    'relation_id' => 7,
]);
if ($mapped['source'] !== 'website'
    || $mapped['request']['request_id'] !== '123e4567-e89b-12d3-a456-426614174000'
    || $mapped['request']['disciplines'] !== ['levering_montage', 'expertise']
    || $mapped['request']['building_id'] !== '42'
    || $mapped['relation_id'] !== 7) {
    throw new RuntimeException('trusted_handoff_mapping_failed');
}
$unresolved = OfficeCanonicalIntakeAdapter::map([
    'source' => 'email',
    'request_id' => 'mail-2026-1',
    'disciplines' => ['advies'],
]);
if ($unresolved['request']['building_id'] !== null || $unresolved['relation_id'] !== null) {
    throw new RuntimeException('unverified_identity_was_inferred');
}
foreach ([
    ['source' => 'website', 'request_id' => 'bad id', 'disciplines' => ['bouw']],
    ['source' => 'website', 'request_id' => 'good-1', 'disciplines' => ['mjop']],
    ['source' => 'website', 'request_id' => 'good-1', 'disciplines' => ['bouw'], 'building_id' => 42],
    ['source' => 'website', 'request_id' => 'good-1', 'disciplines' => ['bouw'], 'relation_id' => '7'],
    ['source' => 'web site', 'request_id' => 'good-1', 'disciplines' => ['bouw']],
] as $bad) {
    try {
        OfficeCanonicalIntakeAdapter::map($bad);
        throw new RuntimeException('invalid_handoff_accepted');
    } catch (InvalidArgumentException $expected) {
    }
}
echo "OfficeCanonicalIntakeAdapterTest OK\n";
