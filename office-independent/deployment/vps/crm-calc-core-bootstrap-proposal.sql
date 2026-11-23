-- REVIEW ONLY: Office-test VPS canonical Calc core bootstrap proposal.
-- Do NOT execute automatically. Requires DBA approval, backup, and schema inventory review.
-- Target database: brebo_office_test. Existing project Calc records must never be overwritten.
-- Canonical column contract derived from OfficeCalcCrmDraftLineRepository and its SQLite tests.
-- This is a minimum foundation; optional materials/labor/collaboration columns require
-- their existing separate migrations before enabling corresponding feature flags.
DELIMITER //
CREATE PROCEDURE brebo_test_calc_core_bootstrap()
BEGIN
 DECLARE v INT DEFAULT 0;
 IF DATABASE() IS NULL OR DATABASE()<>'brebo_office_test' THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='BLOCKED wrong database';
 END IF;
 SELECT COUNT(*) INTO v FROM information_schema.TABLES
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN
  ('calculations','calculation_versions','calculation_lines');
 IF v<>0 THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='BLOCKED existing Calc core; do not replace';
 END IF;
 SELECT COUNT(*) INTO v FROM information_schema.TABLES
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_calc_crm_drafts' AND ENGINE='InnoDB';
 IF v<>1 THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='BLOCKED Deal Calc identity not installed';
 END IF;
 CREATE TABLE calculations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  office_project_id BIGINT UNSIGNED NULL,
  office_calculation_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(100) NOT NULL,
  title VARCHAR(255) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'draft',
  PRIMARY KEY(id),
  UNIQUE KEY uq_calc_office_identity(office_calculation_id),
  UNIQUE KEY uq_calc_code(code),
  KEY ix_calc_project(office_project_id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 CREATE TABLE calculation_versions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  calculation_id BIGINT UNSIGNED NOT NULL,
  version_no INT UNSIGNED NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'draft',
  direct_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  PRIMARY KEY(id),
  UNIQUE KEY uq_calc_version(calculation_id,version_no),
  CONSTRAINT fk_calc_version_calc FOREIGN KEY(calculation_id) REFERENCES calculations(id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 CREATE TABLE calculation_lines (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  version_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  line_type VARCHAR(32) NOT NULL DEFAULT 'item',
  code VARCHAR(100) NOT NULL,
  description VARCHAR(500) NOT NULL,
  unit VARCHAR(32) NULL,
  quantity DECIMAL(18,4) NOT NULL,
  labour_unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  material_unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  equipment_unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  subcontracting_unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  other_unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,
  PRIMARY KEY(id),
  UNIQUE KEY uq_calc_line_code(version_id,code),
  KEY ix_calc_line_order(version_id,sort_order),
  CONSTRAINT fk_calc_line_version FOREIGN KEY(version_id) REFERENCES calculation_versions(id)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
END//
DELIMITER ;
-- NO CALL by design. Explicitly reviewed operator execution only.
-- CALL brebo_test_calc_core_bootstrap();
-- DROP PROCEDURE brebo_test_calc_core_bootstrap;
-- Never enable Calc feature flags until all required extensions and E2E checks pass.
