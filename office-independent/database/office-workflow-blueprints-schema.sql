-- Draft schema only: review tenant model and migration before installation.
CREATE TABLE IF NOT EXISTS office_workflow_blueprints (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 blueprint_key VARCHAR(64) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_workflow_tenant_key (tenant_id, blueprint_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_workflow_blueprint_versions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 blueprint_id BIGINT UNSIGNED NOT NULL,
 version_number INT UNSIGNED NOT NULL,
 status ENUM('draft','published','retired') NOT NULL DEFAULT 'draft',
 definition_json JSON NOT NULL,
 definition_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 published_by BIGINT UNSIGNED NULL,
 published_at DATETIME NULL,
 UNIQUE KEY uq_workflow_version (blueprint_id, version_number),
 KEY idx_workflow_status (blueprint_id, status),
 CONSTRAINT fk_workflow_version_blueprint FOREIGN KEY (blueprint_id)
   REFERENCES office_workflow_blueprints(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_workflow_case_bindings (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL,
 version_id BIGINT UNSIGNED NOT NULL,
 case_type VARCHAR(64) NOT NULL,
 case_id VARCHAR(100) NOT NULL,
 current_node VARCHAR(64) NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_workflow_case (tenant_id, case_type, case_id),
 CONSTRAINT fk_workflow_case_version FOREIGN KEY (version_id)
   REFERENCES office_workflow_blueprint_versions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
