-- Install only beside the verified canonical SALES article tables.
-- Does not create, copy or replace suppliers, articles or prices.
CREATE TABLE IF NOT EXISTS brebo_material_import_reviews (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 catalog_import_id INT UNSIGNED NOT NULL,
 actor_id VARCHAR(128) NOT NULL,
 source_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 source_reference VARCHAR(255) NOT NULL,
 source_json MEDIUMTEXT NOT NULL,
 reviewed_rows_json MEDIUMTEXT NOT NULL,
 reviewed_at BIGINT UNSIGNED NOT NULL,
 UNIQUE KEY uq_material_review_import (catalog_import_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
