-- Review manually and install only after backup and schema approval.
-- Independent of quotation creation; no existing data is modified.
CREATE TABLE IF NOT EXISTS office_quotation_rubric_approvals (
    quotation_id BIGINT UNSIGNED NOT NULL,
    rubric_key VARCHAR(200) NOT NULL,
    source_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    reviewer_id BIGINT UNSIGNED NOT NULL,
    description_checked TINYINT(1) NOT NULL DEFAULT 0,
    quantities_checked TINYINT(1) NOT NULL DEFAULT 0,
    prices_checked TINYINT(1) NOT NULL DEFAULT 0,
    approved_at DATETIME NOT NULL,
    PRIMARY KEY (quotation_id, rubric_key),
    KEY idx_rubric_reviewer (reviewer_id),
    CONSTRAINT fk_quotation_rubric_approval_quotation
        FOREIGN KEY (quotation_id) REFERENCES office_quotations(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
