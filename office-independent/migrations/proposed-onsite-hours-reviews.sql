-- REVIEW AND EXPLICIT APPROVAL REQUIRED. Never executed by HTTP or deployment.
-- Single immutable review ledger used by Office and the device bootstrap.
CREATE TABLE office_onsite_hours_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  person_id BIGINT UNSIGNED NOT NULL,
  project_id VARCHAR(64) NOT NULL,
  zone_id VARCHAR(64) NOT NULL,
  in_event_id VARCHAR(100) NOT NULL,
  out_event_id VARCHAR(100) NOT NULL,
  approval_status ENUM('approved','rejected') NOT NULL,
  reviewed_seconds INT UNSIGNED NOT NULL,
  reviewer_operator_id VARCHAR(128) NOT NULL,
  approved_at DATETIME NULL COMMENT 'UTC',
  reviewed_at DATETIME NOT NULL COMMENT 'UTC',
  reason TEXT NULL,
  revision INT UNSIGNED NOT NULL DEFAULT 1,
  request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  request_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  UNIQUE KEY uq_onsite_review_in (in_event_id),
  UNIQUE KEY uq_onsite_review_out (out_event_id),
  UNIQUE KEY uq_onsite_review_request (request_key),
  KEY ix_onsite_review_person_status (person_id,approval_status,approved_at),
  KEY ix_onsite_review_project (project_id,reviewed_at),
  CONSTRAINT fk_onsite_hours_person FOREIGN KEY (person_id) REFERENCES office_inzet_people(id),
  CONSTRAINT fk_onsite_hours_in FOREIGN KEY (in_event_id) REFERENCES office_onsite_event_inbox(event_id),
  CONSTRAINT fk_onsite_hours_out FOREIGN KEY (out_event_id) REFERENCES office_onsite_event_inbox(event_id),
  CONSTRAINT ck_onsite_hours_seconds CHECK (reviewed_seconds<=86400),
  CONSTRAINT ck_onsite_hours_approval CHECK (
    (approval_status='approved' AND approved_at IS NOT NULL)
    OR (approval_status='rejected' AND approved_at IS NULL AND reviewed_seconds=0 AND reason IS NOT NULL AND CHAR_LENGTH(TRIM(reason))>0)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
