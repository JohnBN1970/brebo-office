-- PREPARATION ONLY. Do not run until the Office schema migration procedure approves it.
-- Keep the canonical calculation_lines table as the single line source.
-- Values are snapshots of the source quantity and adjustment at calculation time.
-- No building, recipe or project records are modified by this migration.
ALTER TABLE calculation_lines
  ADD COLUMN calc_net_quantity DECIMAL(10,4) NULL,
  ADD COLUMN calc_material_loss_percent DECIMAL(5,2) NULL,
  ADD COLUMN calc_extra_labor_hours DECIMAL(10,4) NULL,
  ADD COLUMN calc_quantity_source VARCHAR(64) NULL,
  ADD COLUMN calc_quantity_source_ref VARCHAR(128) NULL;
-- Invariant for application validation: material loss 0..100, extra hours >=0.
-- For legacy rows all fields remain NULL; quantity remains the effective quantity.
