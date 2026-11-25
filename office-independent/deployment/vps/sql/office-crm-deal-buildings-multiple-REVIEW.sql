-- REVIEW ONLY (#2165). Never run automatically.
-- Requires Office-test backup, exact schema verification, maintenance window,
-- and compatible readers/writers deployed together. Existing rows are preserved.
-- Verify current PRIMARY KEY is exactly (opportunity_id), and both FKs exist.
-- MariaDB DDL implicitly commits; do not assume transactional rollback.
ALTER TABLE office_crm_deal_buildings
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (opportunity_id, building_id);
-- Verify row count, all original pairs, foreign keys and building index afterward.
-- Do not revert to single-column PK while any deal has multiple buildings.
