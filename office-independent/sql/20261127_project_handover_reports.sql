-- Office-test only. Apply through the established reviewed migration process.
CREATE TABLE IF NOT EXISTS office_project_handover_reports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  notes TEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  created_by_operator_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_handover_project (project_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
