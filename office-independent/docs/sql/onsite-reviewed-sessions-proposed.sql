-- PROPOSAL ONLY: never execute automatically.
-- Requires DBA review, existing office_inzet_people and office_onsite_event_inbox,
-- and a separate authenticated Office review workflow before enabling sync.
CREATE TABLE office_onsite_reviewed_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  person_id BIGINT UNSIGNED NOT NULL,
  in_event_id VARCHAR(100) NOT NULL,
  out_event_id VARCHAR(100) NOT NULL,
  approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_seconds INT UNSIGNED DEFAULT NULL,
  approved_at_utc DATETIME DEFAULT NULL,
  reviewed_by BIGINT UNSIGNED DEFAULT NULL,
  review_reason TEXT DEFAULT NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_onsite_review_in (in_event_id),
  UNIQUE KEY uq_onsite_review_out (out_event_id),
  KEY idx_onsite_review_person_status (person_id, approval_status, approved_at_utc),
  CONSTRAINT fk_onsite_review_person FOREIGN KEY (person_id) REFERENCES office_inzet_people(id),
  CONSTRAINT fk_onsite_review_in FOREIGN KEY (in_event_id) REFERENCES office_onsite_event_inbox(event_id),
  CONSTRAINT fk_onsite_review_out FOREIGN KEY (out_event_id) REFERENCES office_onsite_event_inbox(event_id),
  CONSTRAINT ck_onsite_review_approved CHECK (
    (approval_status = 'approved' AND approved_at_utc IS NOT NULL AND reviewed_by IS NOT NULL AND reviewed_seconds IS NOT NULL)
    OR (approval_status <> 'approved' AND approved_at_utc IS NULL)
  )
);
