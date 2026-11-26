-- Office-test only. Verify existing columns and take backup before execution.
-- If bag_id already exists, stop and reconcile schema before applying.
ALTER TABLE office_buildings
 ADD COLUMN bag_id VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL,
 ADD COLUMN bag_verified_at DATETIME(6) NULL,
 ADD UNIQUE KEY uq_office_buildings_bag_id (bag_id);
-- NULL permits manual buildings without a verified BAG address.
SHOW CREATE TABLE office_buildings;
