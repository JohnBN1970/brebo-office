# Office dashboard suite

The existing `/office-test/mijn-dashboard` route provides views `projects`, `finance`, `quality`, `builder`, and `wallboard`. It retains Office sessions, active-account checks, project membership and module permissions. The existing preferences table now holds a versioned map of up to ten named dashboards with up to six widgets each. The legacy preference API remains available. Saves use a session-bound CSRF token, same-origin POST and a compare-and-swap revision.

`OfficeDashboardCatalog` defines labels, units, drill-down routes and a server-owned monitor allowlist. `OfficeDashboardKpis` reuses native project, planning, approved-hours, Finance and governance readers. Missing sources remain null. The Finance view reuses `OfficeFinanceDashboard`; bank API transports are not invoked. A booked opening balance is not labelled current available cash. Incomplete realisation and liquidity sources remain visibly unavailable.

The wallboard endpoint accepts `format=json` only in the wallboard view. Private financial values, individual hours, project titles and saved board names are not serialized. It refreshes every minute, can cycle saved boards and supports fullscreen. Failed refreshes and hidden tabs clear the last values. Sessions and permissions are rechecked on every request; there are no public share tokens.

Day/night/system controls are injected in the shared Office application bar. The browser stores only the theme selection. System follows `prefers-color-scheme`, including changes while the page is open. CSS and JavaScript are maintained under `office-independent/resources`; scripts receive exact CSP hashes instead of an unsafe-inline script permission.

## Acceptance

- `OfficeDashboardSuiteTest.php`: monetary and time formatting, missing values, monitor privacy, live project scope, user-owned saved views and stale-write rejection.
- `OfficeDashboardDomTest.cjs`: three theme states, live system changes, storage failure, keyboard reordering, drag-and-drop and fail-closed monitor refresh.
- Existing navigation, Finance dashboard and personal-dashboard boundary tests remain applicable.
- The existing central deployment registers/verifies the additive preference schema, enables the test-only flags and exercises authenticated HTTP screens, saving, CSRF rejection, ownership and monitor JSON with the existing bounded fixture accounts.

## Explicit source limitations

Native inspection and process-handbook aggregate readers are absent from the inspected source and are shown as unavailable. Multi-context documents are excluded until every context can be authorized. Work-budget total realisation is unavailable where the existing source is not reconciled. These limitations must not be described as completed integrations. Office-test browser acceptance is required before declaring the suite delivered.
