# BREBO Finance supplier masterdata contract

Status: implementation step 1.

BREBO Office receives supplier masterdata only through the authenticated BREBO Integration API. Moneybird remains the accounting provider; Drupal does not receive or store the Moneybird bearer token.

For purchase invoices the normalized payload may expose only the supplier fields required for CRM and payment control: Moneybird contact ID, company/person name, postal address, country, phone, email, Moneybird customer ID, VAT number, Chamber of Commerce number, delivery method, direct debit flag and SEPA mandate fields.

Credit-card fields and unrelated provider internals are deliberately not forwarded.

The next Drupal step must use Moneybird contact ID as the external identity and may automatically fill only empty BREBO Organization masterdata fields. Existing non-empty BREBO values are not overwritten automatically; conflicts become review items.

Incidental receipt/payment descriptions are transaction data and must not automatically create CRM Organizations. Genuine business suppliers may be promoted to `brebo_organization` with relationship type `Leverancier`.
