# ADR: Native Office mail has zero Drupal runtime dependencies

Status: Accepted architecture constraint (implementation pending).

## Hard boundary
This rule applies to EVERY Office module, not just mail. Drupal is the public website only. Native Office may accept authenticated website API submissions but must not require Drupal services, data tables, users, sessions or cron at runtime.

BREBO Office owns mailbox configuration, identity, polling jobs, normalized
messages, deduplication, classification, review, audit and dossier handoff.
Drupal is limited to the public website and cannot be a required mail service.

## Migration correction
The Drupal mailbox action form and queue worker proposed in PR #1791 are
transitional prototypes only. They MUST NOT be enabled or deployed as the
final Office mail solution. The existing Drupal UID-to-Office operator mapping
is likewise a transitional bridge, not a native identity model.

The native personal-mail endpoint must NOT be enabled while it accepts only
Drupal-owned identifiers without a native mailbox authorization proof.

## Native processing contracts
1. Functional mailboxes: Office-managed credentials and Office-owned scheduled
   polling; classify into proposed links, never directly attach to a dossier.
2. Personal mailboxes: user-controlled private mailbox; only an explicitly
   selected message may enter the Office processing queue. No background scan
   of personal mailbox contents for dossier classification.
3. All mailbox access: Office session/role and mailbox ownership checks.
4. Office data: message source identity, thread, date, attachments, audit,
   deduplication and retention policy. Sensitive content stays private until
   explicit transfer.
5. Human approval is required before creating any CRM/project/building/finance
   or document relationship.
6. Office production deployment is blocked until Drupal-independent intake,
   review and end-to-end tests pass.

## Migration acceptance checks
- Disable Drupal mail intake cron and personal push queues only after verified
  cutover, preserving rollback and pending work.
- No PHP classes, Drupal services, Drupal tables, Drupal user IDs, Drupal routes,
  Drupal cron or Drupal API calls in the native Office runtime path.
- Functional mailbox ingestion and personal explicit push operate with Drupal
  entirely unavailable.
- Negative authorization, replay, duplicate, attachment, failed delivery and
  human approval tests pass.
- Verify Office-test first; no implicit production rollout.
