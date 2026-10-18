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

-- Office-owned active employee and geofence projection. Populate only from
-- verified Office records using a privileged administrative process.
CREATE TABLE IF NOT EXISTS onsite_employees (
  employee_id VARCHAR(191) NOT NULL PRIMARY KEY,
  display_name VARCHAR(191) NOT NULL,
  language VARCHAR(16) NOT NULL DEFAULT 'nl',
  active TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS onsite_projects (
  project_id VARCHAR(32) NOT NULL PRIMARY KEY,
  name VARCHAR(191) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS onsite_zones (
  zone_id VARCHAR(32) NOT NULL,
  project_id VARCHAR(32) NOT NULL,
  name VARCHAR(191) NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  radius_metres DECIMAL(8,2) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (zone_id, project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Operator-issued one-time device activation credentials. Store SHA-256 only;
-- issue via an authenticated Office workflow, never from an anonymous request.
CREATE TABLE IF NOT EXISTS onsite_activation_tokens (
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  employee_id VARCHAR(191) NOT NULL,
  expires_at VARCHAR(35) NOT NULL,
  consumed_at VARCHAR(35) NULL,
  INDEX onsite_activation_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dedicated enrollment permission, separate from CRM edit and project read.
-- Default deny: no rows are created by the migration.
CREATE TABLE IF NOT EXISTS onsite_enrollment_operator_grants (
  operator_id VARCHAR(128) NOT NULL PRIMARY KEY,
  active TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Immutable record of each authorized device activation issue.
-- Secrets are never written to audit rows.
CREATE TABLE IF NOT EXISTS onsite_activation_issue_audit (
  audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  operator_id VARCHAR(128) NOT NULL,
  employee_id VARCHAR(191) NOT NULL,
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  issued_at VARCHAR(35) NOT NULL,
  expires_at VARCHAR(35) NOT NULL,
  INDEX onsite_issue_audit_employee (employee_id, issued_at),
  UNIQUE KEY onsite_issue_audit_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
