# VPS Office-test via GitHub Actions

The existing central workflow `.github/workflows/office-test-all-modules-auto.yml` owns the VPS deployment job. There is no second deployment pipeline. Its source gate runs on PRs, and VPS deployment is opt-in on develop only.

## One-time setup

1. Configure an SSH key restricted to the VPS `brebo-deploy` account. The account must be able to update `/srv/brebo/office-test/releases`, `current`, and the cached repository. Do not reuse the old shared-hosting SSH identity.
2. In GitHub, create environment `office-test-vps`. Configure secrets `VPS_OFFICE_TEST_HOST`, `VPS_OFFICE_TEST_PORT`, `VPS_OFFICE_TEST_USER` (exactly `brebo-deploy`), `VPS_OFFICE_TEST_SSH_KEY`, and `VPS_OFFICE_TEST_HOST_KEY` (verified full known_hosts entry; never acquire by blindly trusting a first connection).
3. Configure repository variable `VPS_OFFICE_TEST_DEPLOY_ENABLED=true` only when the account and secrets are ready. Before that, the job is intentionally skipped.
4. Keep DB credentials and PHP-FPM pool settings on the VPS, outside Git. A separate privileged one-time provisioning operation is required for PHP-FPM configuration; this deployment job does not edit system configuration or activate the router.
5. Check that the isolated test hostname and local TLS are working. The deployment health check accepts HTTP 503 while the router is deliberately fail-closed; this is a deployment health check, **not login acceptance**.

## Behavior

On a develop commit, after the central source gate passes, the VPS job fetches that exact commit, builds an immutable Office-test release, atomically switches `current`, and checks HTTPS locally. An unexpected HTTP result triggers a symlink rollback. It never touches the production domain, website, Zoho, or production DB.

Do not interpret a green VPS deployment as proof of session/login functionality. Add separate end-to-end login acceptance before enabling the router for users. Never paste passwords into logs.
