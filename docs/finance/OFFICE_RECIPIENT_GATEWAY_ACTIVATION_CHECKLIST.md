# FinanceCenter recipient gateway — activation checklist (NO-GO)

Status: draft only. No schema deployment, service wiring, merge, or production release is authorized.

## Verified in PR #1787
- Isolated Office recipient policy, resolver, and inactive Drupal contract adapter.
- Authorization and immutable recipient-hash regression fixtures.
- PHP quality gate, Finance acceptance, and isolated recipient workflow passed on commit b62a901ad725d21ee8a5213c5bf12aff7a09801e.

## Blocking before enabling payments
1. Confirm the canonical **native Office purchase invoice** repository and its supplier relation mapping; reject non-Office and non-purchase invoices.
2. Approve an Office-owned bank account schema, G-account model, and audit/verification evidence permissions. Migration SQL in the proposal has **not** been run.
3. Store a verified **fingerprint of the exact IBAN + holder + relation + account type** at independent approval. Invalidate verification atomically on any bank-field edit; changed_at alone is not tamper-proof. Recheck this fingerprint in the read adapter before creating any first snapshot.
4. Enforce exactly one active bank account per supplier and account type under concurrent updates; resolve DATETIME-to-UTC epoch mapping and prohibit invalid dates.
5. Implement the read-only repository using parameterized queries against the approved native schema; forbid Moneybird bank-account reads and Drupal master data.
6. Confirm the existing PaymentBatchManager recipient snapshot schema (including G-account fields) before registering the adapter. Existing controller/release checks must remain fail-closed.
7. Decide whether optional four-eyes is supported; the current PaymentBatchManager and contract-control code still unconditionally enforce separate approvers. Do not imply the policy helper alone changes runtime behavior.
8. Run negative end-to-end tests for wrong supplier, stale recipient, unauthorized user, changed IBAN/holder, duplicate active account, unverified G-account, paid/cancelled invoice, duplicate release, and changed amount.
9. Obtain explicit separate approvals for schema execution, merge, and production rollout.

## Current release decision
**NO-GO** for activating the payment adapter or PR #1607. Passing isolated tests does not establish a connected, production-safe payment workflow.
