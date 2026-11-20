-- Additive Office-test mapping for accepted direct orders without a calculation.
-- Do not modify existing calculation acceptance transfers.
CREATE TABLE IF NOT EXISTS office_crm_direct_project_transfers (
  opportunity_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL,
  relation_id BIGINT UNSIGNED NOT NULL,
  accepted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (opportunity_id),
  UNIQUE KEY uq_direct_project (project_id),
  KEY idx_direct_relation (relation_id),
  CONSTRAINT fk_direct_opportunity FOREIGN KEY (opportunity_id) REFERENCES office_crm_opportunities(id),
  CONSTRAINT fk_direct_project FOREIGN KEY (project_id) REFERENCES office_projects(id),
  CONSTRAINT fk_direct_relation FOREIGN KEY (relation_id) REFERENCES office_crm_relations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
