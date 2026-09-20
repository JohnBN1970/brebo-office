# BREBO Finance receivables foundation

The receivables dunning flow derives its financial truth from `brebo_finance_sales_invoice`.

Escalation is blocked for paid, credited, cancelled, disputed, manually-held, or payment-arrangement invoices. The configurable dunning schedule is stored in `brebo_finance.receivables` and the first implementation stores auditable dunning events in the Drupal key-value store until the dedicated event table is introduced.
