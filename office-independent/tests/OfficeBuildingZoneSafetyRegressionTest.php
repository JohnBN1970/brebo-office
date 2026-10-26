<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Buildings/OfficeBuildingZoneRepository.php';

use Brebo\Office\Buildings\OfficeBuildingZoneRepository;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "SKIP: pdo_sqlite unavailable\n");
    exit(0);
}
function assertZone(bool $ok, string $label): void {
    if (!$ok) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    echo "PASS: $label\n";
}
function rejects(callable $operation): bool {
    try { $operation(); return false; } catch (InvalidArgumentException $e) { return true; }
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_buildings (id INTEGER PRIMARY KEY, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_building_zones (id INTEGER PRIMARY KEY AUTOINCREMENT, building_id INTEGER, zone_name TEXT, latitude REAL, longitude REAL, radius_metres REAL)');
$db->exec('INSERT INTO office_buildings VALUES (1,1),(2,0)');
$repo = new OfficeBuildingZoneRepository($db);
$authorized = static fn(string $id): bool => $id === '1';
$zone = ['building_id'=>'1','zone_name'=>'Werkgebied','latitude'=>51.4,'longitude'=>4.4,'radius_metres'=>200];
$id = $repo->create($zone, $authorized);
assertZone($id === '1', 'authorized active building creates zone');
assertZone(rejects(fn()=> $repo->create($zone, static fn(string $id): bool => false)), 'unauthorized building rejected');
assertZone(rejects(fn()=> $repo->create($zone + ['project_id'=>99], $authorized)), 'project ownership injection rejected');
assertZone(rejects(fn()=> $repo->create(array_replace($zone,['radius_metres'=>5001]), $authorized)), 'oversized radius rejected');
assertZone(rejects(fn()=> $repo->create(array_replace($zone,['latitude'=>91]), $authorized)), 'invalid latitude rejected');
assertZone(rejects(fn()=> $repo->create(array_replace($zone,['building_id'=>'2']), static fn(string $id): bool => true)), 'inactive building rejected');
assertZone((int)$db->query('SELECT COUNT(*) FROM office_building_zones')->fetchColumn() === 1, 'failed creates do not write');

assertZone($repo->update('1', 1, ['zone_name'=>'Nieuwe zone','latitude'=>51.41,'longitude'=>4.41,'radius_metres'=>250], $authorized), 'current revision updates');
assertZone(!$repo->update('1', 1, ['zone_name'=>'Verouderd','latitude'=>51.42,'longitude'=>4.42,'radius_metres'=>250], $authorized), 'stale revision rejected');
assertZone((int)$db->query('SELECT revision FROM office_building_zones WHERE id=1')->fetchColumn() === 2, 'revision incremented once');
assertZone(rejects(fn()=> $repo->update('1', 2, ['zone_name'=>'Geen recht','latitude'=>51.42,'longitude'=>4.42,'radius_metres'=>250], static fn(string $id): bool => false)), 'unauthorized update rejected');
