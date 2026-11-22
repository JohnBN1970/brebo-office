<?php
declare(strict_types=1);

namespace Brebo\Office\Documents;

/** Additive, review-only DDL. Never run implicitly from a web request. */
final class OfficeAddressProposalSchema
{
    public static function statements(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS office_document_address_proposals (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                document_id BIGINT UNSIGNED NOT NULL,
                source_location VARCHAR(255) NOT NULL,
                evidence_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                address_line VARCHAR(255) NOT NULL,
                postal_code VARCHAR(32) NOT NULL,
                city VARCHAR(160) NOT NULL,
                resident_name VARCHAR(255) NULL,
                resident_email VARCHAR(255) NULL,
                resident_phone VARCHAR(255) NULL,
                suggested_building_id BIGINT UNSIGNED NULL,
                match_status VARCHAR(32) NOT NULL,
                review_status VARCHAR(32) NOT NULL DEFAULT 'pending',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_document_evidence (document_id, evidence_hash),
                INDEX idx_review (review_status, match_status),
                INDEX idx_suggested_building (suggested_building_id),
                CONSTRAINT fk_document_address_source FOREIGN KEY (document_id) REFERENCES office_documents(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
    }
}
