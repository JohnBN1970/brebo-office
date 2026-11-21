-- Office-test only. Install explicitly after backup and schema review.
-- Stores a per-blueprint-step test setting, with a server-side audit trail.
CREATE TABLE IF NOT EXISTS office_test_blueprint_approval_modes (
  blueprint_key VARCHAR(128) NOT NULL,
  step_id VARCHAR(64) NOT NULL,
  approval_mode ENUM('single','four_eyes') NOT NULL DEFAULT 'four_eyes',
  changed_by_operator_id BIGINT UNSIGNED NOT NULL,
  changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (blueprint_key,step_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS office_test_blueprint_approval_mode_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  blueprint_key VARCHAR(128) NOT NULL,
  step_id VARCHAR(64) NOT NULL,
  old_mode VARCHAR(16) NOT NULL,
  new_mode VARCHAR(16) NOT NULL,
  actor_operator_id BIGINT UNSIGNED NOT NULL,
  changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_approval_mode_events (blueprint_key,step_id,changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
