<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeOfferRoleSchema.php';
require_once __DIR__ . '/../src/Offer/OfficeRoleSettings.php';

use Brebo\Office\Offer\OfficeOfferRoleSchema;
use Brebo\Office\Offer\OfficeRoleSettings;

$dsn = getenv('OFFICE_OFFER_TEST_DSN');
if (!$dsn) {
    echo "OfficeRoleSettingsDatabaseTest: SKIP (no isolated test DSN)\n";
    exit(0);
}
if (parse_url(str_replace('mysql:', 'mysql://', $dsn), PHP_URL_HOST) === null
    && !str_contains($dsn, 'dbname=offer_ci_isolated')) {
    throw new RuntimeException('Only isolated CI database is supported');
}
$db = new PDO($dsn, (string) getenv('OFFICE_OFFER_TEST_USER'), (string) getenv('OFFICE_OFFER_TEST_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$settings = new OfficeRoleSettings($db);
$db->exec("INSERT INTO office_roles (id, role_key, label, active) VALUES (31, 'role_settings_test', 'Role settings test', 1)");
$settings->setModule(31, 'offers', true, ['read', 'create', 'read'], 'assigned');
$grants = $db->query("SELECT permission FROM office_role_permissions WHERE role_id = 31 AND module = 'offers' ORDER BY permission")->fetchAll(PDO::FETCH_COLUMN);
if ($grants !== ['create', 'read']) {
    throw new RuntimeException('Module permissions not saved correctly');
}
$scope = $db->query("SELECT scope_key FROM office_role_scopes WHERE role_id = 31 AND module = 'offers'")->fetchColumn();
if ($scope !== 'assigned') {
    throw new RuntimeException('Module scope not saved');
}
try {
    $settings->setModule(31, 'offers', true, ['invalid'], 'all');
    throw new RuntimeException('Invalid permission was accepted');
} catch (DomainException $expected) {
}
$settings->setModule(31, 'offers', false, [], 'none');
$count = (int) $db->query("SELECT COUNT(*) FROM office_role_permissions WHERE role_id = 31")->fetchColumn();
if ($count !== 0) {
    throw new RuntimeException('Disabled module retained grants');
}
echo "OfficeRoleSettingsDatabaseTest: PASS\n";
