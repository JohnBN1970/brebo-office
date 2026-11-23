-- Explicit Deal-to-building selection. Never infer it from another project.
CREATE TABLE IF NOT EXISTS office_crm_deal_buildings (
  opportunity_id BIGINT UNSIGNED NOT NULL,
  building_id BIGINT UNSIGNED NOT NULL,
  linked_by BIGINT UNSIGNED NOT NULL,
  linked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (opportunity_id, building_id),
  CONSTRAINT fk_deal_building_opportunity FOREIGN KEY (opportunity_id) REFERENCES office_crm_opportunities(id),
  CONSTRAINT fk_deal_building_building FOREIGN KEY (building_id) REFERENCES office_buildings(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Office-test review migration: Deal scope is the commercial source before project creation.
-- Apply only after confirming database identity, backup, and CRM schema.
-- No existing project rows are changed; no sample data.
CREATE TABLE IF NOT EXISTS office_crm_deal_housing_types (
  opportunity_id BIGINT UNSIGNED NOT NULL,
  building_id BIGINT UNSIGNED NOT NULL,
  housing_type_id BIGINT UNSIGNED NOT NULL,
  dwelling_count INT UNSIGNED NOT NULL,
  revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
  updated_by BIGINT UNSIGNED NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (opportunity_id, building_id, housing_type_id),
  KEY ix_deal_housing_building (building_id, housing_type_id),
  CONSTRAINT fk_deal_housing_opportunity FOREIGN KEY (opportunity_id) REFERENCES office_crm_opportunities(id),
  CONSTRAINT fk_deal_housing_type FOREIGN KEY (housing_type_id) REFERENCES office_housing_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS office_crm_deal_housing_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  opportunity_id BIGINT UNSIGNED NOT NULL,
  building_id BIGINT UNSIGNED NOT NULL,
  housing_type_id BIGINT UNSIGNED NOT NULL,
  old_count INT UNSIGNED NULL,
  new_count INT UNSIGNED NOT NULL,
  revision BIGINT UNSIGNED NOT NULL,
  changed_by BIGINT UNSIGNED NOT NULL,
  changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_deal_housing_events (opportunity_id, changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
