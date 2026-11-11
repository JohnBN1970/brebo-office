-- Native Office Gantt planning. Schema only; no test records or production seed.
CREATE TABLE IF NOT EXISTS office_project_plan_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  planned_start DATE NOT NULL,
  planned_end DATE NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'planned',
  critical_note VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_office_plan_project_dates (project_id, planned_start, planned_end),
  CONSTRAINT chk_office_plan_dates CHECK (planned_end >= planned_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
