# BREBO Office CSS migration contract

## Scope
Office-independent is the target. Drupal theme and production website are out of scope. The first centralized stylesheet is `public/assets/css/office-tokens.css`. It is intentionally not loaded until asset serving and visual parity have been verified.

## Migration sequence
1. Verify public asset URL, cache headers and deployment mapping on office-test.
2. Move shared layout CSS to a shared stylesheet with scoped selectors.
3. Migrate Projecten, Inzet, CRM, FinanceCenter and Gebouwen one module at a time.
4. Remove obsolete inline styles only after screenshot comparisons at desktop and mobile widths.
5. Add a CI baseline gate after the first reviewed inventory, allowing explicitly documented dynamic CSS variables.

## Acceptance
- Same screen appearance and responsive behavior before and after migration.
- No changes to authorization, routes, database or business logic.
- No CSS selector leaks across modules.
- No regression in Gantt, week calendar, table editing or full-screen calculator.
- Document baseline totals and verify new inline CSS does not increase.

## Safety
Do not enable a new global stylesheet for all pages in one change. CSS must be scoped and loaded only after route and visual checks.
