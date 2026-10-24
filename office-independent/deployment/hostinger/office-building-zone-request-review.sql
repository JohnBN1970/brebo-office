-- REVIEW ONLY. Do not run automatically.
-- First inspect office-building-zone-preflight.php on the isolated Office test DB.
-- Add nullable metadata to the EXISTING building-owned zone table; no new zone master.
-- Existing zones retain their IDs, geometry, building ownership and revision.
ALTER TABLE office_building_zones
  ADD COLUMN request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  ADD COLUMN request_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  ADD UNIQUE KEY uq_office_building_zone_request (request_key);
-- Application rollback: disable OFFICE_TEST_ZONE_WRITE_ENABLED first and keep the
-- metadata columns. Do not remove request metadata after accepting writes: it
-- preserves retry identities. DDL requires its own reviewed test migration.

