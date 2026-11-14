-- Office-only access and append-only audit. Calculation rows remain canonical in calculation_lines.
CREATE TABLE IF NOT EXISTS office_calc_crm_draft_collaborators (
 draft_id BIGINT UNSIGNED NOT NULL,
 operator_id VARCHAR(128) NOT NULL,
 granted_by VARCHAR(128) NOT NULL,
 granted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(draft_id,operator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS office_calc_crm_draft_changes (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 draft_id BIGINT UNSIGNED NOT NULL,
 actor_id VARCHAR(128) NOT NULL,
 event_kind VARCHAR(32) NOT NULL,
 reason VARCHAR(1000) NOT NULL,
 before_json MEDIUMTEXT NOT NULL,
 after_json MEDIUMTEXT NOT NULL,
 content_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 INDEX idx_calc_draft_history(draft_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
