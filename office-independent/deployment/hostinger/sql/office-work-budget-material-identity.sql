-- Office-test only. Additive article identity sidecar for immutable Calc baseline lines.
-- Apply only after backup and migration preflight. No changes to locked baseline rows.
CREATE TABLE IF NOT EXISTS office_work_budget_material_identity (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  baseline_line_id BIGINT UNSIGNED NOT NULL,
  article_code VARCHAR(191) NOT NULL,
  specification VARCHAR(500) NOT NULL,
  unit VARCHAR(32) NOT NULL,
  building_id BIGINT UNSIGNED NULL,
  zone_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_work_budget_material_line (baseline_line_id),
  KEY ix_work_budget_material_article (article_code, unit),
  CONSTRAINT fk_work_budget_material_baseline_line FOREIGN KEY (baseline_line_id)
    REFERENCES office_calc_work_budget_lines (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
