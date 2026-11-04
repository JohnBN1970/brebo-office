<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Demo/OfficeCommercialCalcHandoff.php';
require_once __DIR__ . '/../src/Demo/OfficeCommercialContextIntegrity.php';
use Brebo\Office\Demo\OfficeCommercialContextIntegrity as I;
$db = new PDO('sqlite::memory:');
$db->exec('CREATE TABLE office_commercial_profiles (id INTEGER PRIMARY KEY, position_code TEXT, status TEXT)');
$stmt = $db->prepare('INSERT INTO office_commercial_profiles VALUES (?, ?, ?)');
$stmt->execute([1, 'hoofdleverancier', 'active']);
$stmt->execute([2, 'particulier', 'draft']);
$check = new I($db);
$check->assertProfile(1, 'hoofdleverancier');
$check->assertProfile(null, 'particulier');
foreach ([[1, 'particulier'], [2, 'particulier'], [99, 'hoofdleverancier']] as $case) {
    try { $check->assertProfile(...$case); throw new RuntimeException('Invalid profile accepted'); }
    catch (DomainException $expected) {}
}
I::assertSource('direct_order', 'ord-1', static fn($type, $id) => $type === 'direct_order' && $id === 'ord-1');
try {
    I::assertSource('direct_order', 'ord-2', static fn() => false);
    throw new RuntimeException('Unauthorized source accepted');
} catch (DomainException $expected) {}
echo "Commercial context integrity OK\n";
