<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeOfferRoleSchema.php';
require_once __DIR__ . '/../src/Offer/OfficeOfferRolePermission.php';
require_once __DIR__ . '/../src/Offer/OfficeOfferRoleScope.php';

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
echo "OfficeOfferRolePermissionDatabaseTest: PASS\n";
