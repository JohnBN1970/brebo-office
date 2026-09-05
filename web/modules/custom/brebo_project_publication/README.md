# BREBO Project Publication

BREBO Office remains the source of truth. This module owns only the bounded public presentation projection for an existing `brebo_project`.

## Rules

- exactly one publication projection per canonical project;
- external publication is opt-in and defaults to false;
- generic Drupal publication or project status never grants external release;
- only public presentation fields are stored here;
- only demonstrably realised results may be entered as public results;
- media require separate approval;
- release and withdrawal are restricted actions;
- every externally relevant change increments `publication_version`;
- withdrawal removes the projection from the public API and must invalidate website caches within the contract maximum of five minutes;
- calculations, budgets, margins, suppliers, internal notes, personal data and other internal dossier information do not belong in this projection.

## Public projection

The intended read-only projection contains only:

- `public_id`;
- `public_slug`;
- `public_title`;
- `public_intro`;
- `building_question`;
- `chosen_approach`;
- `realized_results`;
- Lens roles;
- suitable public status;
- separately approved public media;
- `publication_version` and `updated_at`.

The Integration API and public website are later consumers of this projection. They never become the canonical project owner.
