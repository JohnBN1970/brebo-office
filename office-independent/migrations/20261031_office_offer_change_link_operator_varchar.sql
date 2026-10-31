-- Apply manually only after confirming the table exists and taking a verified backup.
-- Keeps existing numeric IDs as their textual representation.
-- Do not execute from a web request.
ALTER TABLE office_offer_change_links
    MODIFY COLUMN linked_by_operator_id VARCHAR(128) NOT NULL;
