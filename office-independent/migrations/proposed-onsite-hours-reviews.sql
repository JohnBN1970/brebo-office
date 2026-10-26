-- Proposed Office-native hours review ledger; DBA review required before execution.
-- Geofence events remain evidence; only authorized Office review may write here.
CREATE TABLE office_onsite_hours_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  person_id BIGINT UNSIGNED NOT NULL,
  in_event_id VARCHAR(100) NOT NULL,
  out_event_id VARCHAR(100) NOT NULL,
  approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_seconds INT UNSIGNED NULL,
  reviewer_person_id BIGINT UNSIGNED NULL,
  approved_at DATETIME NULL COMMENT 'UTC',
  reviewed_at DATETIME NULL COMMENT 'UTC',
  reason TEXT NULL,
  revision INT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_onsite_review_in (in_event_id),
  UNIQUE KEY uq_onsite_review_out (out_event_id),
  KEY ix_onsite_review_person_status (person_id, approval_status, approved_at),
  CONSTRAINT fk_onsite_review_person FOREIGN KEY (person_id) REFERENCES office_inzet_people(id),
  CONSTRAINT fk_onsite_review_in FOREIGN KEY (in_event_id) REFERENCES office_onsite_event_inbox(event_id),
  CONSTRAINT fk_onsite_review_out FOREIGN KEY (out_event_id) REFERENCES office_onsite_event_inbox(event_id),
  CONSTRAINT chk_onsite_review_approved CHECK (
    (approval_status = 'approved' AND approved_at IS NOT NULL AND reviewer_person_id IS NOT NULL AND reviewed_seconds IS NOT NULL)
    OR (approval_status <> 'approved' AND approved_at IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
