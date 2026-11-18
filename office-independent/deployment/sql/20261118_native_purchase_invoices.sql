-- BREBO Office native purchase invoice schema (VPS test).
-- Additive only. Run after checking SHOW TABLES and taking a database backup.
-- The application owns purchase invoices; Moneybird remains bank-read-only.
CREATE TABLE IF NOT EXISTS office_finance_invoices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  supplier_id BIGINT UNSIGNED NULL,
  supplier_name VARCHAR(255) NOT NULL,
  invoice_number VARCHAR(128) NOT NULL,
  invoice_date DATE NULL,
  due_date DATE NULL,
  currency CHAR(3) NOT NULL DEFAULT 'EUR',
  subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  vat_total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  total DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  status VARCHAR(24) NOT NULL DEFAULT 'draft',
  created_by VARCHAR(128) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_finance_purchase_status (status, invoice_date),
  INDEX idx_finance_purchase_owner (created_by, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS office_finance_invoice_lines (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  invoice_id BIGINT UNSIGNED NOT NULL,
  line_number INT UNSIGNED NOT NULL,
  description VARCHAR(2000) NOT NULL,
  quantity DECIMAL(13,3) NOT NULL DEFAULT 0,
  unit VARCHAR(20) NOT NULL DEFAULT '',
  unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
  net_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  vat_rate DECIMAL(6,3) NOT NULL DEFAULT 0,
  vat_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  office_project_id BIGINT UNSIGNED NULL,
  INDEX idx_finance_purchase_line_invoice (invoice_id, line_number),
  INDEX idx_finance_purchase_line_project (office_project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS office_finance_invoice_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  invoice_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(64) NOT NULL,
  operator_id VARCHAR(128) NOT NULL,
  event_data LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_finance_purchase_event_invoice (invoice_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS office_finance_invoice_documents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  invoice_id BIGINT UNSIGNED NOT NULL,
  document_id BIGINT UNSIGNED NULL,
  document_reference VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_finance_purchase_document_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
