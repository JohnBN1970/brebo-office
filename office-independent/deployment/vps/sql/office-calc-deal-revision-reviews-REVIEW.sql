-- Additive migration; apply only after Office-test schema and backup verification.
CREATE TABLE office_calc_deal_revision_reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    draft_id BIGINT UNSIGNED NOT NULL,
    deal_revision BIGINT UNSIGNED NOT NULL,
    reviewed_by BIGINT UNSIGNED NOT NULL,
    reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_calc_deal_review (draft_id, deal_revision),
    CONSTRAINT fk_calc_deal_review_draft FOREIGN KEY (draft_id) REFERENCES office_calc_crm_drafts(id),
    CONSTRAINT fk_calc_deal_review_actor FOREIGN KEY (reviewed_by) REFERENCES office_operator_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
