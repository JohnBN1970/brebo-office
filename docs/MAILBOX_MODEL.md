# BREBO Office Mailbox Model

Status: accepted foundation for implementation.

## Principle

The external mail provider is transport and temporary buffer. BREBO Office is the permanent mailbox, archive and dossier layer.

A BREBO mailbox is a logical business object and is not the same as an IMAP folder.

## Mailbox types

- functional: central/shared business mailbox, eligible for automatic intake;
- personal: personal mailbox; no automatic central dossier intake without an explicit push or another configured lawful workflow;
- catch_all: technical receiver for otherwise unmatched addresses; never trusted as dossier truth by itself.

## Message states

Inbox, Sent, Drafts, Spam, Archive and Trash are BREBO states/views. A canonical communication object is stored once and can participate in multiple views without duplication.

## Access control

Mailbox visibility and actions are controlled through Drupal roles and permissions. Per mailbox, separate capabilities must exist for view, handle, send-as, send-on-behalf, archive, delete, permanent-delete and administration.

Message-level privacy may further restrict mailbox access. Search, AI, project timelines, document results and attachments must never bypass mailbox/message access rules.

## Personal mail push

Personal mail remains personal by default. A user can explicitly push relevant business communication into BREBO. BREBO then preserves source metadata, Message-ID, timestamps, thread references and attachments, and proposes dossier context. The push action is auditable.

## Provider retention

Provider-side deletion is only allowed after BREBO has safely persisted the message and attachments, verified integrity, and satisfied the configured backup/retention gate. Provider deletion is never part of initial read-only intake.

## Multiple addresses

The model must support many addresses and aliases without duplicating infrastructure. Functional addresses, personal addresses, aliases and catch-all recipients map into logical BREBO mailboxes and routing rules.
