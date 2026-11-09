-- Run only against the approved Office database after backup and schema preflight.
-- Personal layout only: never store role assignments or authorization grants here.
CREATE TABLE IF NOT EXISTS office_dashboard_preferences (
  operator_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  layout_json TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
