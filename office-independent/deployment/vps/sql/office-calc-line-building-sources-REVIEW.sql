-- REVIEW ONLY. Install on Office-test after backup and schema inspection.
-- Requires calculation_lines.id, office_buildings.id and office_building_elements.id.
-- Verify existing indexes, engine, FK column types and charset before applying.
-- MariaDB DDL implicitly commits; no automatic rollback.
-- Mandatory read-only preflight: run these SELECTs first and compare results.
-- Expected: three id columns exist, InnoDB engines, compatible signedness/types.
SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN
 ('calculation_lines','office_buildings','office_building_elements');
SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE
 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
 AND (TABLE_NAME='calculation_lines' AND COLUMN_NAME='id'
 OR TABLE_NAME='office_buildings' AND COLUMN_NAME='id'
 OR TABLE_NAME='office_building_elements' AND COLUMN_NAME IN ('id','building_id','revision'))
 ORDER BY TABLE_NAME,COLUMN_NAME;
SELECT TABLE_NAME,CONSTRAINT_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME
 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE()
 AND TABLE_NAME IN ('calculation_lines','office_buildings','office_building_elements')
 AND REFERENCED_TABLE_NAME IS NOT NULL;
-- STOP if any required column or type differs. The DDL below is illustrative,
-- not approved until its BIGINT UNSIGNED types match the live schema.
-- Referential integrity must be reviewed before setting the feature flag.
CREATE TABLE office_calc_line_building_sources (
  calculation_line_id BIGINT UNSIGNED NOT NULL,
  building_id BIGINT UNSIGNED NOT NULL,
  building_element_id BIGINT UNSIGNED NOT NULL,
  building_element_revision BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (calculation_line_id),
  KEY idx_calc_line_building_sources_building (building_id),
  KEY idx_calc_line_building_sources_element (building_element_id),
  CONSTRAINT fk_calc_source_line FOREIGN KEY (calculation_line_id) REFERENCES calculation_lines(id) ON DELETE CASCADE,
  CONSTRAINT fk_calc_source_building FOREIGN KEY (building_id) REFERENCES office_buildings(id),
  CONSTRAINT fk_calc_source_element FOREIGN KEY (building_element_id) REFERENCES office_building_elements(id)
) ENGINE=InnoDB;
-- Source ID types confirmed on Office-test; review existing constraints before execution.
-- Enable OFFICE_CALC_BUILDING_ELEMENT_LINK_SCHEMA_VERIFIED only after inspection.
