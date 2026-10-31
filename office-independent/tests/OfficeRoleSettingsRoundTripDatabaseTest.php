<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeRoleSettings.php';
require_once __DIR__ . '/../src/Offer/OfficeRoleSettingsRead.php';
require_once __DIR__ . '/../src/Offer/OfficeRoleSettingsView.php';
require_once __DIR__ . '/../src/Offer/OfficeRoleSettingsPage.php';
require_once __DIR__ . '/../src/Offer/OfficeRoleSettingsController.php';
require_once __DIR__ . '/../src/Offer/OfficeRoleSettingsEndpoint.php';

$dsn = getenv('OFFICE_OFFER_TEST_DSN');
if (!$dsn || !str_contains($dsn, 'dbname=offer_ci_isolated')) {
    echo "OfficeRoleSettingsRoundTripDatabaseTest: SKIP (isolated CI only)\n";
    exit(0);
}
$db = new PDO($dsn, (string) getenv('OFFICE_OFFER_TEST_USER'), (string) getenv('OFFICE_OFFER_TEST_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec("INSERT INTO office_roles (id, role_key, label, active) VALUES (41, 'roundtrip_role', 'Roundtrip role', 1)");
$db->exec("INSERT INTO office_operator_accounts (id, active) VALUES (441, 1)");
$db->exec("INSERT INTO office_module_permissions (operator_id, module, permission) VALUES (441, 'settings', 'manage')");
$endpoint = new Brebo\Office\Offer\OfficeRoleSettingsEndpoint($db);
$action = '/office-test/instellingen/rollen';
$initial = $endpoint->handle('GET', 441, 'test-csrf', $action);
if ($initial['status'] !== 200 || !str_contains($initial['body'], 'name="module" value="offers"')) {
    throw new RuntimeException('Disabled Offers module missing from initial settings page');
}
$denied = $endpoint->handle('POST', 441, 'test-csrf', $action, ['csrf_token'=>'wrong','role_id'=>'41','module'=>'offers','enabled'=>'1','permissions'=>['read'],'scope'=>'assigned']);
if ($denied['status'] !== 403) throw new RuntimeException('Invalid CSRF accepted');
$saved = $endpoint->handle('POST', 441, 'test-csrf', $action, ['csrf_token'=>'test-csrf','role_id'=>'41','module'=>'offers','enabled'=>'1','permissions'=>['read','create'],'scope'=>'assigned']);
if ($saved['status'] !== 303) throw new RuntimeException('Authorized settings save failed');
$rendered = $endpoint->handle('GET', 441, 'test-csrf', $action);
if ($rendered['status'] !== 200 || !str_contains($rendered['body'], 'name="enabled" value="1" checked') || !str_contains($rendered['body'], '<option value="assigned" selected>')) {
    throw new RuntimeException('Saved settings not reflected in page');
}
echo "OfficeRoleSettingsRoundTripDatabaseTest: PASS\n";
