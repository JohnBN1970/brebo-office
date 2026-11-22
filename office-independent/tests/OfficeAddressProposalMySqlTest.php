<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Documents/OfficeDocumentRegistry.php';
require_once dirname(__DIR__) . '/src/Documents/OfficeAddressProposalSchema.php';
require_once dirname(__DIR__) . '/src/Documents/OfficeSourceAddressExtractor.php';
require_once dirname(__DIR__) . '/src/Documents/OfficeAddressProposalService.php';
require_once dirname(__DIR__) . '/src/Documents/OfficeAddressProposalReviewService.php';
require_once dirname(__DIR__) . '/src/Buildings/OfficeIntakeBuildingResolver.php';

use Brebo\Office\Documents\OfficeDocumentRegistry;
use Brebo\Office\Documents\OfficeAddressProposalSchema;
use Brebo\Office\Documents\OfficeSourceAddressExtractor;
use Brebo\Office\Documents\OfficeAddressProposalService;
use Brebo\Office\Documents\OfficeAddressProposalReviewService;
use Brebo\Office\Buildings\OfficeIntakeBuildingResolver;

$dsn = getenv('OFFICE_DOCUMENT_TEST_DSN');
if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:')) throw new RuntimeException('isolated_mysql_required');
$db = new PDO($dsn, getenv('OFFICE_DOCUMENT_TEST_USER') ?: '', getenv('OFFICE_DOCUMENT_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$name = (string)$db->query('SELECT DATABASE()')->fetchColumn();
if (!str_starts_with($name, 'office_documents_ci_')) throw new RuntimeException('refusing_non_isolated_database');
foreach (OfficeDocumentRegistry::schema() as $statement) $db->exec($statement);
foreach (OfficeAddressProposalSchema::statements() as $statement) $db->exec($statement);
$db->exec('CREATE TABLE IF NOT EXISTS office_buildings (id BIGINT UNSIGNED NOT NULL PRIMARY KEY, address_line VARCHAR(255), postal_code VARCHAR(32), city VARCHAR(160)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$db->exec("INSERT INTO office_buildings (id,address_line,postal_code,city) VALUES (101,'Kade 1','1234 AB','Gouda'),(102,'Kade 2','1234 AB','Gouda'),(103,'Kade 2','1234 AB','Gouda')");
$path = tempnam(sys_get_temp_dir(), 'office-proposal-');
try {
    file_put_contents($path, "Adres;Postcode;Plaats;Naam\nKade 1;1234 AB;Gouda;J. Jansen\nKade 2;1234 AB;Gouda;J. Jansen\nKade 9;1234 AB;Gouda;J. Jansen\n");
    $hash = hash_file('sha256', $path);
    $registry = new OfficeDocumentRegistry($db);
    $id = $registry->register(['storage_provider'=>'office_private','storage_key'=>'originals/'.substr($hash,0,2).'/'.$hash,'sha256'=>$hash,'size'=>filesize($path)], 'Test', 'bewoners.csv', 'text/csv', 'ci');
    $service = new OfficeAddressProposalService($db, new OfficeSourceAddressExtractor(), new OfficeIntakeBuildingResolver($db));
    $first = $service->propose($id, $path, 'text/csv', 'bewoners.csv');
    $second = $service->propose($id, $path, 'text/csv', 'bewoners.csv');
    if ($first['created'] !== 3 || $second['created'] !== 0 || $second['existing'] !== 3) throw new RuntimeException('idempotency_failed');
    $rows = $db->query('SELECT match_status, suggested_building_id, resident_name, review_status FROM office_document_address_proposals ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== 3 || $rows[0]['match_status'] !== 'exact_candidate' || (int)$rows[0]['suggested_building_id'] !== 101 || $rows[1]['match_status'] !== 'ambiguous' || $rows[1]['suggested_building_id'] !== null || $rows[2]['match_status'] !== 'unmatched') throw new RuntimeException('matching_failed');
    foreach ($rows as $row) if ($row['review_status'] !== 'pending' || $row['resident_name'] !== 'J. Jansen') throw new RuntimeException('review_or_identity_failed');
    if ((int)$db->query('SELECT COUNT(*) FROM office_buildings')->fetchColumn() !== 3) throw new RuntimeException('canonical_building_modified');
    $review = new OfficeAddressProposalReviewService($db);
    $pending = $review->pendingForDocument($id);
    if (count($pending) !== 3) throw new RuntimeException('review_listing_failed');
    if (!$review->decide($id, (int)$pending[0]['id'], 'accepted')) throw new RuntimeException('exact_review_failed');
    if ($review->decide($id, (int)$pending[0]['id'], 'accepted')) throw new RuntimeException('double_review_allowed');
    if ($review->decide($id, (int)$pending[1]['id'], 'accepted')) throw new RuntimeException('ambiguous_acceptance_allowed');
    if (!$review->decide($id, (int)$pending[1]['id'], 'rejected')) throw new RuntimeException('ambiguous_rejection_failed');
    if ((int)$db->query('SELECT COUNT(*) FROM office_buildings')->fetchColumn() !== 3) throw new RuntimeException('review_mutated_canonical_buildings');
    echo "OFFICE_ADDRESS_PROPOSAL_MYSQL_OK\\n";
} finally {
    @unlink($path);
}
