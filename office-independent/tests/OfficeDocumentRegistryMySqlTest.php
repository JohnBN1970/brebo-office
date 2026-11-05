<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Documents/OfficeDocumentRegistry.php';
use Brebo\Office\Documents\OfficeDocumentRegistry;

$dsn = getenv('OFFICE_DOCUMENT_TEST_DSN');
$user = getenv('OFFICE_DOCUMENT_TEST_USER');
$password = getenv('OFFICE_DOCUMENT_TEST_PASSWORD');
if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:') || !is_string($user) || !is_string($password)) {
    fwrite(STDERR, "ISOLATED_MYSQL_TEST_CONFIGURATION_REQUIRED\n");
    exit(2);
}
$db = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$name = $db->query('SELECT DATABASE()')->fetchColumn();
if (!is_string($name) || !str_starts_with($name, 'office_documents_ci_')) {
    throw new RuntimeException('refusing_non_isolated_database');
}
foreach (OfficeDocumentRegistry::schema() as $statement) {
    $db->exec($statement);
}
$registry = new OfficeDocumentRegistry($db);
$hash = hash('sha256', 'isolated office document test');
$key = 'originals/' . substr($hash, 0, 2) . '/' . $hash;
$id = $registry->register(['storage_provider' => 'office_private', 'storage_key' => $key, 'sha256' => $hash, 'size' => 29], 'Test', 'test.txt', 'text/plain', 'ci');
$registry->attachContext($id, 'project', 'TEST-1', 'origin');
$registry->attachContext($id, 'building', 'BUILDING-1', 'durable');
$registry->attachContext($id, 'project', 'TEST-1', 'origin');
$id2 = $registry->register(['storage_provider' => 'office_private', 'storage_key' => $key, 'sha256' => $hash, 'size' => 29], 'Test 2', 'test.txt', 'text/plain', 'ci');
$count = (int) $db->query('SELECT COUNT(*) FROM office_document_originals')->fetchColumn();
$documents = (int) $db->query('SELECT COUNT(*) FROM office_documents')->fetchColumn();
$contexts = (int) $db->query('SELECT COUNT(*) FROM office_document_contexts')->fetchColumn();
if ($count !== 1 || $documents !== 2 || $contexts !== 2 || $id2 === $id) {
    throw new RuntimeException('document_registry_integration_failed');
}
echo "OFFICE_DOCUMENT_REGISTRY_MYSQL_OK\n";
