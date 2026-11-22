# Office-test checkpoint preflight (read-only)

Purpose: verify a recoverable rollback point before deploying PR #2034. This document does not authorize deployment.

## Preconditions
- Confirm VPS Office-test release foundation completed successfully.
- Use the unprivileged deployment account and inspect only /srv/brebo/office-test.
- Identify the active Office-test application path, release revision and activation mechanism (symlink, service, or webroot) without changing them.
- Check whether the current release is retained intact and whether its application configuration, writable/shared paths and database dependencies are recoverable.
- Check available space, ownership and permissions in releases/, shared/ and checkpoints/; reject unexpected symlinks.
- Determine how to restore the prior release and which health checks prove rollback succeeded.
- Record a timestamp, active revision, checkpoint identifier and rollback instructions, without logging credentials or secret values.

## Stop conditions
- Unknown active release, missing rollback mechanism, unverified database compatibility, insufficient disk space, or unexpected production paths: STOP.
- Do not copy secrets into GitHub Actions logs or artifacts.
- No deploy, activation, merge, database change or production write is allowed by this preflight.

## Gate to next phase
A separate reviewed checkpoint procedure must create and verify a recoverable rollback point on the VPS. Only then consider isolated Office-test deployment of PR #2034 at c28be37343b80b69f791c17539b7f9f4548ae9ec. Keep PR #2034 draft until logged-in personnel/document navigation and authorization tests pass.
