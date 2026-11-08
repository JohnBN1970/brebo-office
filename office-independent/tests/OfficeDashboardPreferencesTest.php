<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Dashboard/OfficePersonalDashboard.php';
require_once dirname(__DIR__) . '/src/Dashboard/OfficeDashboardPreferences.php';

use Brebo\Office\Dashboard\OfficeDashboardPreferences;
use Brebo\Office\Dashboard\OfficePersonalDashboard;

function assertDashboard(bool $ok, string $reason): void {
  if (!$ok) throw new RuntimeException($reason);
}

$schema = OfficeDashboardPreferences::schema();
assertDashboard(str_contains($schema, 'office_dashboard_preferences'), 'Missing preference table');
assertDashboard(str_contains($schema, 'operator_id BIGINT UNSIGNED NOT NULL PRIMARY KEY'), 'Operator scope missing');
assertDashboard(!str_contains($schema, 'role_id'), 'Preferences must not store role grants');

if (!extension_loaded('pdo_sqlite')) {
  echo "Dashboard preference database test skipped: pdo_sqlite unavailable\n";
  exit(0);
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_dashboard_preferences (operator_id INTEGER PRIMARY KEY, layout_json TEXT NOT NULL, updated_at TEXT)');
$repo = new OfficeDashboardPreferences($db);
$permitted = ['projects', 'tasks', 'quality'];
assertDashboard($repo->load(17, $permitted) === $permitted, 'Default layout incorrect');
$saved = $repo->save(17, ['invoices', 'tasks', 'tasks', 'quality'], $permitted);
assertDashboard($saved === ['tasks', 'quality'], 'Unauthorized widget stored');
assertDashboard($repo->load(17, $permitted) === $saved, 'Preference roundtrip failed');
assertDashboard($repo->load(18, $permitted) === $permitted, 'Other operator preference leaked');
assertDashboard($repo->load(17, ['projects']) === ['projects'], 'Revoked permission still visible');
try {
  $repo->save(0, ['tasks'], $permitted);
  throw new RuntimeException('Invalid operator accepted');
} catch (RuntimeException $e) {
  if ($e->getMessage() === 'Invalid operator accepted') throw $e;
}
echo "Dashboard preference checks passed\n";
