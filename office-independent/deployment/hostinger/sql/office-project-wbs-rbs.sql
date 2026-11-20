-- BREBO Office-test WBS/RBS: additive structure and references only.
-- Existing budget, planning and governance tables remain authoritative.
CREATE TABLE IF NOT EXISTS office_project_wbs_nodes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  code VARCHAR(64) NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  revision INT UNSIGNED NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_office_wbs_project_code (project_id, code),
  UNIQUE KEY uq_office_wbs_project_node (project_id, id),
  KEY idx_office_wbs_parent (project_id, parent_id),
  CONSTRAINT fk_office_wbs_project FOREIGN KEY (project_id) REFERENCES office_projects(id),
  CONSTRAINT fk_office_wbs_parent FOREIGN KEY (project_id, parent_id) REFERENCES office_project_wbs_nodes(project_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_project_wbs_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id BIGINT UNSIGNED NOT NULL,
  wbs_node_id BIGINT UNSIGNED NOT NULL,
  source_type VARCHAR(32) NOT NULL,
  source_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_office_wbs_link (project_id, wbs_node_id, source_type, source_id),
  KEY idx_office_wbs_source (project_id, source_type, source_id),
  CONSTRAINT fk_office_wbs_link_node FOREIGN KEY (project_id, wbs_node_id) REFERENCES office_project_wbs_nodes(project_id, id),
  CONSTRAINT chk_office_wbs_source CHECK (source_type IN ('plan_item','budget_line','risk'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_project_rbs_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  code VARCHAR(64) NOT NULL,
  title VARCHAR(200) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_office_rbs_project_code (project_id, code),
  UNIQUE KEY uq_office_rbs_project_category (project_id, id),
  KEY idx_office_rbs_parent (project_id, parent_id),
  CONSTRAINT fk_office_rbs_project FOREIGN KEY (project_id) REFERENCES office_projects(id),
  CONSTRAINT fk_office_rbs_parent FOREIGN KEY (project_id, parent_id) REFERENCES office_project_rbs_categories(project_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_project_rbs_risk_links (
  project_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  risk_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (project_id, category_id, risk_id),
  KEY idx_office_rbs_risk (project_id, risk_id),
  CONSTRAINT fk_office_rbs_link_category FOREIGN KEY (project_id, category_id) REFERENCES office_project_rbs_categories(project_id, id),
  CONSTRAINT fk_office_rbs_link_risk FOREIGN KEY (risk_id) REFERENCES office_risks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
