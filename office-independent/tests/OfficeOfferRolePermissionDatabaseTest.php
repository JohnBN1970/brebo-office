<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeOfferRoleSchema.php';
require_once __DIR__ . '/../src/Offer/OfficeOfferRolePermission.php';
require_once __DIR__ . '/../src/Offer/OfficeOfferRoleScope.php';
require_once __DIR__ . '/../src/Offer/OfficeOfferModulePermission.php';

use Brebo\Office\Offer\OfficeOfferRoleSchema;
use Brebo\Office\Offer\OfficeOfferRolePermission;
use Brebo\Office\Offer\OfficeOfferRoleScope;

$dsn = getenv('OFFICE_OFFER_TEST_DSN');
if (!$dsn) {
    echo "OfficeOfferRolePermissionDatabaseTest: SKIP (no isolated test DSN)\n";
    exit(0);
}
$db = new PDO($dsn, (string) getenv('OFFICE_OFFER_TEST_USER'), (string) getenv('OFFICE_OFFER_TEST_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
foreach (OfficeOfferRoleSchema::statements() as $sql) {
    $db->exec($sql);
}
$db->exec("INSERT INTO office_roles (id, role_key, label, active) VALUES (1, 'calculator', 'Calculator', 1), (2, 'disabled', 'Disabled', 0)");
$db->exec("INSERT INTO office_user_roles VALUES (21, 1), (22, 1), (23, 2)");
$db->exec("INSERT INTO office_role_permissions VALUES (1, 'offers', 'create'), (2, 'offers', 'create')");
$roles = new OfficeOfferRolePermission($db);
if (!$roles->allows(21, 'create')) {
    throw new RuntimeException('Active role grant rejected');
}
foreach ([[21, 'issue'], [22, 'create'], [23, 'create'], [100, 'create']] as [$id, $permission]) {
    if ($roles->allows($id, $permission)) {
        throw new RuntimeException('Unauthorized role grant accepted');
    }
}
$db->exec("INSERT INTO office_role_scopes VALUES (1, 'offers', 'assigned'), (2, 'offers', 'all')");
$scopes = new OfficeOfferRoleScope($db);
if ($scopes->forOperator(21, 'offers') !== 'assigned'
    || $scopes->forOperator(22, 'offers') !== 'none'
    || $scopes->forOperator(23, 'offers') !== 'none'
    || $scopes->forOperator(21, 'finance') !== 'none') {
    throw new RuntimeException('Role scope isolation failed');
}
// Exercise role-derived access without the earlier fixture's explicit operator grant.
$db->exec("DELETE FROM office_module_permissions WHERE operator_id = 21 AND module = 'offers' AND permission = 'create'");
$moduleAccess = new \Brebo\Office\Offer\OfficeOfferModulePermission($db);
if (!$moduleAccess->allows(21, 'create') || $moduleAccess->allows(22, 'create')) {
    throw new RuntimeException('Role module scope not enforced');
}
$db->exec("UPDATE office_role_scopes SET scope_key = 'own' WHERE role_id = 1 AND module = 'offers'");
if ($moduleAccess->allows(21, 'create')) {
    throw new RuntimeException('Unsupported own scope granted access');
}
$db->exec("DELETE FROM office_role_scopes WHERE role_id = 1 AND module = 'offers'");
if ($moduleAccess->allows(21, 'create')) {
    throw new RuntimeException('Missing role scope granted access');
}
// An incomplete role schema must deny access without querying the missing table.
$db->exec('DROP TABLE office_role_scopes');
if ($moduleAccess->allows(21, 'create')) {
    throw new RuntimeException('Incomplete role schema granted access');
}
echo "OfficeOfferRolePermissionDatabaseTest: PASS\n";
