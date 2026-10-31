<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Project/OfficeNativeProjectAccess.php';
require_once __DIR__ . '/../src/Offer/OfficeOfferAccess.php';
require_once __DIR__ . '/../src/Offer/OfficeOfferRolePermission.php';
require_once __DIR__ . '/../src/Offer/OfficeOfferModulePermission.php';
require_once __DIR__ . '/../src/Offer/OfficeOfferChangeLinkRepository.php';
require_once __DIR__ . '/../src/Offer/OfficeOfferChangeLinkApplication.php';

use Brebo\Office\Offer\OfficeOfferChangeLinkApplication;

$dsn = getenv('OFFICE_OFFER_TEST_DSN');
if (!$dsn) {
    echo "OfficeOfferChangeLinkAuthorizationDatabaseTest: SKIP (no isolated test DSN)\n";
    exit(0);
}
if (!is_string($dsn) || !str_contains($dsn, 'dbname=offer_ci_isolated')) {
    throw new RuntimeException('Refusing database test outside offer_ci_isolated');
}
$db = new PDO($dsn, (string) getenv('OFFICE_OFFER_TEST_USER'), (string) getenv('OFFICE_OFFER_TEST_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
// Prior integration test intentionally links acceptance 5; reset only this isolated fixture.
$db->exec("DELETE FROM office_offer_change_links");
$db->exec("CREATE TABLE office_projects (id BIGINT UNSIGNED PRIMARY KEY)");
$db->exec("CREATE TABLE office_operator_accounts (id BIGINT UNSIGNED PRIMARY KEY, active TINYINT NOT NULL)");
$db->exec("CREATE TABLE office_project_memberships (project_id BIGINT UNSIGNED NOT NULL, operator_id BIGINT UNSIGNED NOT NULL, permission VARCHAR(24) NOT NULL)");
$db->exec("INSERT INTO office_projects VALUES (10)");
$db->exec("INSERT INTO office_operator_accounts VALUES (7, 1), (8, 1), (9, 0)");
$db->exec("INSERT INTO office_project_memberships VALUES (10, 7, 'manage'), (10, 8, 'read'), (10, 9, 'manage')");
$db->exec("CREATE TABLE office_module_permissions (operator_id BIGINT UNSIGNED NOT NULL, module VARCHAR(32) NOT NULL, permission VARCHAR(32) NOT NULL)");
$db->exec("INSERT INTO office_module_permissions VALUES (7, 'offers', 'link_change'), (8, 'offers', 'link_change'), (9, 'offers', 'link_change')");
$db->exec("INSERT INTO office_operator_accounts VALUES (11, 1)");
$db->exec("INSERT INTO office_project_memberships VALUES (10, 11, 'manage')");
$app = new OfficeOfferChangeLinkApplication($db);
foreach ([8, 9, 11, 100] as $operatorId) {
    try {
        $app->linkAcceptedChange($operatorId, 5);
        throw new RuntimeException('Unauthorized operator accepted: ' . $operatorId);
    } catch (DomainException $expected) {
    }
}
$id = $app->linkAcceptedChange(7, 5);
if ($id < 1) {
    throw new RuntimeException('Authorized manager could not link');
}

// Exercise the complete authorization path with a textual Office identity.
$db->exec('ALTER TABLE office_operator_accounts MODIFY id VARCHAR(128) NOT NULL');
$db->exec('ALTER TABLE office_project_memberships MODIFY operator_id VARCHAR(128) NOT NULL');
$db->exec('ALTER TABLE office_module_permissions MODIFY operator_id VARCHAR(128) NOT NULL');
$db->exec("INSERT INTO office_operator_accounts VALUES ('operator-link-441', 1)");
$db->exec("INSERT INTO office_project_memberships VALUES (10, 'operator-link-441', 'manage')");
$db->exec("INSERT INTO office_module_permissions VALUES ('operator-link-441', 'offers', 'link_change')");
$db->exec("INSERT INTO office_project_change_orders VALUES (32, 1, 'more', 'draft')");
$db->exec("INSERT INTO office_offers VALUES (33, 10, 32, 'more', 'accepted')");
$db->exec("INSERT INTO office_offer_versions VALUES (34, 33, 'accepted')");
$db->exec("INSERT INTO office_offer_acceptances VALUES (35, 33, 34)");
if ($app->linkAcceptedChange('operator-link-441', 35) < 1) {
    throw new RuntimeException('Textual Office identity could not link accepted change');
}
echo "OfficeOfferChangeLinkAuthorizationDatabaseTest: PASS\n";
