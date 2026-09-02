# BREBO Finance completion gap analysis

## Definition of Finance complete

BREBO Finance is complete when both operational money flows can be followed and acted on from Office without bypassing the existing control services.

Outgoing chain:

`working budget -> commitment -> invoice capture/coding -> verified performance -> three-way match -> VAT/G-account control -> approval -> payment release -> execution -> forecast/project result -> controller findings`

Incoming chain:

`approved billable term -> Moneybird sales invoice -> recurring Moneybird reconciliation -> debtor monitoring -> receipt/dispute -> project cash position -> forecast/project result -> controller findings`

Moneybird remains the accounting source administration. BREBO Office remains the operational control and project steering layer.

## What already exists

The Finance module already contains the underlying control model and services for working budgets, commitments, performance receipts and evidence, invoice matching/blockers, G-account instructions, payment releases, phase gates, euro trace, cash-flow forecasting, billing/debtor control, changes, failure cost, supplier scorecards, contract obligations, scenarios and the Digital Controller.

The main remaining product gap is therefore not a new financial model. It is the Office operating layer that exposes those controls as coherent workflows and closes the missing ingestion/reconciliation steps between existing services.

## Gap 1 - Budget and commitment operating layer

The outgoing chain cannot be considered operational until an authorized Office user can review and lock the working budget and create/maintain commitments through the existing Finance services.

The operating layer must expose working-budget review/approval and commitment creation/maintenance without duplicating `WorkingBudgetApprovalManager`, `CommitmentManager` or phase-gate rules.

## Gap 2 - Purchase invoice capture, coding and control workbench

The current purchase-invoice detail page mainly shows invoice metadata. Imported/mail-routed invoices can arrive as unmatched headers without project, invoice lines or linked commitment lines. A real invoice therefore needs a controlled path from intake into the existing three-way-match engine.

The workbench must support and show the following chain from one invoice:

1. invoice identity, supplier and source;
2. capture/import of invoice lines from the authoritative source/document, with traceability to that source;
3. project coding/assignment;
4. commitment and commitment-line coding per invoice line;
5. invoice amounts excluding VAT, VAT and including VAT and reconciliation of line totals to the header;
6. ordered/committed value;
7. verified performance and supporting evidence;
8. explicit execution of the existing three-way match after coding/evidence changes;
9. match state and variance codes per line and on the invoice;
10. VAT treatment and regular/G-account split;
11. active blockers with financial impact and priority;
12. payment-release state and four-eyes approval state;
13. euro trace and project-finance navigation.

Coding/capture must never silently invent a project, commitment or invoice line. Uncertain coding remains visibly unmatched and blocks payment. The workbench must not duplicate matching, payment or phase-gate business rules. Existing Finance services, including the three-way-match and payment-release managers, remain authoritative.

## Gap 3 - Receivables operating layer and reconciliation

Finance also needs a complete revenue-side workflow. An authorized Office user must be able to move from an approved billable instalment/term into the Moneybird sales-invoice state and then monitor collection, disputes and overdue receivables from Office.

Creation-time synchronization is not sufficient: subsequent Moneybird changes such as payment, partial payment, credit, overdue state or dispute/collection-relevant status must be reconciled back into BREBO Office on a recurring or event-driven basis. The synchronization must be idempotent, auditable and safe to retry, and must update the existing billing/debtor control model rather than introduce a second sales-invoice administration.

Moneybird remains authoritative for the accounting invoice/payment state. BREBO Office remains authoritative for operational follow-up, project impact and controller findings.

## Build order

### Slice A - Working budget and commitment workbench

Expose working-budget review/locking and commitment creation/maintenance using the existing managers and permissions. This makes the first stages of the controlled outgoing chain operable from Office.

### Slice B - Purchase invoice capture and coding workbench

Extend the invoice detail page into the operational intake/coding cockpit. Provide controlled line ingestion/capture, project assignment and commitment-line coding for imported/mail-routed invoices before matching can succeed.

Required behavior:

- preserve source document/import identifiers and provenance;
- create or update invoice lines without silent duplication;
- require explicit project coding where no trustworthy project is known;
- link invoice lines to existing commitment lines rather than creating hidden procurement data;
- show uncoded/unmatched lines prominently;
- reconcile captured line totals against invoice header totals;
- degrade safely when optional tables/fields are not yet present during deployment transitions.

### Slice C - Read-only purchase invoice control view

Show invoice lines, commitment references, match/blocker state, verified performance, evidence, G-account split and payment-release state in one control cockpit.

### Slice D - Controlled matching, performance and payment actions

Expose existing actions through normal Drupal forms/controllers:

- register/attach performance evidence;
- verify performance under existing permissions and four-eyes rules;
- execute/re-execute the existing three-way match after invoice coding, commitment or performance changes;
- request payment release;
- approve/reject payment release;
- mark an approved release executed with Moneybird/bank reference.

No action may reimplement `PaymentReleaseManager`, phase-gate, G-account or three-way-match rules in a controller.

### Slice E - Payables work queues

Add actionable queues for uncoded invoices, unmatched invoice lines, missing commitment/order, missing or rejected performance evidence, invoices approaching due date while blocked, pending payment-release approvals, and approved releases awaiting execution.

### Slice F - Billing and receivables workbench

Expose the revenue chain through the existing billing/debtor services:

- review billable instalments/terms;
- approve billable value;
- show the resulting Moneybird sales-invoice reference/status;
- monitor due and overdue receivables;
- surface receipts, partial receipts and open balances;
- register/show operational disputes and their effect on collectability;
- connect debtor state to project cash flow and controller findings.

### Slice G - Recurring Moneybird receivables reconciliation

Add an inbound synchronization path for sales invoices after initial creation. Prefer a verified webhook/event mechanism when the integration supports it; otherwise use a bounded scheduled reconciliation job. In either case:

- refresh open/recent Moneybird sales invoices and payment state;
- map paid, partially paid, overdue, credited/cancelled and relevant dispute/collection signals into the existing BREBO billing/debtor model;
- call the existing synchronization logic rather than writing parallel status rules;
- make processing idempotent and retry-safe;
- persist audit/provenance and last successful synchronization information;
- surface synchronization failures in Finance/controller work queues;
- never overwrite BREBO operational dispute notes with accounting-source data.

### Slice H - Project result closure

Connect controlled payables and reconciled receivables back into project finance, cash-flow forecast, current forecast and Digital Controller findings so that a user can trace every material euro from baseline/earned value to payment or receipt.

## Acceptance rule

Finance is not considered complete merely because an API/service exists. A normal authorized Office user must be able to initiate the controlled financial chain, capture and code a real incoming purchase invoice, move it through authoritative three-way matching and payment controls, understand the current payable and receivable state, see why a transaction is blocked, navigate to the underlying evidence, perform the permitted next action, and see later Moneybird receipt/payment-state changes reconciled back into Office without leaving the BREBO Office workflow.
