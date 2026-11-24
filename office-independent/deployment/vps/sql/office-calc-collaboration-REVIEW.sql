-- REVIEW ONLY: Office-test, backup and verify existing column definitions first.
CREATE TABLE IF NOT EXISTS office_calc_crm_draft_collaborators (
 draft_id BIGINT UNSIGNED NOT NULL,
 operator_id BIGINT UNSIGNED NOT NULL,
 granted_by BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (draft_id,operator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS office_calc_crm_draft_changes (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 draft_id BIGINT UNSIGNED NOT NULL,
 actor_id BIGINT UNSIGNED NOT NULL,
 event_kind VARCHAR(64) NOT NULL,
 reason TEXT NOT NULL,
 before_json LONGTEXT NOT NULL,
 after_json LONGTEXT NOT NULL,
 content_hash CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL,
 KEY ix_draft_history (draft_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE calculation_lines
 ADD COLUMN IF NOT EXISTS calc_net_quantity DECIMAL(18,4) NULL,
 ADD COLUMN IF NOT EXISTS calc_material_loss_percent DECIMAL(7,2) NULL,
 ADD COLUMN IF NOT EXISTS calc_extra_labor_hours DECIMAL(18,4) NULL,
 ADD COLUMN IF NOT EXISTS calc_quantity_source VARCHAR(64) NULL,
 ADD COLUMN IF NOT EXISTS calc_quantity_source_ref VARCHAR(255) NULL;
