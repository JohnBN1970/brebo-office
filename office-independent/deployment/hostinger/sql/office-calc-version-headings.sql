-- Office-test only. Heading metadata belongs to the canonical Calc version.
-- No change to immutable work-budget baselines or production.
CREATE TABLE IF NOT EXISTS office_calc_version_headings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  version_id BIGINT UNSIGNED NOT NULL,
  code_system ENUM('nlsfb','stabu','free') NOT NULL,
  heading_code VARCHAR(40) NOT NULL,
  title VARCHAR(200) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_calc_heading_code (version_id,code_system,heading_code),
  KEY ix_calc_heading_sort (version_id,sort_order,id),
  CONSTRAINT fk_calc_heading_version FOREIGN KEY (version_id)
    REFERENCES calculation_versions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS office_calc_heading_line_links (
  heading_id BIGINT UNSIGNED NOT NULL,
  calculation_line_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (calculation_line_id),
  KEY ix_calc_heading_line (heading_id,calculation_line_id),
  CONSTRAINT fk_calc_heading_link_heading FOREIGN KEY (heading_id)
    REFERENCES office_calc_version_headings(id) ON DELETE CASCADE,
  CONSTRAINT fk_calc_heading_link_line FOREIGN KEY (calculation_line_id)
    REFERENCES calculation_lines(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
