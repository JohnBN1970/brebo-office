<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeOfferChangeLinkRepository.php';
require_once __DIR__ . '/../src/Offer/OfficeOfferChangeLinkSchema.php';

use Brebo\Office\Offer\OfficeOfferChangeLinkRepository;
use Brebo\Office\Offer\OfficeOfferChangeLinkSchema;

$dsn = getenv('OFFICE_OFFER_TEST_DSN');
if (!$dsn) {
    echo "OfficeOfferChangeLinkDatabaseTest: SKIP (no isolated test DSN)\n";
    exit(0);
}
if (!str_starts_with($dsn, 'mysql:')) {
    throw new RuntimeException('MySQL test DSN required');
}
$db = new PDO($dsn, (string) getenv('OFFICE_OFFER_TEST_USER'), (string) getenv('OFFICE_OFFER_TEST_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$db->exec(OfficeOfferChangeLinkSchema::statements()[0]);
$db->exec("CREATE TABLE office_offers (id BIGINT UNSIGNED PRIMARY KEY, office_project_id BIGINT UNSIGNED, change_order_id BIGINT UNSIGNED, change_type VARCHAR(8), status VARCHAR(24))");
$db->exec("CREATE TABLE office_offer_versions (id BIGINT UNSIGNED PRIMARY KEY, offer_id BIGINT UNSIGNED, status VARCHAR(24))");
$db->exec("CREATE TABLE office_offer_acceptances (id BIGINT UNSIGNED PRIMARY KEY, offer_id BIGINT UNSIGNED, offer_version_id BIGINT UNSIGNED)");
$db->exec("CREATE TABLE office_project_contracts (id BIGINT UNSIGNED PRIMARY KEY, office_project_id BIGINT UNSIGNED)");
$db->exec("CREATE TABLE office_project_change_orders (id BIGINT UNSIGNED PRIMARY KEY, contract_id BIGINT UNSIGNED, change_type VARCHAR(8), status VARCHAR(24))");
$db->exec("INSERT INTO office_project_contracts VALUES (1, 10)");
$db->exec("INSERT INTO office_project_change_orders VALUES (2, 1, 'more', 'draft')");
$db->exec("INSERT INTO office_offers VALUES (3, 10, 2, 'more', 'accepted')");
$db->exec("INSERT INTO office_offer_versions VALUES (4, 3, 'accepted')");
$db->exec("INSERT INTO office_offer_acceptances VALUES (5, 3, 4)");
$repo = new OfficeOfferChangeLinkRepository($db);
$id = $repo->link(5, 7);
if ($id < 1) {
    throw new RuntimeException('Link was not created');
}
try {
    $repo->link(5, 7);
    throw new RuntimeException('Duplicate linkage was accepted');
} catch (PDOException $expected) {
    // Unique constraints reject repeated processing.
}
$count = (int) $db->query('SELECT COUNT(*) FROM office_offer_change_links')->fetchColumn();
if ($count !== 1) {
    throw new RuntimeException('Unexpected link count');
}
echo "OfficeOfferChangeLinkDatabaseTest: PASS\n";
