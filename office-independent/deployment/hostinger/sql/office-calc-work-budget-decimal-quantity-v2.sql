-- Office-test ONLY. Reviewed additive migration for fractional calculation quantities.
-- Does not change or reinterpret the legacy BIGINT quantity field.
-- Existing locked baselines remain immutable; v2 values are written only for
-- new accepted transfers after application and preflight.
ALTER TABLE office_calc_work_budget_lines
  ADD COLUMN quantity_decimal DECIMAL(18,4) NULL AFTER quantity,
  ADD COLUMN unit_cost_decimal DECIMAL(18,4) NULL AFTER unit_cost_cents,
  ADD COLUMN budget_decimal DECIMAL(22,4) NULL AFTER budget_cents;
