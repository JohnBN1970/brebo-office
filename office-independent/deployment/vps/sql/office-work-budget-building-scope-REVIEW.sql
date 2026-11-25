-- REVIEW ONLY. Verify baseline line and building PK types before installation.
-- Existing baseline lines remain untouched. Missing scope row means unverified,
-- not general project costs. Explicit NULL building_id means general costs.
CREATE TABLE office_work_budget_building_scope (
 baseline_line_id BIGINT UNSIGNED NOT NULL,
 building_id BIGINT UNSIGNED NULL,
 PRIMARY KEY (baseline_line_id),
 KEY idx_work_budget_scope_building (building_id),
 CONSTRAINT fk_work_budget_scope_line FOREIGN KEY (baseline_line_id) REFERENCES office_calc_work_budget_lines(id),
 CONSTRAINT fk_work_budget_scope_building FOREIGN KEY (building_id) REFERENCES office_buildings(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
