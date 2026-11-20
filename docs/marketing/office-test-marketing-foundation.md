# BREBO Marketing — Office-test foundation

## Context
External marketing is managed internally in BREBO Office on the Hostinger VPS. The public website remains a separate publication and intake surface. Existing Office source: `office-independent/public/index.php` (isolated Office-test router), `office-independent/public/office-home.php` (session-protected home/navigation), and existing CRM lead handlers.

## Non-negotiable boundaries
- `develop` remains integration source; changes are prepared on a feature branch and reviewed before merge.
- Only `/office-test` and the `office-test.brebobv.nl` host are eligible for first rollout. No production or public website changes.
- Reuse existing operator session validation and server-side authorization. Navigation visibility is not authorization.
- CRM owns relations, leads, opportunities and assignments; Intake owns incoming requests. Marketing stores campaign metadata and attribution only.
- No second CRM, no duplicate customer records, no separate marketing database unless an explicit architecture review requires it.

## First implementation slice
1. Add a GET-only, session-protected `/office-test/marketing` overview handler, using the existing Office session conventions and an explicit feature gate. No writes initially.
2. Add Marketing to the Office navigation only for authorized operators, preserving all existing navigation.
3. Define campaign fields: ID, title, channel, owner, start/end dates, budget, status, source code, timestamps.
4. Define attribution contract: intake ID, campaign ID/source code, first-touch channel and captured timestamp. Preserve original source data; do not overwrite CRM relations.
5. Prepare safe idempotent schema migration with backup and rollback procedure; implement campaign CRUD only after verification.
6. Build reporting on traceable intake/CRM links: requests, qualified leads, opportunities, quote value and realized revenue, with documented attribution rules.

## Acceptance gates
- Unauthenticated access denied; feature off returns unavailable; unauthorized users cannot access via direct URL.
- Existing CRM and Intake workflows unchanged; campaign source survives request-to-lead handoff.
- No duplicate leads on repeated intake; all reported totals have traceable source records.
- Tests and CI green, VPS deployment/readiness verified, functional Office-test acceptance documented.
- Explicit separate approval required for production activation.

## Status
Specification committed on a feature branch only. No executable module, schema migration, deployment or production change is claimed.
