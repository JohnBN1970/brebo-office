-- BREBO Office TEST: quantities per housing-type scope, independent of Calc.
-- Review and back up isolated test database before applying manually.
-- Never infer quantities or unit prices from dwelling counts.
CREATE TABLE IF NOT EXISTS office_scope_quantity_lines (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  scope_id BIGINT UNSIGNED NOT NULL,
  line_code VARCHAR(128) NOT NULL,
  description VARCHAR(255) NOT NULL,
  unit VARCHAR(16) NOT NULL,
  quantity_per_dwelling DECIMAL(18,4) NOT NULL,
  unit_price_ex_vat DECIMAL(18,4) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_office_sql_scope_code (scope_id, line_code),
  CONSTRAINT fk_office_sql_scope FOREIGN KEY (scope_id)
    REFERENCES office_project_calculation_scopes(id) ON DELETE RESTRICT,
  CONSTRAINT chk_office_sql_qty CHECK (quantity_per_dwelling >= 0),
  CONSTRAINT chk_office_sql_price CHECK (unit_price_ex_vat IS NULL OR unit_price_ex_vat >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
