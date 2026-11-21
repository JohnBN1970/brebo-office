-- Explicitly provisioned site administrators for Office-test.
-- Never populate from project manage, CRM edit, browser role preview or a request.
-- Operator IDs correspond to authenticated office_operator_accounts.id.
CREATE TABLE IF NOT EXISTS office_test_site_administrators (
  operator_id VARCHAR(128) NOT NULL PRIMARY KEY,
  granted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  granted_by VARCHAR(128) NOT NULL,
  reason VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- No automatic grants. Provision an existing active account only after manual review.
