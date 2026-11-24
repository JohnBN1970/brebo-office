-- REVIEW ONLY. Install on Office-test after backup and schema inspection.
-- Requires calculation_lines.id, office_buildings.id and office_building_elements.id.
-- Verify existing indexes, engine, FK column types and charset before applying.
-- MariaDB DDL implicitly commits; no automatic rollback.
CREATE TABLE office_calc_line_building_sources (
  calculation_line_id BIGINT UNSIGNED NOT NULL,
  building_id BIGINT UNSIGNED NOT NULL,
  building_element_id BIGINT UNSIGNED NOT NULL,
  building_element_revision INT NOT NULL,
  PRIMARY KEY (calculation_line_id),
  KEY idx_calc_line_building_sources_building (building_id),
  KEY idx_calc_line_building_sources_element (building_element_id)
) ENGINE=InnoDB;
-- FK constraints must be added only after confirming actual source column types.
-- Enable OFFICE_CALC_BUILDING_ELEMENT_LINK_SCHEMA_VERIFIED only after inspection.
