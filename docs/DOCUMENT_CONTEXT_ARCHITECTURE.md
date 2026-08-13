# BREBO Document Context Architecture

Status: accepted architectural direction
Issue: #209

## Purpose

BREBO Office must ingest documents from mail, attachments, uploads, migrations and future external sources without creating duplicate files or losing origin, context or evidentiary value.

The document model therefore separates physical storage, business context and provenance.

## Core rule

A document is stored physically once and may be related to multiple business contexts.

Do not copy the same binary into separate Building, Project, CRM or BREBO folders merely because it is relevant in more than one place.

The user interface may present familiar folder-like views, but those views are projections over document-context relations.

## Canonical contexts

### Building

Building context is durable and project-overarching. It belongs to the physical functional BREBO building and remains useful across successive projects.

Typical examples:

- object information;
- BAG/PDOK/BGT-related source documents;
- permanent drawings;
- permits;
- building-level inspections;
- historical photographs;
- permanent guarantees;
- as-built and revision documents after acceptance;
- technical history that remains relevant after a project closes.

A Building is not equal to one address, house number, BAG pand or project.

### Project

Project context contains information that exists because BREBO performs or prepares a specific assignment.

Typical examples:

- quotation and commercial correspondence;
- calculation;
- assignment/order;
- planning;
- work preparation;
- purchasing and subcontracting;
- execution records;
- project quality checks;
- site reports;
- handover documents;
- project finance and invoicing;
- project-specific communication.

A document originating in a Project may later also acquire durable Building context. The Project remains its origin; Building becomes an additional persistent context.

### CRM

CRM context is relationship- and company-oriented and is not dependent on one Building or Project.

Typical examples:

- framework agreements;
- general customer or supplier agreements;
- contact-person related documents;
- general price lists;
- supplier information;
- company certificates received from relations;
- general relationship correspondence;
- documents relevant to an Organization across multiple buildings/projects.

CRM context may relate to an Organization and, where useful, a Contact Person.

### BREBO / internal organization

This context contains internal documents that do not require a Building, Project or CRM relation.

Typical examples:

- KAM and VCA;
- insurance;
- BREBO certificates;
- procedures and policies;
- templates;
- general terms;
- branding;
- HR;
- ICT;
- internal finance;
- management/director documents.

## Inbox / Unclassified

Inbox / Unclassified is a temporary classification state, not a permanent business silo.

A document may enter BREBO Office without sufficient evidence for a safe context decision. It must then remain available, traceable and reviewable without forcing a Building, Project or CRM relation.

Once sufficient evidence exists, one or more contexts may be added. The original source/provenance never disappears.

## Multi-context documents

A document may have multiple simultaneous contexts.

Example:

- source: email attachment received during Project 2026;
- origin context: Project 2026;
- durable context after handover: Building Bilderdijkstraat 87-97;
- CRM context: customer Organization;
- source Communication: the email in which the file was received.

This must not create four file copies.

## Proposed logical model

### Document

Represents the logical document and its current preferred binary/version.

Minimum identity metadata:

- document id;
- title/document name;
- document type;
- original filename;
- MIME type;
- file size;
- SHA-256 content hash;
- storage provider/key;
- lifecycle status;
- created/changed timestamps.

Content hash is a strong duplicate signal but does not by itself decide that two separately received documents are semantically the same business record.

### DocumentContext

A lightweight many-to-many relation from Document to a business object.

Suggested attributes:

- document id;
- context type: building, project, organization, contact, brebo;
- context id;
- relation role: origin, durable, supporting, contractual, correspondence, other;
- confidence;
- source of relation;
- review status;
- confirmed by / confirmed at;
- created/changed.

Do not store huge reverse multi-value lists on parent Drupal nodes. Query the relation records by context id.

### DocumentSource / Provenance

Records where the document and extracted information came from.

Minimum provenance attributes:

- document id;
- source system, e.g. Zoho IMAP, live IMAP, Gmail, manual upload, future connector;
- source external id;
- Communication id where applicable;
- sender/source actor where applicable;
- received or source-created timestamp;
- original filename;
- SHA-256;
- extraction method/version;
- page number or fragment location when evidence is page/fragment based;
- original versus derived artifact;
- confidence;
- human confirmation status.

Provenance is append-only in meaning: corrections may supersede earlier interpretations, but the fact that a source existed must not be silently erased.

### DocumentEvidence

Optional high-volume evidence records produced by extraction.

Examples:

- page 2 contains `Marktlaan 12-18, 1234 AB Amsterdam`;
- page 4 contains project number `P-2026-014`;
- page 1 names organization X.

Suggested attributes:

- document id;
- source/provenance id;
- page/fragment;
- evidence type;
- extracted value;
- normalized value;
- extraction method;
- confidence;
- validation source, e.g. PDOK/BAG;
- validation status;
- canonical_truth = false by default;
- reviewed/confirmed metadata.

## Evidence is not truth

Attachment text, OCR, parser output and AI extraction are evidence only.

They may strengthen context resolution, but they may not silently create or overwrite canonical Building, Project, CRM or contractual truth.

For address/building identity, official BAG/PDOK identity and existing confirmed BREBO relations are preferred validation sources.

Ambiguity produces a review proposal, never an arbitrary choice.

## Source precedence and information gathering

Information may come from:

1. confirmed BREBO canonical data;
2. official registers such as BAG/PDOK where applicable;
3. confirmed document metadata/relations;
4. source Communication metadata;
5. native text in documents;
6. structured parser extraction;
7. OCR where native text is unavailable;
8. AI interpretation/inference.

Lower layers may propose enrichment but must not silently override stronger confirmed sources.

## Folder-like user experience

The UI may expose views such as:

- Buildings / <building> / Object information / Drawings / Inspections / Permits / Photos / History;
- Projects / <project> / Commercial / Calculation / Contract / Work preparation / Purchasing / Execution / Quality / Handover / Finance;
- CRM / Organizations / <organization>;
- CRM / Contacts / <contact>;
- BREBO / KAM / Finance / HR / ICT / Templates / Policy;
- Inbox / Unclassified.

These are navigational classifications, not necessarily physical filesystem paths.

Changing a folder taxonomy must not require moving or duplicating every binary.

## Storage boundary

Drupal should manage identity, metadata, relationships, permissions and workflow. Large binary storage should remain abstracted behind a storage provider/key so BREBO can change physical storage later without redesigning document relations.

The architecture must support local/private Drupal storage initially and allow migration to object storage or another document backend later.

Do not encode business truth in a physical pathname.

## Deduplication

Deduplication operates at multiple levels:

- exact binary duplicate: SHA-256 match;
- same source item imported twice: source system + external id/hash;
- semantic duplicate: proposed only when metadata/content/context indicate likely equivalence;
- same document relevant to multiple contexts: add DocumentContext relations, never duplicate the binary.

Semantic duplicates must be reviewable before destructive merge/removal.

## Lifecycle example: email with PDF attachment

1. Mail source creates/updates Communication.
2. Attachment is fingerprinted by SHA-256.
3. Existing binary is reused when safely identical; otherwise one Document is created.
4. DocumentSource links it to the Communication and source system.
5. Native PDF text is extracted; OCR is used only where needed.
6. Page-level DocumentEvidence records are created.
7. Resolver considers mail + document evidence.
8. Exact BAG/address/project/CRM evidence may identify existing context.
9. Uncertain context remains review-only.
10. Confirmed relations are stored as DocumentContext.
11. Later project closure may add durable Building context without losing Project origin.

## Implementation order

1. Stabilize attachment evidence intake and its non-canonical invariant.
2. Implement generic Document identity/storage metadata.
3. Implement DocumentSource/provenance.
4. Implement DocumentContext many-to-many relations.
5. Implement high-volume DocumentEvidence storage.
6. Connect mail attachments to the document model.
7. Add context resolver integration and review proposals.
8. Add folder-like Building/Project/CRM/BREBO views.
9. Add migration/import adapters and duplicate review tooling.

## Acceptance principles

The architecture is accepted only if it can prove all of the following:

- one PDF can appear under a Project and Building without two binary copies;
- its original email and filename remain traceable;
- a value extracted from page N retains page-level provenance;
- extracted values are non-canonical until validated/confirmed;
- a document may remain Unclassified without data loss;
- a Building may accumulate permanent documents across many projects;
- CRM documents can exist without a Building or Project;
- internal BREBO documents can exist without CRM, Building or Project;
- changing the user-facing folder taxonomy does not require physical file relocation;
- duplicate imports do not multiply identical binaries and relations uncontrollably.
