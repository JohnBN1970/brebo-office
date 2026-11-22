<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Documents/OfficeAddressProposalSchema.php';
require_once dirname(__DIR__) . '/src/Documents/OfficeAddressProposalService.php';
use Brebo\Office\Documents\OfficeAddressProposalSchema;
$sql = implode("\n", OfficeAddressProposalSchema::statements());
foreach (['office_document_address_proposals', 'UNIQUE KEY uniq_document_evidence', 'review_status', 'document_id', 'source_location', 'resident_name', 'FOREIGN KEY'] as $part) {
    if (!str_contains($sql, $part)) throw new RuntimeException('missing_contract:' . $part);
}
if (stripos($sql, 'drupal') !== false) throw new RuntimeException('drupal_dependency_forbidden');
echo "OFFICE_ADDRESS_PROPOSAL_CONTRACT_OK\n";
