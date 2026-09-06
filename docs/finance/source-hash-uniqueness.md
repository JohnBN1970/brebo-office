# Finance source-hash uniqueness

BREBO Finance treats `brebo_finance_purchase_invoice.source_hash` as the stable replay identity for source-neutral supplier-invoice intake.

Production preflight on 2026-09-06 proved 383/383 purchase invoices have a source hash and zero duplicate source-hash groups. The production migration therefore rechecks duplicates immediately before creating the `source_hash_unique` database key and refuses to modify the schema when any duplicate exists.

The enforcement workflow is idempotent: an existing key is retained, a missing key is created only after the duplicate preflight, and the key is verified after creation.

The application-layer replay check remains in place as the first line of defense; the unique database key is the final race-condition guard.
