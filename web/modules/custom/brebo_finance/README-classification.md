# Moneybird supplier classification dry run

The supplier diagnosis workflow classifies unique Moneybird purchase-invoice contacts without mutating Drupal or Moneybird.

- `SUPPLIER`: strong business identity is present; candidate for central BREBO Organization creation/linking.
- `RECEIPT`: explicit, narrow merchant rule; no CRM Organization is created and a generic transaction category is proposed.
- `REVIEW`: insufficient or ambiguous business identity; requires a human decision before any CRM mutation.

Initial explicit receipt rules include `. Turnhout` -> `Tankstation` and `Action*` -> `Winkel`.

The classifier deliberately prefers `REVIEW` over guessing. Repeated transactions alone never promote an unknown merchant to a CRM supplier.
