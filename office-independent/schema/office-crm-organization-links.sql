-- BREBO Office CRM: additive organization-to-organization associations.
-- Review and migrate explicitly; no automatic activation or production changes.
-- Keep legal ownership distinct from property-management representation.
-- A contact person remains linked through office_crm_relation_contacts.
CREATE TABLE IF NOT EXISTS office_crm_organization_links (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    parent_relation_id BIGINT UNSIGNED NOT NULL,
    child_relation_id BIGINT UNSIGNED NOT NULL,
    link_type VARCHAR(32) NOT NULL,
    valid_from DATE NULL,
    valid_until DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_crm_org_link (parent_relation_id, child_relation_id, link_type),
    KEY idx_crm_org_child (child_relation_id, link_type),
    CONSTRAINT fk_crm_org_parent FOREIGN KEY (parent_relation_id) REFERENCES office_crm_relations(id),
    CONSTRAINT fk_crm_org_child FOREIGN KEY (child_relation_id) REFERENCES office_crm_relations(id),
    CONSTRAINT chk_crm_org_not_self CHECK (parent_relation_id <> child_relation_id),
    CONSTRAINT chk_crm_org_dates CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from),
    CONSTRAINT chk_crm_org_link_type CHECK (link_type IN ('subsidiary', 'branch', 'group_member'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Application write policy before enabling editing:
-- 1. Both endpoints must be company relations and active.
-- 2. Reject cycles using a transaction-safe ancestor traversal.
-- 3. For subsidiary/branch, enforce one active legal parent per child.
-- 4. Property-management/VvE relationships stay in office_crm_relation_representations.
-- 5. Revenue rollups must not change the original invoice/debtor relation.
-- 6. Mail visibility is not inherited from group membership; enforce per-message ACL.
