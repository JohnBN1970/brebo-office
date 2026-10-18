# BREBO OnSite -> independent Office: test cutover checklist

Status: source implementation only. No production deployment or activation authorized.

## Endpoint alignment
- Production iOS base URL: `https://office.brebobv.nl`, client appends `/api/onsite/v1/...`.
- Isolated Hostinger test router: `https://brebobv.nl/office-test`, client appends `/api/onsite/v1/...`; confirm the generated URL and Apache rewrite on the real host.
- Test router explicitly rejects `office.brebobv.nl` and requires its own `office_calc_test` database and HTTP access gate.
- Do not point test devices at production or reuse old SBOffice device tokens.

## Verified source implementation
- `POST /api/onsite/v1/presence`: token authentication, assignment authorization, event validation, idempotent inbox. Disabled by default using `OFFICE_ONSITE_PRESENCE_ENABLED` and `OFFICE_ONSITE_SCHEMA_VERIFIED`.
- Explicit MySQL migration: `deployment/hostinger/sql/onsite-presence-mysql.sql`. Apply only to the intended isolated database after backup; do not migrate from web requests.
- CI includes MySQL persistence and HTTP rejection tests.

## Missing before an iPhone field test
1. Implement independent Office `POST /api/onsite/v1/link/request`, `POST /link/verify`, `POST /activate`, and `GET /bootstrap`. They are required by the installed iOS client and are NOT implemented in the independent Office router.
2. Use verified employee identity and a secure enrollment/verification flow; never allow public self-issuance of device tokens.
3. Supply bootstrap projects, active assigned zones, coordinates and radius from authoritative Office records; no guessed coordinates or numeric fixture IDs.
4. Configure and review isolated test database and HTTP access gate. Provision test token/assignments only through a privileged authenticated administration flow.
5. Test the exact externally accessible URLs, JSON response shapes, 401/403/503 behavior, revocation, replay, offline retry and audit. Keep presence as pending review, never automatically payable hours.
6. Obtain explicit approval before merge, deployment, schema migration, enabling feature flags or registering a live device.

## Suggested first controlled test
Project address: Rodeweg 37, 2920 Kalmthout. Requested radius: 100 metres. Confirm project ID, employee ID, geocoded zone center, assignment, consent and access in Office before provisioning. Test entry/exit, delayed upload and duplicate delivery. Do not store raw location trails.
