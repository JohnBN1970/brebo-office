# ADR-0001 — High-volume building data model

Status: Proposed

Related: #201

## Context

BREBO Office is beginning to ingest large volumes of historical mail, BAG/PDOK data and CRM context. The existing model must not turn every new observation, address or mail into a new top-level Project or Building. The design must remain usable when Communications and evidence grow into the tens or hundreds of thousands.

## Core principle

**More information enriches existing identities; it does not automatically create more top-level objects.**

The BREBO Building is the functional maintenance object. It is not synonymous with one postal address, one house number, one BAG pand or one project.

## Target object model

```text
BREBO Building
  -> 0..n BAG identities
  -> 1..n Addresses / house numbers / units
  -> 0..n Projects over time
  -> 0..n Communications / documents / photos / inspections
  -> 0..n Evidence and enrichment observations
```

A BREBO Building may therefore represent a complex, block or operational maintenance object containing multiple house numbers and, where justified, multiple BAG objects.

Projects are separate time-bound work objects. The same Building can participate in many Projects over its lifetime.

## Storage rules

### Scalar Drupal fields

Use ordinary single-value node fields for low-cardinality current state and presentation values, for example:

- canonical BREBO code;
- primary display name;
- primary/summary location;
- current lifecycle/status;
- short human-facing description.

These fields are not the authoritative store for unbounded history.

### Relation objects / dedicated storage

Use dedicated relation records or lightweight entities/tables for data that can grow without a small fixed bound:

- addresses and house numbers;
- BAG pand/verblijfsobject/nummeraanduiding identities;
- source evidence and provenance;
- enrichment observations and proposals;
- merge/deduplication candidates;
- historical value assertions.

Do not append these indefinitely to large text fields or huge multi-value fields on `brebo_building`.

## Provenance

Every automatically enriched assertion must be traceable. At minimum store:

- target object and target field/concept;
- proposed/current value;
- source type;
- source reference (for example Communication id, BAG id or document id);
- observed/retrieved timestamp;
- confidence;
- method: hard source / AI inference / manual;
- confirmation state;
- superseded state where applicable.

A manual-confirmed or newer high-confidence value must not be silently overwritten by an older historical mail.

## Identity and deduplication

Incoming evidence is resolved in this order:

1. existing canonical object;
2. existing provisional object;
3. existing likely duplicate -> merge proposal;
4. only then create a new provisional object.

For Buildings, a full address is evidence but is not the definition of the Building. BAG identity is a strong technical key, but a BREBO Building can intentionally aggregate multiple BAG identities. The same BAG identity must not silently appear under multiple active Buildings; that condition becomes a reviewable conflict.

## Multi-house-number buildings

A newly observed house number never proves a new Building by itself. The resolver must first determine whether the address belongs to an existing BREBO Building through:

- exact known address relation;
- BAG identity relation;
- existing project/building relation;
- compatible complex/building context;
- human-confirmed grouping.

If so, the address is attached to the existing Building. Otherwise a provisional Building proposal may be created.

## Project identity

A Project is not a Building and must never become the long-term container for building identity. Many Projects can reference the same Building over time.

## Enrichment engine contract

The future enrichment engine may:

- detect missing/eligible fields;
- collect evidence from Communications, BAG/PDOK, documents and CRM;
- propose values with confidence and provenance;
- automatically apply deterministic hard-source values when policy permits.

It may not:

- invent missing facts without evidence;
- overwrite protected/manual-confirmed values;
- create canonical Projects or Buildings solely because one mail mentions an unfamiliar name/address;
- collapse objects automatically where identity is ambiguous.

## Performance rules

- Process enrichment and backfill in bounded queue batches.
- Query by indexed identity keys rather than loading all nodes for matching where scale makes that unsafe.
- Avoid unbounded entity-reference fields on high-volume nodes.
- Keep operational current state separate from large evidence/history datasets.
- Add uniqueness/index constraints to durable external identifiers where the relation model supports them.

## Migration strategy

1. Inventory current field cardinalities and identify fields that currently mix identity, presentation and history.
2. Introduce address/BAG/provenance relation storage without removing existing fields.
3. Backfill relation records from existing Building fields.
4. Switch resolvers to relation-aware matching.
5. Keep legacy summary fields synchronized for UI compatibility during transition.
6. Only remove/redefine legacy storage after acceptance tests prove no information loss.

## Required acceptance scenarios

- One Building with many house numbers does not create duplicate Buildings.
- One Building may intentionally contain multiple BAG identities.
- Repeated mails about the same unknown object reuse one provisional proposal.
- A second likely duplicate creates a merge proposal rather than a silent second canonical object.
- Historical mail cannot overwrite a newer/manual-confirmed value.
- Every enriched field can show where its value came from.
- Large Communication/evidence counts do not require loading all records into memory for matching.
