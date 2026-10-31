<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeOfferRolePermission.php';
require_once __DIR__ . '/../src/Offer/OfficeOfferModulePermission.php';

use Brebo\Office\Offer\OfficeOfferModulePermission;

$dsn = getenv('OFFICE_OFFER_TEST_DSN');
if (!$dsn) {
    echo "OfficeOfferModulePermissionDatabaseTest: SKIP (no isolated test DSN)\n";
    exit(0);
}
$db = new PDO($dsn, (string) getenv('OFFICE_OFFER_TEST_USER'), (string) getenv('OFFICE_OFFER_TEST_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$access = new OfficeOfferModulePermission($db);
$db->exec("INSERT INTO office_operator_accounts VALUES (21, 1), (22, 0), (23, 1)");
$db->exec("INSERT INTO office_module_permissions VALUES (21, 'offers', 'create'), (22, 'offers', 'create'), (23, 'finance', 'read')");
if (!$access->allows(21, 'create')) {
    throw new RuntimeException('Explicit grant rejected');
}
foreach ([[21, 'read'], [22, 'create'], [23, 'read'], [21, 'unknown'], [0, 'create']] as [$operatorId, $permission]) {
    if ($access->allows($operatorId, $permission)) {
        throw new RuntimeException('Unauthorized module permission accepted');
    }
}
echo "OfficeOfferModulePermissionDatabaseTest: PASS\n";
