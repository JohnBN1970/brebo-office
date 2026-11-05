<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Documents/OfficeDocumentRegistry.php';

use Brebo\Office\Documents\OfficeDocumentRegistry;

$sql = implode("\n", OfficeDocumentRegistry::schema());
foreach (['office_document_originals', 'office_documents', 'office_document_contexts', 'office_document_sources', 'UNIQUE KEY uniq_sha256', 'FOREIGN KEY'] as $needle) {
    if (!str_contains($sql, $needle)) {
        throw new RuntimeException('schema_contract_missing: ' . $needle);
    }
}
if (str_contains(strtolower($sql), 'drupal')) {
    throw new RuntimeException('drupal_dependency_forbidden');
}
echo "OFFICE_DOCUMENT_REGISTRY_CONTRACT_OK\n";
