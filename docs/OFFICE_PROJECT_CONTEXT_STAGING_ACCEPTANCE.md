# Office canonical project context — staging acceptance

Status: **NOT VERIFIED**. No production enablement or feature-flag change authorized by this document.

## Scope

The existing Drupal `brebo_project` is canonical. Office must not create a second project record. The currently implemented `OfficeProjectAccess` checks **Calc-scoped** `office_calc_project_access` grants only. This is not general Projects-module authorization; do not enable the general Projects tile on this basis.

## Preconditions (staging only)

1. Confirm the exact staging Office URL, Drupal API host, and database/environment identity. Explicitly verify neither points to production or the shared Drupal sandbox by mistake.
2. Use a dedicated staging service secret (>=32 bytes) provisioned securely on both sides; never paste secrets into PRs, screenshots or logs. Confirm the Drupal endpoint uses the same secret and accepts the intended Office caller.
3. Ensure the target project exists as a Drupal `brebo_project` and record only its numeric ID for the test. Verify a distinct test operator is active and has a matching `office_calc_project_access` grant; use another operator with no grant.
4. Confirm outbound TLS hostname, DNS resolution and network egress are restricted to the intended API host. The client rejects literal IP URLs and redirects, but hostname checks alone are **not** an IP-range allowlist or DNS-rebinding protection.
5. Verify no deployment workflow is triggered by the test branch; no changes to `develop`, production flags, or production database.

## Acceptance scenarios

| Scenario | Expected result |
| --- | --- |
| Active operator + explicit Calc grant + valid project | `OfficeAuthorizedProjectContext` returns matching `brebo-office-calc-context-v1` project ID and metadata |
| Active operator without grant | Denied **before** HTTP request |
| Inactive operator with grant | Denied **before** HTTP request |
| Unknown project ID | No project data returned |
| Different project ID in API response | Context rejected |
| Invalid/expired signature or replayed request | Drupal rejects request |
| API returns non-200, invalid JSON, or oversized body | Office fails closed; no partial project card |
| Endpoint redirects or TLS validation fails | Office fails closed |

## Evidence and release gate

Capture timestamp, staging environment identity, test project ID, test case result, CI run URL and redacted HTTP status for each case. Do **not** capture shared secrets, authorization headers, personal details or complete project payloads.

Only after every scenario passes may the Calc-scoped project view be considered for staging activation. General Projects navigation requires its **own** project authorization model and separate approval. Production enablement requires explicit deployment approval and rollback plan.
