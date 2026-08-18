# BREBO Finance

BREBO Finance is the operational financial cockpit of BREBO Office.

## Fixed principles

- Moneybird remains the financial source administration.
- Project result, budgets and margins are controlled excluding VAT.
- Cash flow, invoices and payments are monitored including VAT.
- Every financial record belongs to a BREBO project.
- Every record is allocated to one or more central building-model objects whenever technically possible.
- An approved calculation is the commercial source, but does not automatically become the project baseline.
- The calculation is translated into a technically executable working budget.
- Only the approved working budget is frozen as the original project baseline.
- Changes are recorded as auditable mutations; historical values are never silently overwritten.
- A commitment precedes invoice approval.
- Invoice approval will use three-way matching: order, verified performance and invoice.
- VAT reverse charge and G-account payment are separate controls and are never conflated.
- G-account splits are supported for both incoming client payments and outgoing subcontractor payments.

## Calculation to working budget

The controlled transition is:

1. approved calculation and quotation;
2. transfer to a draft working budget;
3. regrouping into executable cost codes and work packages;
4. verification of quantities, labour, materials, subcontracting, logistics, risk and planning;
5. allocation to project and building-model objects;
6. review by calculator/purchaser, work planner and project manager;
7. formal approval and locking of the original working budget.

The commercial calculation therefore remains traceable while the working budget becomes the operational control instrument. Commercial allowances, mark-ups and sales structure are not silently treated as executable cost lines.

## Initial data model

- `brebo_finance_budget`: versioned project budget, including draft and approved working budgets.
- `brebo_finance_budget_line`: cost-code and work-package budget rows including VAT treatment.
- `brebo_finance_commitment`: purchase order or subcontract commitment.
- `brebo_finance_commitment_line`: detailed ordered, delivered and invoiced values.
- `brebo_finance_object_allocation`: allocation to project, cluster, dwelling, facade, room or product position.
- `brebo_finance_g_account_instruction`: approved incoming or outgoing contractual payment split.
- `brebo_finance_g_account_payment`: actual blocked-account payment and evidence matching.
- `brebo_finance_audit`: append-only change history.

## Object allocation

A contractual purchase order remains one document. Its lines may be distributed across multiple building objects. This supports orders such as one glass order covering many individual glass positions while retaining traceability in both directions.

## Next tranche

1. VAT value object and calculation service.
2. Controlled transfer from approved calculation snapshot to draft working budget.
3. Working-budget review, approval and immutable baseline snapshot.
4. Project and building financial summary queries.
5. Commitment workflow and approval rules.
6. Performance receipt and three-way invoice matching.
7. Moneybird synchronization through the BREBO integration API.

## G-account control

The G-account percentage is not assumed or derived from VAT. It must be agreed and approved per contract, commitment or invoice basis. BREBO Office records the basis, percentage or fixed amount, regular-account share, G-account share, masked IBAN reference, actual bank transaction and supporting evidence. Variances remain blocked for review.

## Digital Controller

The BREBO Digital Controller orchestrates deterministic controls before AI analysis. It seals the exact forecast, open findings, payment exceptions, budget state and model provenance into a hashed evidence package. AI output is rejected when that package changes. The Digital Controller may signal, predict, prioritize and block unsafe workflow transitions, but never grants its own financial approval.

### Decision briefing

Each scheduled run also produces a concise decision briefing from the sealed deterministic findings. Critical and high findings are never hidden. Medium findings are limited to the most urgent watch items; low findings remain available in the complete evidence package.

Every briefing item follows the fixed chain: deviation, cause, financial consequence, control measure, owner and deadline. Amounts are shown only when they exist in verified source evidence. Missing ownership or deadlines are reported explicitly; the system never invents them.

### Control-action lifecycle

A financial finding stays active until its cause is demonstrably controlled. An authorized user assigns an owner and deadline. Only that owner may submit a resolution, including a note and explicit evidence. A different authorized user must accept or reject the resolution under the four-eyes principle. Every transition stores before and after hashes in the append-only financial audit trail. A new deterministic scan preserves pending verification and reopens a verified finding if the underlying exception returns.
