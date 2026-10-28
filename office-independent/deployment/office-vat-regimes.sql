-- Native Office settings storage. Populate only from approved Office settings; no default tax rate.
CREATE TABLE IF NOT EXISTS office_vat_regimes (
  code VARCHAR(80) NOT NULL PRIMARY KEY,
  label VARCHAR(160) NOT NULL,
  rate DECIMAL(9,4) NOT NULL,
  treatment VARCHAR(40) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
