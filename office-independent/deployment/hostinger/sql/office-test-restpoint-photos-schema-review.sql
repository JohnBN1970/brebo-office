-- Review only. Verify parent schemas before installation.
CREATE TABLE IF NOT EXISTS office_restpoint_photos (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 restpoint_id BIGINT UNSIGNED NOT NULL,
 document_id BIGINT UNSIGNED NOT NULL,
 photo_role VARCHAR(16) NOT NULL,
 created_by VARCHAR(128) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_restpoint_photo (restpoint_id,document_id),
 CONSTRAINT fk_rp_photo_restpoint FOREIGN KEY (restpoint_id) REFERENCES office_quality_checks(id),
 CONSTRAINT fk_rp_photo_document FOREIGN KEY (document_id) REFERENCES office_documents(id),
 CONSTRAINT chk_rp_photo_role CHECK (photo_role IN ('before','after','review'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
