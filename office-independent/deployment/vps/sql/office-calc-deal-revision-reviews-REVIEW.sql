-- REVIEW ONLY: additive Office-test migration. Do not execute until backup and schema preflight pass.
-- Preflight: verify office_calc_crm_drafts.id and office_operator_accounts.id are
-- BIGINT UNSIGNED, both tables use InnoDB, and referenced id columns are indexed.
-- Verify against information_schema.COLUMNS and information_schema.TABLES on Office-test.
-- If either parent id differs, adjust child column types before applying; never alter parent data.
-- Verify no pre-existing table with incompatible schema before deployment.
CREATE TABLE office_calc_deal_revision_reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    draft_id BIGINT UNSIGNED NOT NULL,
    deal_revision BIGINT UNSIGNED NOT NULL,
    reviewed_by BIGINT UNSIGNED NOT NULL,
    reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_calc_deal_review (draft_id, deal_revision),
    CONSTRAINT fk_calc_deal_review_draft FOREIGN KEY (draft_id) REFERENCES office_calc_crm_drafts(id),
    CONSTRAINT fk_calc_deal_review_actor FOREIGN KEY (reviewed_by) REFERENCES office_operator_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
