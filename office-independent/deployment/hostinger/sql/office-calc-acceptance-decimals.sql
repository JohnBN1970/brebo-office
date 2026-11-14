-- Explicit installation migration, never executed by a web request.
-- Apply before accepting native fractional quantities/four-decimal EUR prices.
-- Existing whole quantities and cent prices retain their numeric values.
-- 20 integer digits preserve the complete former BIGINT UNSIGNED range.
ALTER TABLE office_crm_calc_accepted_lines
    MODIFY quantity DECIMAL(24,4) UNSIGNED NOT NULL,
    MODIFY unit_cost_cents DECIMAL(22,2) UNSIGNED NOT NULL;
ALTER TABLE office_calc_work_budget_lines
    MODIFY quantity DECIMAL(24,4) UNSIGNED NOT NULL,
    MODIFY unit_cost_cents DECIMAL(22,2) UNSIGNED NOT NULL;
-- budget_cents remains an integer: round half up once after quantity * unit price.
-- Never reverse this migration while fractional accepted/budget lines exist.
