<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Demo/OfficeCommercialCalcHandoff.php';
require_once __DIR__ . '/../src/Demo/OfficeCommercialContextWriter.php';
use Brebo\Office\Demo\OfficeCommercialContextWriter;
$db = new PDO('sqlite::memory:');
$writer = new OfficeCommercialContextWriter($db);
$invalid = [
    [false, 'direct_order', '1', 'particulier', ['glas'], null, null],
    [true, 'direct_order', '1', 'unknown', ['glas'], null, null],
    [true, 'direct_order', '1', 'particulier', ['glas', 'glas'], null, null],
    [true, 'direct_order', '1', 'particulier', ['unknown'], null, null],
];
foreach ($invalid as $args) {
    try { $writer->save(...$args); throw new RuntimeException('Invalid input accepted'); }
    catch (InvalidArgumentException $expected) {}
}
echo "Commercial context validation OK\n";
