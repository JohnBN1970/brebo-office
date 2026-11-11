-- Proposed auditable acceptance evidence, NOT automatically installed.
-- An accepted CRM stage alone is never sufficient evidence of a contract.
CREATE TABLE IF NOT EXISTS office_commercial_acceptances (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 opportunity_id BIGINT UNSIGNED NOT NULL,
 quotation_id BIGINT UNSIGNED NULL,
 acceptance_kind VARCHAR(24) NOT NULL,
 evidence_document_id BIGINT UNSIGNED NULL,
 evidence_reference VARCHAR(255) NULL,
 accepted_by_operator_id BIGINT UNSIGNED NOT NULL,
 accepted_at DATETIME NOT NULL,
 contract_amount_ex_vat_cents BIGINT UNSIGNED NULL,
 idempotency_key CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_commercial_acceptance_idempotency (idempotency_key),
 UNIQUE KEY uq_commercial_acceptance_opportunity (opportunity_id),
 INDEX idx_commercial_acceptance_quotation (quotation_id),
 CONSTRAINT chk_commercial_acceptance_kind CHECK (acceptance_kind IN ('quotation','direct_order')),
 CONSTRAINT chk_commercial_acceptance_evidence CHECK (
   evidence_document_id IS NOT NULL OR
   (evidence_reference IS NOT NULL AND CHAR_LENGTH(TRIM(evidence_reference)) > 0)
 ),
 CONSTRAINT chk_commercial_acceptance_source CHECK (
   (acceptance_kind = 'quotation' AND quotation_id IS NOT NULL) OR
   (acceptance_kind = 'direct_order' AND quotation_id IS NULL)
 )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
