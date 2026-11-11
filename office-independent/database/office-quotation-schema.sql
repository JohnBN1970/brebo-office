-- BREBO Office native quotation tables (proposal only; not applied automatically)
-- MySQL 8 / utf8mb4. No Drupal dependency.
CREATE TABLE IF NOT EXISTS office_quotations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 relation_id BIGINT UNSIGNED NOT NULL,
 opportunity_id BIGINT UNSIGNED NULL,
 contact_id BIGINT UNSIGNED NULL,
 building_id BIGINT UNSIGNED NULL,
 calculation_id BIGINT UNSIGNED NULL,
 calculation_version INT UNSIGNED NULL,
 quotation_type VARCHAR(24) NOT NULL,
 title VARCHAR(255) NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'draft',
 currency CHAR(3) NOT NULL DEFAULT 'EUR',
 version INT UNSIGNED NOT NULL DEFAULT 1,
 amount_ex_vat_cents BIGINT UNSIGNED NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 approved_by BIGINT UNSIGNED NULL,
 approved_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_quotation_relation (relation_id),
 INDEX idx_quotation_opportunity (opportunity_id),
 INDEX idx_quotation_status (status),
 CONSTRAINT chk_quotation_type CHECK (quotation_type IN ('project','mjop','regie','compact')),
 CONSTRAINT chk_quotation_status CHECK (status IN ('draft','review','approved','sent','accepted','rejected','withdrawn'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_quotation_sections (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 quotation_id BIGINT UNSIGNED NOT NULL,
 section_key VARCHAR(64) NOT NULL,
 sort_order INT UNSIGNED NOT NULL,
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 body MEDIUMTEXT NOT NULL,
 provenance VARCHAR(64) NOT NULL DEFAULT 'manual',
 UNIQUE KEY uq_quotation_section (quotation_id,section_key),
 CONSTRAINT fk_quotation_section FOREIGN KEY (quotation_id) REFERENCES office_quotations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_quotation_instalments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 quotation_id BIGINT UNSIGNED NOT NULL,
 sequence_number INT UNSIGNED NOT NULL,
 trigger_description VARCHAR(255) NOT NULL,
 percentage_basis_points INT UNSIGNED NOT NULL,
 amount_ex_vat_cents BIGINT UNSIGNED NOT NULL,
 UNIQUE KEY uq_quotation_instalment (quotation_id,sequence_number),
 CONSTRAINT fk_quotation_instalment FOREIGN KEY (quotation_id) REFERENCES office_quotations(id) ON DELETE CASCADE,
 CONSTRAINT chk_quotation_percentage CHECK (percentage_basis_points BETWEEN 1 AND 10000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_quotation_assets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 quotation_id BIGINT UNSIGNED NOT NULL,
 asset_kind VARCHAR(16) NOT NULL,
 source_id BIGINT UNSIGNED NOT NULL,
 sort_order INT UNSIGNED NOT NULL DEFAULT 0,
 display_text TEXT NULL,
 included_in_pdf TINYINT(1) NOT NULL DEFAULT 1,
 CONSTRAINT fk_quotation_asset FOREIGN KEY (quotation_id) REFERENCES office_quotations(id) ON DELETE CASCADE,
 CONSTRAINT chk_quotation_asset_kind CHECK (asset_kind IN ('reference','attachment'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_quotation_versions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 quotation_id BIGINT UNSIGNED NOT NULL,
 version INT UNSIGNED NOT NULL,
 snapshot_json JSON NOT NULL,
 snapshot_sha256 CHAR(64) NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_quotation_version (quotation_id,version),
 CONSTRAINT fk_quotation_version FOREIGN KEY (quotation_id) REFERENCES office_quotations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
