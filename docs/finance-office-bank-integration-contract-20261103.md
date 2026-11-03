# Financecenter: Office-led bank integration contract (2026-11-03)

## Confirmed product decision
BREBO Office initiates and owns invoices. Moneybird is an accounting sink and returns accounting references and status **for Office-originated invoices only**. Do not add a generic Moneybird invoice import/list synchronization. ABN AMRO provides bank account balances, booked activities and payment execution/status. Office owns reconciliation, operational controls and audit.

## Existing code found
- `office-independent/src/Finance/OfficeFinanceApplication.php`: authorized Office payables/read workflows; does not itself wire bank insight.
- `office-independent/src/Finance/OfficeFinanceReadConnection.php`: legacy read-only finance source, not a bank API.
- `integration-api/src/abnamro-insight.ts`: balance and BOOKED activity GET adapter, including pagination cursor.
- `integration-api/src/abnamro.ts`: payment submission boundary; accepted submission is not bank execution.
- `web/modules/custom/brebo_finance/src/Service/BankTransactionReconciliationManager.php`: existing reconciliation logic to reuse, not duplicate.
- `docs/BREBO_CONTINUITEIT_FINANCE_2026-09-29.md`: bank production activation still requires verified OAuth2/mTLS, endpoints, status polling and uncertain-submit safety.

## Integration sequence (not a declaration of live readiness)
1. Trace integration-api authenticated routes, bank credential provisioning and deployed runtime. Do not expose bank secrets to the Office browser or PHP view.
2. Introduce an Office-side bank gateway contract with authorized account scope, read-only balance and booked activity retrieval, explicit pagination, cursor persistence, deduplication and audit.
3. Adapt existing reconciliation manager behind the Office-independent Finance boundary; never copy matching rules into controllers.
4. Display bank account, balance freshness, activity inbox and unmatched exceptions in the existing Financecenter, not a second payment cockpit.
5. Wire Office-originated invoice POST to Moneybird-specific readback by stored external reference; prohibit generic Moneybird invoice GET import.
6. Reuse payment releases, mandate-required approval, controller gates, immutable batches and SEPA fallback. Keep payment initiation disabled until provider certification and end-to-end tests pass.
7. Validate with isolated test fixtures: authorization, missing configuration fail-closed, duplicate activities, paging, changed IBAN, unrecognized external payments, partial payment, uncertain submission, Moneybird drift and bank-vs-accounting mismatch.

## Non-negotiable acceptance
- No invoice is created in Office from Moneybird GET.
- No bank balance or activity is visible without explicit Office finance and account authorization.
- Bank activity ingestion is idempotent, audit-visible and never marks an invoice paid from submission alone.
- A payment cannot bypass approval, four-eyes or account re-verification.
- No production bank action or deployment without verified credentials, contract and human authorization.
- A working Moneybird POST path must remain unchanged.

## Current verification boundary
This document records the inspected repository design only. It does **not** assert that the ABN AMRO production connection or Financecenter UI is operational. Next PR should contain the actual Office gateway, routes, fixtures and tests before deployment.

## Confirmed SEPA batch lifecycle (2026-11-03)
A SEPA batch is mandatory as an **internal payment artifact**, not a user download. Office generates exact pain.001 XML bytes from approved Office payables, stores those bytes encrypted in private storage with a stable batch ID and immutable SHA-256, and records the invoice/recipient/amount snapshot. The browser receives only an artifact ID, status and authorized summary; never bank credentials or unrestricted file URLs.

The controller checks totals, recipient snapshots and mandate-based authorization (single, dual, or dual above a configured threshold). Once released, the same sealed bytes are submitted through the server-to-server Integration API to ABN AMRO. The integration request hash is SHA-256 of the **exact XML bytes**; the batch seal is tracked separately. A durable unique submission reservation must commit **before** the network call. An ambiguous bank response blocks retries until provider reconciliation.

Bank acknowledgement means **submitted**, not **paid**. Office records bank references and later independently confirms execution against booked bank activity before marking the corresponding payable reconciled. Do not re-generate or mutate XML after release; amendments require a new batch and a new approval cycle.

This is an architectural contract, not a statement that encrypted artifact storage, mandate UI, ABN AMRO provider onboarding, bank status polling or the full end-to-end path is implemented. Production initiation remains disabled pending explicit authorization and tests.
