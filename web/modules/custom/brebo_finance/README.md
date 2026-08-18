# BREBO Finance

BREBO Finance is the operational financial cockpit of BREBO Office.

## Fixed principles

- Moneybird remains the financial source administration.
- Project result, budgets and margins are controlled excluding VAT.
- Cash flow, invoices and payments are monitored including VAT.
- Every financial record belongs to a BREBO project.
- Every record is allocated to one or more central building-model objects whenever technically possible.
- An approved calculation becomes an immutable zero budget.
- Changes are recorded as auditable mutations; historical values are never silently overwritten.
- A commitment precedes invoice approval.
- Invoice approval will use three-way matching: order, verified performance and invoice.

## Initial data model

- `brebo_finance_budget`: versioned project budget.
- `brebo_finance_budget_line`: cost-code and work-package budget rows including VAT treatment.
- `brebo_finance_commitment`: purchase order or subcontract commitment.
- `brebo_finance_commitment_line`: detailed ordered, delivered and invoiced values.
- `brebo_finance_object_allocation`: allocation to project, cluster, dwelling, facade, room or product position.
- `brebo_finance_audit`: append-only change history.

## Object allocation

A contractual purchase order remains one document. Its lines may be distributed across multiple building objects. This supports orders such as one glass order covering many individual glass positions while retaining traceability in both directions.

## Next tranche

1. VAT value object and calculation service.
2. Budget import from an approved calculation snapshot.
3. Project and building financial summary queries.
4. Commitment workflow and approval rules.
5. Performance receipt and three-way invoice matching.
6. Moneybird synchronization through the BREBO integration API.
