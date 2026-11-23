-- SUPERSEDED / BLOCKED 2026-11-23: Do NOT execute or CALL procedures from this proposal.
-- This historical design does not implement the current canonical identity contract:
-- office_calc_calculations.id <-> office_calc_crm_drafts.calculation_id
-- and calculations.office_calculation_id -> office_calc_calculations.id.
-- Source of truth for review: deployment/vps/sql/office-crm-calc-shared-identity-REVIEW.sql
-- Feature flags remain disabled; migration requires separately approved runbook.
--
-- BREBO Office-test VPS: REVIEW-ONLY migration proposal, NOT automatically executed.
-- Target: brebo_office_test exclusively. Requires DBA review, backup, maintenance window.
-- This installs ONLY CRM revision and Deal-to-Calc identity; canonical Calc core is a separate phase.
-- NEVER run on production, the legacy Hostinger database, or via CI.
DELIMITER //
CREATE PROCEDURE brebo_test_deal_calc_identity_migration()
BEGIN
  DECLARE v_count INT DEFAULT 0;
  DECLARE v_revision INT DEFAULT 0;
  DECLARE v_drafts INT DEFAULT 0;
  DECLARE v_bad INT DEFAULT 0;
  IF DATABASE() IS NULL OR DATABASE() <> 'brebo_office_test' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='BLOCKED: wrong database';
  END IF;
  SELECT COUNT(*) INTO v_count FROM information_schema.TABLES
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_crm_opportunities' AND ENGINE='InnoDB';
  IF v_count <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='BLOCKED: CRM table missing or wrong engine';
  END IF;
  SELECT COUNT(*) INTO v_count FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_crm_opportunities'
     AND COLUMN_NAME='id' AND COLUMN_TYPE='bigint(20) unsigned' AND IS_NULLABLE='NO';
  IF v_count <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='BLOCKED: unexpected CRM ID type';
  END IF;
  SELECT COUNT(*) INTO v_revision FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_crm_opportunities' AND COLUMN_NAME='revision';
  SELECT COUNT(*) INTO v_drafts FROM information_schema.TABLES
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_calc_crm_drafts';
  IF v_revision > 0 OR v_drafts > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='BLOCKED: existing revision or draft schema; review manually';
  END IF;
  SELECT COUNT(*) INTO v_bad FROM information_schema.TABLES
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_calc_crm_acceptance_transfers' AND ENGINE='InnoDB';
  IF v_bad <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='BLOCKED: existing transfer table not verified';
  END IF;
  -- MySQL DDL commits implicitly. A tested backup and operator approval are mandatory.
  ALTER TABLE office_crm_opportunities
    ADD COLUMN revision BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER currency;
  CREATE TABLE office_calc_crm_drafts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    opportunity_id BIGINT UNSIGNED NOT NULL,
    crm_revision BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    project_id BIGINT UNSIGNED NULL,
    PRIMARY KEY(id),
    UNIQUE KEY uq_calc_crm_draft_opportunity(opportunity_id),
    KEY idx_calc_crm_draft_project(project_id),
    CONSTRAINT fk_calc_crm_draft_opportunity FOREIGN KEY(opportunity_id)
      REFERENCES office_crm_opportunities(id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
END//
DELIMITER ;
-- INTENTIONALLY NO CALL. The operator must explicitly review and invoke
-- CALL brebo_test_deal_calc_identity_migration();
-- DROP PROCEDURE brebo_test_deal_calc_identity_migration;
-- Then run crm-calc-schema-preflight.php --check. Full preflight remains BLOCKED
-- until canonical calculations, calculation_versions and calculation_lines exist.
