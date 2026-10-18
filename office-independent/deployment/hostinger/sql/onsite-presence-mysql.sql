-- BREBO Office OnSite v1: independent MySQL 8 schema.
-- Run explicitly on the intended Office database after backup and review.
-- Never execute this migration from a mobile HTTP request.
CREATE TABLE IF NOT EXISTS onsite_device_tokens (
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  employee_id VARCHAR(191) NOT NULL,
  revoked_at VARCHAR(35) NULL,
  INDEX onsite_device_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS onsite_project_zone_assignments (
  employee_id VARCHAR(191) NOT NULL,
  project_id VARCHAR(32) NOT NULL,
  zone_id VARCHAR(32) NOT NULL,
  valid_from VARCHAR(35) NOT NULL,
  valid_until VARCHAR(35) NULL,
  active TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (employee_id, project_id, zone_id, valid_from),
  INDEX onsite_assignment_zone (project_id, zone_id, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS onsite_event_inbox (
  event_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  employee_id VARCHAR(191) NOT NULL,
  project_id VARCHAR(32) NOT NULL,
  zone_id VARCHAR(32) NOT NULL,
  kind VARCHAR(3) NOT NULL,
  occurred_at VARCHAR(35) NOT NULL,
  received_at VARCHAR(35) NOT NULL,
  INDEX onsite_event_employee_time (employee_id, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
