# VPS Office-test via GitHub Actions

The existing central workflow `.github/workflows/office-test-all-modules-auto.yml` owns the VPS deployment job. There is no second deployment pipeline. Its source gate runs on PRs, and VPS deployment is opt-in on develop only. When VPS_OFFICE_TEST_DEPLOY_ENABLED=true, the legacy shared-hosting deployment job is skipped; only the VPS receives new test releases.

## One-time setup

1. Configure an SSH key restricted to the VPS `brebo-deploy` account. The account must be able to update `/srv/brebo/office-test/releases`, `current`, and the cached repository. Do not reuse the old shared-hosting SSH identity.
2. In GitHub, create environment `office-test-vps`. Configure secrets `VPS_OFFICE_TEST_HOST`, `VPS_OFFICE_TEST_PORT`, `VPS_OFFICE_TEST_USER` (exactly `brebo-deploy`), `VPS_OFFICE_TEST_SSH_KEY`, and `VPS_OFFICE_TEST_HOST_KEY` (verified full known_hosts entry; never acquire by blindly trusting a first connection).
3. Configure repository variable `VPS_OFFICE_TEST_DEPLOY_ENABLED=true` only when the account and secrets are ready. Before that, the job is intentionally skipped.
4. Keep DB credentials and PHP-FPM pool settings on the VPS, outside Git. A separate privileged one-time provisioning operation is required for PHP-FPM configuration; this deployment job does not edit system configuration or activate the router.
5. Check that the isolated test hostname and local TLS are working. The deployment health check accepts HTTP 503 while the router is deliberately fail-closed; this is a deployment health check, **not login acceptance**.

## Behavior

On a develop commit, after the central source gate passes, the VPS job fetches that exact commit, builds an immutable Office-test release, atomically switches `current`, and checks HTTPS locally. An unexpected HTTP result triggers a symlink rollback. It never touches the production domain, website, Zoho, or production DB.

Do not interpret a green VPS deployment as proof of session/login functionality. Add separate end-to-end login acceptance before enabling the router for users. Never paste passwords into logs.

## Dashboard activation on the existing VPS

The dashboard suite is already included in develop. Deploying its source does not activate the PHP-FPM feature flags or install the presentation preferences table. As root, the bounded helper in the active release can inspect and activate only this component:

```sh
php /srv/brebo/office-test/current/deployment/vps/activate-dashboard-suite.php --plan
php /srv/brebo/office-test/current/deployment/vps/activate-dashboard-suite.php --apply
```

It requires the already-active VPS test router, the exact `brebo_office_test` database/user, the existing root-private database environment and dedicated PHP 8.3 test pool. It creates only the existing preference table if absent, preserves business data, makes a private pool backup, validates PHP-FPM before reload and restores the old pool if validation/reload fails. Credentials are never printed. The unprivileged GitHub deploy account cannot run this activation.

After activation, verify `/office-test/mijn-dashboard?view=projects`, `finance`, `quality`, `builder`, and `wallboard` at `https://office-test.brebobv.nl`, including authenticated saving and monitor privacy. A release health check or anonymous login redirect is not functional acceptance.

## Existing project workspace activation

The dashboard activation does not activate project modules. If the menu says
`Nog niet aangesloten`, the root-only helper can verify the existing project
tables and enable only their existing two feature flags:

```sh
php /srv/brebo/office-test/current/deployment/vps/activate-dashboard-suite.php --workspace-plan
php /srv/brebo/office-test/current/deployment/vps/activate-dashboard-suite.php --workspace-apply
```

Missing prerequisite columns stop activation before any writes. Account and project
permissions, CRM, calculation, inspections and write flags remain unchanged. The
existing private backup, FPM validation and rollback apply. The `brebo-deploy`
pipeline account cannot change the root-owned FPM configuration.
