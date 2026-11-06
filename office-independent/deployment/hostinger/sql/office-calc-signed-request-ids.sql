-- Apply only after staging database preflight and explicit approval.
-- Keep request IDs at least 180 seconds; never remove still-valid IDs.
CREATE TABLE IF NOT EXISTS office_calc_signed_request_ids (
  request_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (request_id),
  KEY idx_calc_request_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
