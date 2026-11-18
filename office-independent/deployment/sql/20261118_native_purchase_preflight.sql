-- Read-only preflight for the native purchase invoice migration.
-- Run against the intended Office-test database BEFORE applying the migration.
-- Expected for a fresh installation: all four tables absent (0 rows).
-- If any table exists, inspect SHOW CREATE TABLE before proceeding.
SELECT DATABASE() AS selected_database;
SELECT table_name, table_type
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN (
    'office_finance_invoices',
    'office_finance_invoice_lines',
    'office_finance_invoice_events',
    'office_finance_invoice_documents'
  )
ORDER BY table_name;
SELECT table_name, column_name, column_type, is_nullable, column_default
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name IN (
    'office_finance_invoices',
    'office_finance_invoice_lines',
    'office_finance_invoice_events',
    'office_finance_invoice_documents'
  )
ORDER BY table_name, ordinal_position;
-- For existing tables, manually compare columns, types and indexes with the
-- migration. CREATE TABLE IF NOT EXISTS does NOT validate existing structure.
