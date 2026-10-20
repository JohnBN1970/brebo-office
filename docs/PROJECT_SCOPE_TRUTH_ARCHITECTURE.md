# BREBO Project Scope Truth

Status: first implementation slice

## Purpose

Project Scope Truth is the reviewed bridge between source evidence and calculation/commercial output.

Sources may request, observe or suggest work. Those statements are evidence, not automatically BREBO scope. A human-confirmed decision establishes whether a subject is in scope, an alternative, third-party work, declined or otherwise out of scope.

## Core chain

```text
Source / communication / technical advice
-> extracted or reviewed fact
-> requested / observed statement
-> human scope decision
-> current Project Scope Truth
-> CalculationContext / scenario
-> Calc
-> Advice / Offer Model / Output
```

## Rules

- Source evidence is never overwritten by a later decision.
- A later decision supersedes an earlier decision for the same project + subject and preserves history.
- REQUESTED and OBSERVED statements stay unresolved and cannot establish commercial scope.
- Only DECIDED statements can establish a final disposition.
- BREBO Lens observations therefore never silently become offer scope.
- Drupal/database persistence is behind a repository contract; domain rules contain no Drupal Database API.

## Current dispositions

- `unresolved`
- `in_scope`
- `alternative`
- `third_party`
- `declined`
- `out_of_scope`

## Golden case: Wakkerstraat 29 Amsterdam

Expected current truth includes:

- entire building painting = in scope;
- wood-rot repair/replacement where needed = in scope;
- front façade uPVC replacement = alternative;
- rear ground-floor pui replacement = third party;
- rear ground-floor pui painting = in scope;
- roof renovation = declined;
- roof insulation = declined.

Regression tests must also prove that a technical observation cannot silently become `in_scope`.
