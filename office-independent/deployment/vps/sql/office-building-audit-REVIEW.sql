-- REVIEW ONLY: verify operator/building ID types and test database before installation.
CREATE TABLE office_building_audit_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 building_id BIGINT UNSIGNED NOT NULL,
 operator_id BIGINT UNSIGNED NOT NULL,
 event_type VARCHAR(32) NOT NULL,
 context_type VARCHAR(32) NOT NULL,
 context_id BIGINT UNSIGNED NULL,
 before_json JSON NULL,
 after_json JSON NULL,
 created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 KEY idx_building_audit_history (building_id,id),
 KEY idx_building_audit_context (context_type,context_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
