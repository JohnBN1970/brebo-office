-- REVIEW ONLY: install after parent schema and test scope verification.
CREATE TABLE IF NOT EXISTS office_restpoint_document_markers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    restpoint_id BIGINT UNSIGNED NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    original_id BIGINT UNSIGNED NOT NULL,
    page_number INT UNSIGNED NOT NULL,
    x_millionths INT UNSIGNED NOT NULL,
    y_millionths INT UNSIGNED NOT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by VARCHAR(128) NOT NULL,
    updated_by VARCHAR(128) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_restpoint_marker (restpoint_id,active),
    KEY idx_document_marker (document_id,original_id,page_number,active),
    CONSTRAINT fk_rp_marker_restpoint FOREIGN KEY (restpoint_id) REFERENCES office_quality_checks(id),
    CONSTRAINT fk_rp_marker_document FOREIGN KEY (document_id) REFERENCES office_documents(id),
    CONSTRAINT fk_rp_marker_original FOREIGN KEY (original_id) REFERENCES office_document_originals(id),
    CONSTRAINT chk_rp_marker_position CHECK (x_millionths <= 1000000 AND y_millionths <= 1000000 AND page_number >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS office_restpoint_document_marker_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    marker_id BIGINT UNSIGNED NOT NULL,
    revision INT UNSIGNED NOT NULL,
    actor_operator_id VARCHAR(128) NOT NULL,
    event_type VARCHAR(32) NOT NULL,
    snapshot_json LONGTEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rp_marker_revision (marker_id,revision),
    CONSTRAINT fk_rp_marker_event FOREIGN KEY (marker_id) REFERENCES office_restpoint_document_markers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Application must validate restpoint kind, project/building and document ACL,
-- document/original identity, page range, and revision atomically before writes.
