# BREBO Mail context model

## Principle

A communication is linked to the most specific canonical business object that owns the work. Do not duplicate hierarchy relations that can be derived from that object.

## Project mail

Project-related mail is linked to the **project** only. The building context is derived from the project's canonical building relation(s). A user should not have to select both project and building for the same project mail.

If the project does not yet exist, create the project from the Mail context workflow. During project creation the canonical building can be selected or, if it does not exist, created there. This keeps building ownership in one place: the project hierarchy.

## Non-project mail

Not all mail belongs to a project. The Mail context workflow therefore supports these primary targets:

- Project
- Organization
- Contact
- BREBO general/internal context

The communication can still carry classification, tags and document provenance independently from the primary context.

## Documents

Attachments are registered once as BREBO documents. Confirmation of the mail context adds or confirms document-context relations; it never creates a duplicate document merely because the same file is received again.

For project mail, documents receive the confirmed project context. Building context can be derived from the project hierarchy where needed instead of being manually duplicated at intake.

## Rule

**One object -> one identity -> one primary business context -> derived hierarchy -> multiple sources and relations.**
