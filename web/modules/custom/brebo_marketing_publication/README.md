# BREBO Marketing Publication

Controlled external communication starts in BREBO Office.

## Architectural rule

Office knows the internal truth. AI may prepare and advise. The Digital Marketeer reviews and directs external communication. An authorised human approves publication. External channels are publication targets and never become the source of truth.

## Tranche 1

This module introduces only the controlled publication-dossier foundation:

- exactly one marketing publication dossier per `brebo_project`;
- separate marketing status and marketing decision;
- explicit Digital Marketeer review and approval permissions;
- append-oriented audit trail;
- no AI calls;
- no website calls;
- no social-media calls.

## Status model

Initial workflow states are:

- `draft`
- `marketing_review`
- `changes_required`
- `approved`
- `channel_proposals`

Approval is not publication. A later channel-specific lifecycle will own `ready`, `live`, `replaced` and withdrawal states.

## Marketing decisions

A reviewer can later record one of these explicit recommendations:

- `undecided`
- `publish_recommended`
- `website_only`
- `social_only`
- `review_later`
- `not_suitable`

No project becomes marketing content merely because the project exists.
