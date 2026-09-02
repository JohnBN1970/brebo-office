# BREBO Finance completion gap analysis

## Definition of Finance complete

BREBO Finance is complete when the operational chain can be followed and acted on from Office without bypassing the existing control services:

`working budget -> commitment -> verified performance -> purchase invoice -> three-way match -> VAT/G-account control -> approval -> payment release -> execution -> forecast/project result -> controller findings`

Moneybird remains the accounting source administration. BREBO Office remains the operational control and project steering layer.

## What already exists

The Finance module already contains the underlying control model and services for working budgets, commitments, performance receipts and evidence, invoice matching/blockers, G-account instructions, payment releases, phase gates, euro trace, cash-flow forecasting, billing/debtor control, changes, failure cost, supplier scorecards, contract obligations, scenarios and the Digital Controller.

The main remaining product gap is therefore not a new financial model. It is the Office operating layer that exposes those controls as one coherent workflow.

## Gap 1 - Purchase invoice workbench

The current purchase-invoice detail page mainly shows invoice metadata. It does not yet present the complete control state that already exists underneath it.

The workbench must make the following chain visible from one invoice:

1. invoice identity, supplier, project and source;
2. invoice amounts excluding VAT, VAT and including VAT;
3. invoice lines and linked commitment lines;
4. ordered/committed value;
5. verified performance and supporting evidence;
6. three-way match state and variance codes;
7. VAT treatment and regular/G-account split;
8. active blockers with financial impact and priority;
9. payment-release state and four-eyes approval state;
10. euro trace and project-finance navigation.

The page must not duplicate matching, payment or phase-gate business rules. Existing Finance services remain authoritative.

## Build order

### Slice A - Read-only control workbench

Extend the invoice detail page into a read-only control cockpit. Show invoice lines, commitment references, match/blocker state, verified performance, G-account split and payment-release state. Missing tables/optional fields must degrade safely because production installations can be between post-updates.

### Slice B - Controlled actions

Expose existing actions through normal Drupal forms/controllers:

- register/attach performance evidence;
- verify performance under existing permissions and four-eyes rules;
- request payment release;
- approve/reject payment release;
- mark an approved release executed with Moneybird/bank reference.

No action may reimplement `PaymentReleaseManager`, phase-gate or matching rules in a controller.

### Slice C - Finance work queues

Add actionable queues for unmatched invoices, missing commitment/order, missing or rejected performance evidence, invoices approaching due date while blocked, pending payment-release approvals, and approved releases awaiting execution.

### Slice D - Project result closure

Connect the controlled invoice/payment state back into project finance, cash-flow forecast, current forecast and Digital Controller findings so that a user can trace every material euro from baseline to payment.

## Acceptance rule

Finance is not considered complete merely because an API/service exists. A normal authorized Office user must be able to understand the current financial state, see why a transaction is blocked, navigate to the underlying evidence, perform the permitted next action, and see the resulting state change without leaving the BREBO Office workflow.
