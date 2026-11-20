# Personal mailbox push to Office — implementation contract

Issue: #1790

## Existing integration points
- `web/modules/custom/brebo_mail_intake/src/Form/MailboxMessageActionForm.php` owns message actions.
- `web/modules/custom/brebo_mail_intake/src/Controller/MailboxController.php` renders mailbox messages.
- `web/modules/custom/brebo_mail_intake/brebo_mail_intake.module` enqueues functional mailbox IMAP intake from cron.
- `docs/MAILBOX_MODEL.md` establishes explicit personal push and message-level privacy.
- `docs/MAIL_PLATFORM.md` establishes proposal-only classification and human review.

## Required behavior
1. Display **Push naar Office** only for a personal mailbox and only for its authorized user. Authorization must be rechecked server-side on POST.
2. Explicitly select a message or thread. Never scan or push the rest of a personal mailbox implicitly.
3. Preserve original communication identity, source metadata, thread references and attachments. Record actor and timestamp of push.
4. Submit to a durable, idempotent classification queue; repeated push must not duplicate communication, documents or proposals.
5. Classifier returns *proposals*, never definitive dossier links: CRM, Project, Building, Document, Finance, Quality.
6. Review UI supports approve, edit and reject; access must be enforced for every proposed destination and attachment.
7. No automatic sending, deletion, mailbox mutation or provider-side cleanup.
8. Functional mailbox cron behavior must remain unchanged.

## Verification gates
- Owner can push; unauthorized user cannot view or submit the action.
- CSRF-protected POST, retry and duplicate push.
- Thread selection, attachments, missing attachments and malformed mail.
- Ambiguous address/project match remains review-only.
- Proposed finance or quality record is not committed without approval.
- Functional cron continues to enqueue IMAP messages.
- Native Office-test route and UAT pass before deployment.

This contract is preparation only; implementation and deployment are separate steps.
