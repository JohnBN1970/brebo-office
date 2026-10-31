<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Project/OfficeNativeProjectAccess.php';
require_once __DIR__ . '/../src/Offer/OfficeOfferAccess.php';
require_once __DIR__ . '/../src/Offer/OfficeOfferChangeLinkRepository.php';
require_once __DIR__ . '/../src/Offer/OfficeOfferChangeLinkApplication.php';

use Brebo\Office\Offer\OfficeOfferChangeLinkApplication;

$dsn = getenv('OFFICE_OFFER_TEST_DSN');
if (!$dsn) {
    echo "OfficeOfferChangeLinkAuthorizationDatabaseTest: SKIP (no isolated test DSN)\n";
    exit(0);
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
$app = new OfficeOfferChangeLinkApplication($db);
foreach ([8, 9, 100] as $operatorId) {
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
echo "OfficeOfferChangeLinkAuthorizationDatabaseTest: PASS\n";
