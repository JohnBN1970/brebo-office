CREATE TABLE IF NOT EXISTS office_finance_sales_drafts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  operator_id VARCHAR(64) NOT NULL,
  office_project_id BIGINT UNSIGNED NOT NULL,
  customer_name VARCHAR(160) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'draft',
  lines_json JSON NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_sales_drafts_project (office_project_id, id),
  INDEX idx_sales_drafts_operator (operator_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
