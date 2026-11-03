# BREBO Office Inspectie 1.0 — foundation

Status: repository-only draft; not deployed and not activated.

## Principle
An inspection belongs to a canonical Office building; a project association is optional.
Observations are the source of truth. Reports are generated derivatives.

## Current scope
- Review-only SQL DDL: inspections, findings, evidence metadata.
- Repository storage: create inspection, add and list findings.
- Explicit five-part finding model: observation, assessment framework, technical assessment, consequences, remedy.
- No Drupal dependency.

## Required before activation
1. Add an explicit migration and schema verification flag for isolated Office-test.
2. Wire Office session, CSRF, project/building authorization and audit logging before any HTTP endpoint.
3. Implement evidence upload in private storage with MIME, size, and malware checks.
4. Add inspector UI and structured dictation review; never publish AI-generated technical claims without approval.
5. Add versioned DOCX/PDF rendering, logo assets, immutable finalized snapshots and approval workflow.
6. Acceptance: Overtoom 209 inspection → approved expert report in same working day.

No production routes, tables or feature flags are changed by this PR.
