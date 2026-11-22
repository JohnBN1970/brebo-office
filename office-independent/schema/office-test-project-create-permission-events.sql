-- Explicit Office-test only audit schema. Review and apply with privileged schema process.
-- No automatic permission grants, backfill or production writes.
CREATE TABLE office_test_project_create_permission_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actor_operator_id VARCHAR(128) NOT NULL,
  target_operator_id VARCHAR(128) NOT NULL,
  action ENUM('grant','revoke') NOT NULL,
  reason VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_office_project_permission_target (target_operator_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
