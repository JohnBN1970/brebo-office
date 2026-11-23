-- REVIEW ONLY. Do not execute without backup, schema preflight and approval.
-- One canonical building per Deal, including Deals created without a Lead.
-- No project records and no guessed address matching.
CREATE TABLE IF NOT EXISTS office_crm_deal_buildings (
    opportunity_id BIGINT UNSIGNED NOT NULL,
    building_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (opportunity_id),
    KEY idx_crm_deal_buildings_building (building_id),
    CONSTRAINT fk_crm_deal_buildings_deal FOREIGN KEY (opportunity_id)
      REFERENCES office_crm_opportunities(id),
    CONSTRAINT fk_crm_deal_buildings_building FOREIGN KEY (building_id)
      REFERENCES office_buildings(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
