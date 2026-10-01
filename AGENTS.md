# BREBO Office — agent working agreements

These instructions apply to every coding agent and chat working in this repository.

## Product principles

- The building is the durable information core; a project is temporary context around it.
- Keep data and workflows aligned with the BREBO Lens: Inzicht, Regie, Realisatie.
- Prefer explicit, auditable relations between projects, buildings, building objects, activities, evidence, decisions, risks, and documents.
- Preserve existing user work and avoid assumptions when a choice materially changes the result.

## Parallel chat development

- Assume multiple chats or developers may be changing the repository at the same time.
- Each chat must work on its own feature branch. Never reuse another chat's feature branch.
- Start a new change from the latest `develop`, not from a stale feature branch.
- Immediately before opening, updating, or merging a pull request, fetch or re-check the latest `develop` and compare the branch against it.
- When `develop` moved, rebuild or rebase the feature safely on the latest state and resolve conflicts deliberately. Never overwrite newer work blindly.
- Treat shared files as conflict-prone, especially update hooks, routing, services, permissions, libraries, configuration, and schemas.
- Keep commits and pull requests limited to the current chat's intended scope. Do not stage, revert, rewrite, or delete unrelated changes.

## Acceptance, merge, and deployment

- Short continuation messages such as `door`, `doorgaan`, `ja`, or `acc` mean continue the work unless the user explicitly says otherwise.
- Do not interpret those messages by themselves as approval to merge, deploy, release, or perform another irreversible action.
- A feature may be prepared, committed, pushed, and offered as a draft pull request when requested, but merging into `develop` requires explicit merge approval.
- Deployment or release requires separate explicit approval when it is not an automatic, already-authorized consequence clearly stated to the user.
- Before a merge, summarize the exact scope, validation results, conflicts or risks, and the target branch.

## Implementation quality

- Inspect the current architecture and reuse established Drupal patterns before adding new ones.
- Prefer additive, backward-compatible changes and auditable revisions for business data.
- Add the smallest relevant automated tests and run available static or runtime checks.
- If a required runtime or dependency is unavailable, report which checks could not be executed; do not claim they passed.
- Never claim a deployment succeeded without verifying its resulting state.
