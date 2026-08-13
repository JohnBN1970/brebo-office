# BREBO field cardinality inventory

Status: working inventory for #201.

## Finding

The current core field helper creates field storage with `cardinality => $definition['cardinality'] ?? 1`. In other words, every field is single-value unless a field definition explicitly overrides cardinality. The current install code does not show an explicit `cardinality => -1` override in the searched BREBO core definitions.

This is safe for scalar master-data fields, but it is not a suitable default for high-volume building/address/BAG/history relations.

## Current fields that should remain scalar

### Project
- `field_brebo_project_code`
- `field_brebo_client` (temporary string model; later candidate for organization reference)
- `field_brebo_location` (human-readable summary only)
- `field_brebo_status`
- `field_brebo_description`

### Building master data
Keep canonical display/master fields scalar: BREBO building code, canonical display name/title, primary display address, primary postal code/city/country, lifecycle/review status and short description.

These fields are summaries, not the storage location for all underlying addresses or BAG identities.

## Relations that must not become unbounded fields on Building

The following must be stored as dedicated lightweight relation/evidence records rather than appended indefinitely to a building node:

1. Building -> address/unit relations.
2. Building -> BAG pand/verblijfsobject/nummeraanduiding identities.
3. Building/project -> Communications and other source evidence.
4. Per-field provenance and enrichment history.
5. Deduplication/merge proposals.
6. AI enrichment proposals and their review history.

Reason: these collections can grow from tens to hundreds or thousands of records per functional building over its lifetime. Keeping them as independent indexed records avoids oversized edit forms, expensive node revision payloads and large multi-value field tables.

## Existing hierarchy risk

The existing core model contains Project -> Cluster -> Woning -> Productpositie entity references. The helper defaults these references to cardinality 1, which is correct for their current parent direction: one child points to one parent. The reverse relation must be queried/indexed and must not be duplicated as a huge list of child references on the parent.

The same rule should be used for the new building model:

- Address/Unit record -> one BREBO Building reference.
- BAG Identity record -> one BREBO Building reference for the active reviewed relation (with explicit exception/review handling where needed).
- Project-Building relation -> one project + one building per relation row; many rows allowed overall.
- Evidence/Provenance record -> one target object/field/value observation per row.

## Target lightweight records

### `brebo_building_address`
Suggested indexed columns/fields:
- building_id
- address_role (primary/secondary/unit/service/etc.)
- street
- house_number
- house_letter
- house_number_addition
- postal_code
- city
- country
- bag_nummeraanduiding_id nullable
- bag_verblijfsobject_id nullable
- valid_from / valid_to nullable
- status
- created / changed

Recommended uniqueness/review key: normalized address + active relation. A new house number should first search this table and BAG identity relations before any new Building is proposed.

### `brebo_building_bag_identity`
- building_id
- bag_object_type
- bag_object_id
- source
- retrieved_at
- relation_status
- confidence
- reviewed_by / reviewed_at nullable

Index BAG object id strongly. Reuse of one active BAG identity across multiple active BREBO buildings must raise a review conflict instead of silently creating another building.

### `brebo_project_building`
Use a relation record rather than assuming Project == Building.
- project_id
- building_id
- relation_role/scope
- valid_from / valid_to nullable
- status

This permits one building to have many projects over time and one project to cover multiple buildings.

### `brebo_value_provenance`
- target_entity_type
- target_entity_id
- target_field/key
- normalized_value_hash
- source_type (BAG/PDOK/mail/document/manual/AI)
- source_entity/reference
- observed_at / retrieved_at
- confidence
- method (hard_source/derived_ai/manual)
- confirmation_state
- confirmed_by / confirmed_at nullable

Do not create a node revision merely because another evidence source supports the same value.

### `brebo_merge_proposal`
- object_type
- candidate_a_id
- candidate_b_id
- match_signals
- confidence
- status
- created
- reviewed_by / reviewed_at

## Deduplication order

For every incoming mail/BAG/document observation:

1. Exact/strong canonical identity match.
2. Existing provisional object match.
3. Existing address/BAG relation match.
4. Merge candidate if identity is ambiguous.
5. Only then create a new provisional object.

This prevents each historical e-mail from producing another provisional project/building.

## Drupal volume rule

Use Drupal nodes for human-facing business identities and lifecycle objects. Use dedicated indexed relation/evidence storage for high-volume child facts and history. Avoid unlimited multi-value fields on Building, Project, Organization or Contact when the list can grow throughout the life of the object.

## Migration rule

Existing scalar building address fields remain valid as a canonical display snapshot. During migration they can seed one primary `brebo_building_address` record. No existing node should be deleted or rewritten merely to introduce the new relation model.

## Acceptance scenarios

1. A building with house numbers 87, 89, 91, 93, 95 and 97 remains one BREBO Building with six address/unit relations.
2. A second mail mentioning house number 91 links to the existing address/building and creates no new Building proposal.
3. Multiple BAG pand identities may intentionally belong to one functional BREBO Building/complex.
4. The same active BAG identity appearing under two BREBO Buildings raises a merge/review conflict.
5. A 2022 project and a 2026 project can both point to the same Building.
6. Ten thousand Communications can support a building/project without becoming ten thousand values in one node field.
7. Older Zoho mail may add missing evidence but cannot overwrite a manually confirmed/newer high-confidence value.

## Next implementation step

Create the first lightweight schema for Building Address and BAG Identity relations, with indexes and duplicate checks, without yet migrating or deleting existing building fields.