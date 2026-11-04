<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Demo/OfficeCommercialCalcHandoff.php';
require_once __DIR__ . '/../src/Demo/OfficeCommercialProfileRepository.php';
use Brebo\Office\Demo\OfficeCommercialProfileRepository;
$db = new PDO('sqlite::memory:');
$db->exec('CREATE TABLE office_commercial_profiles (id INTEGER PRIMARY KEY, position_code TEXT, version_code TEXT, status TEXT)');
$db->exec('CREATE TABLE office_commercial_profile_rules (profile_id INTEGER, category_code TEXT, material_markup_pct NUMERIC, labor_markup_pct NUMERIC)');
$db->exec('CREATE TABLE office_crm_commercial_context (source_type TEXT, source_id TEXT, position_code TEXT, work_categories_json TEXT, work_scope TEXT, profile_id INTEGER)');
$stmt = $db->prepare('INSERT INTO office_commercial_profiles VALUES (?, ?, ?, ?)');
$stmt->execute([1, 'toeleverancier', 'v1', 'active']);
$stmt = $db->prepare('INSERT INTO office_commercial_profile_rules VALUES (?, ?, ?, ?)');
$stmt->execute([1, 'montage', 5, 20]);
$stmt = $db->prepare('INSERT INTO office_crm_commercial_context VALUES (?, ?, ?, ?, ?, ?)');
$stmt->execute(['direct_order', 'ord-1', 'toeleverancier', json_encode(['montage']), 'plaatsen', 1]);
$repository = new OfficeCommercialProfileRepository($db);
$profile = $repository->findProfile('toeleverancier', 'v1');
$context = $repository->findCrmContext('direct_order', 'ord-1');
if ($profile['categories']['montage']['labor_markup_pct'] !== 20.0 || $context['work_categories'] !== ['montage']) {
    throw new RuntimeException('Repository roundtrip failed');
}
if ($repository->findProfile('particulier', 'v1') !== null) throw new RuntimeException('Unexpected profile');
echo "Commercial repository read contract OK\n";
