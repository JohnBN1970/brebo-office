-- TEST ONLY: explicit expected dwelling count per project.
-- Apply manually after backing up the isolated Office test database.
-- Never infer a target from the project title.
CREATE TABLE IF NOT EXISTS office_project_dwelling_targets (
  project_id BIGINT UNSIGNED NOT NULL,
  expected_dwelling_count INT UNSIGNED NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (project_id),
  CONSTRAINT fk_office_pdt_project FOREIGN KEY (project_id)
    REFERENCES office_projects(id) ON DELETE RESTRICT,
  CONSTRAINT chk_office_pdt_count CHECK (expected_dwelling_count > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
