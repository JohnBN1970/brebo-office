-- REVIEW ONLY. Verify InnoDB, matching unsigned bigint IDs and absence of orphaned legacy links.
-- Apply during maintenance with backup; retain legacy building_id until all readers/writers migrate.
SELECT l.id,l.building_id FROM office_crm_leads l LEFT JOIN office_buildings b ON b.id=l.building_id WHERE l.building_id IS NOT NULL AND b.id IS NULL;
CREATE TABLE office_crm_lead_buildings (
 lead_id BIGINT UNSIGNED NOT NULL,
 building_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (lead_id,building_id),
 KEY idx_lead_buildings_building (building_id),
 CONSTRAINT fk_lead_buildings_lead FOREIGN KEY (lead_id) REFERENCES office_crm_leads(id),
 CONSTRAINT fk_lead_buildings_building FOREIGN KEY (building_id) REFERENCES office_buildings(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO office_crm_lead_buildings (lead_id,building_id)
SELECT id,building_id FROM office_crm_leads WHERE building_id IS NOT NULL;
-- Verify every legacy pair is present before enabling plural readers.
SELECT l.id FROM office_crm_leads l LEFT JOIN office_crm_lead_buildings x ON x.lead_id=l.id AND x.building_id=l.building_id WHERE l.building_id IS NOT NULL AND x.lead_id IS NULL;
