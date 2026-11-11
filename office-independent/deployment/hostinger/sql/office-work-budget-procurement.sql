-- Office-test procurement ledger, additive only. Apply after backup and schema preflight.
-- Project ACL is enforced by application services, not by this DDL.
CREATE TABLE IF NOT EXISTS office_work_budget_purchase_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id BIGINT UNSIGNED NOT NULL,
  supplier_relation_id BIGINT UNSIGNED NOT NULL,
  status ENUM('draft','issued','cancelled') NOT NULL DEFAULT 'draft',
  order_ref VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  issued_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wb_po_project_ref (project_id,order_ref),
  KEY ix_wb_po_project_status (project_id,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS office_work_budget_purchase_order_lines (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  baseline_line_id BIGINT UNSIGNED NOT NULL,
  quantity_milli BIGINT UNSIGNED NOT NULL,
  unit_price_cents BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_wb_pol_baseline (baseline_line_id),
  CONSTRAINT fk_wb_pol_order FOREIGN KEY (order_id)
    REFERENCES office_work_budget_purchase_orders(id),
  CONSTRAINT fk_wb_pol_baseline FOREIGN KEY (baseline_line_id)
    REFERENCES office_calc_work_budget_lines(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS office_work_budget_receipts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_line_id BIGINT UNSIGNED NOT NULL,
  receipt_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  quantity_milli BIGINT UNSIGNED NOT NULL,
  building_id BIGINT UNSIGNED NULL,
  zone_id BIGINT UNSIGNED NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wb_receipt_key (receipt_key),
  KEY ix_wb_receipt_line (order_line_id),
  CONSTRAINT fk_wb_receipt_order_line FOREIGN KEY (order_line_id)
    REFERENCES office_work_budget_purchase_order_lines(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
