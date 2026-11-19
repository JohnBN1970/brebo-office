<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Project/OfficeCalcBuildingTruthReader.php';

use Brebo\Office\Project\OfficeCalcBuildingTruthReader;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "PDO SQLite is required for this contract test.\n");
    exit(1);
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_buildings (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
$db->exec('CREATE TABLE office_project_buildings (project_id INTEGER NOT NULL, building_id INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_housing_types (id INTEGER PRIMARY KEY, building_id INTEGER NOT NULL, code TEXT NOT NULL, name TEXT NOT NULL)');
$db->exec('CREATE TABLE office_project_housing_types (project_id INTEGER NOT NULL, building_id INTEGER NOT NULL, housing_type_id INTEGER NOT NULL, dwelling_count INTEGER NOT NULL)');
$db->exec("INSERT INTO office_buildings VALUES (1,'Blok A'),(2,'Blok B'),(3,'Niet gekoppeld')");
$db->exec('INSERT INTO office_project_buildings VALUES (10,1),(10,2),(20,3)');
$db->exec("INSERT INTO office_housing_types VALUES (11,1,'NLSFB-01','Appartement'),(12,1,'STABU-02','Studio'),(13,3,'VRIJ-03','Ander project')");
$db->exec('INSERT INTO office_project_housing_types VALUES (10,1,11,8),(10,1,12,3),(20,3,13,50)');

$reader = new OfficeCalcBuildingTruthReader($db);
$actual = $reader->forProject(10);
$expected = [
    'project_id' => 10,
    'buildings' => [
        ['id' => 1, 'name' => 'Blok A', 'housing_types' => [
            ['id' => 11, 'code' => 'NLSFB-01', 'name' => 'Appartement', 'dwelling_count' => 8],
            ['id' => 12, 'code' => 'STABU-02', 'name' => 'Studio', 'dwelling_count' => 3],
        ]],
        ['id' => 2, 'name' => 'Blok B', 'housing_types' => []],
    ],
];
if ($actual !== $expected) {
    fwrite(STDERR, "Unexpected building truth snapshot: " . json_encode($actual) . "\n");
    exit(1);
}
if ($reader->forProject(999)['buildings'] !== []) {
    throw new RuntimeException('Unlinked project must not see buildings');
}
try {
    $reader->forProject(0);
    throw new RuntimeException('Invalid project ID accepted');
} catch (InvalidArgumentException) {
    // Expected.
}
echo "OFFICE_CALC_BUILDING_TRUTH_READER_PASS\n";
