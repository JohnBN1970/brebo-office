# BREBO Calculation Document Set Architecture

Status: accepted implementation direction

## Purpose

A calculation must not start from a blank Calc workbench or from one manually uploaded supplier quote.

BREBO Office acts as the digital work preparer. It reviews the documents already related to the Project and proposes which sources are useful for calculation. Calc acts as the digital calculator and builds a concept calculation from the reviewed calculation context.

## Core flow

Project documents -> work-preparation selection -> evidence/facts -> calculation context -> Calc concept -> human review -> accepted calculation.

## Responsibilities

### Office: digital work preparer

Office owns document identity, project/building context, provenance, extraction and review.

For a Project, Office may propose a CalculationDocumentSet containing relevant sources such as:

- supplier quotations;
- window/door schedules;
- drawings;
- specifications;
- inspection or technical advice;
- measurement schedules;
- planning information;
- project photographs;
- technical requirements and price lists.

Selection is explicit and reviewable. A document is never made canonical merely because an extractor or AI considered it useful.

### CalculationDocumentSet

A CalculationDocumentSet is a versioned selection of existing Office documents for one calculation purpose.

Minimum fields:

- id;
- project id;
- calculation reference/purpose;
- status: proposed, reviewed, accepted, superseded;
- created/changed timestamps;
- selection method/version;
- reviewed by/at.

Each selected document records:

- document id;
- role in calculation;
- relevance/confidence;
- selected by: system or human;
- review status;
- optional exclusion reason.

The set references existing documents. It does not duplicate binaries.

### Calculation facts

Office converts selected document evidence into structured calculation facts. Facts retain provenance.

Initial fact types should support:

- product/position identity;
- quantity and unit;
- width and height;
- derived area and perimeter;
- material/system/glazing/colour;
- supplier price and commercial cost rows;
- building, facade, dwelling type and component relation;
- technical requirements;
- execution/access constraints.

Every fact must retain document id and, where available, page/fragment, extraction method, confidence and review status.

Detected, selected and calculated values remain distinguishable.

### Quantity take-off

Reviewed geometric facts feed a quantity take-off layer.

Examples:

- width x height x quantity;
- area;
- perimeter;
- jamb/head/sill lengths;
- opening counts;
- profile lengths.

The take-off is source-independent: the same geometry may later originate from a supplier quote, drawing, window schedule, manual input or Sparingsmeter.

### Recipes and derived quantities

Recipes consume take-off values rather than parsing source documents themselves.

Examples:

- subframes;
- compriband;
- sealant;
- reveal cladding;
- fixings;
- glazing accessories;
- profile/cutting requirements.

This keeps document recognition separate from calculation logic.

### Cutting list

Profile requirements may feed a cutting-list optimizer. Optimization must consider both material yield and practical site/workshop handling.

Future constraints include:

- available stock lengths;
- saw kerf and end loss;
- minimum reusable offcut;
- maximum practical handling length;
- grouping by building/facade/dwelling/position;
- preference for a workable cutting sequence over mathematically minimal waste.

### Calc: digital calculator

Calc receives a structured CalculationContext from Office. It must not independently decide canonical project/document truth.

The context may contain:

- selected source references;
- reviewed and proposed facts;
- quantity take-off;
- commercial source lines;
- unresolved review points;
- grouping dimensions such as facade or dwelling type.

Calc may use this to propose:

- chapters and paragraphs;
- calculation lines;
- recipes;
- labour norms/hours;
- materials and subcontracting;
- cost allocations;
- partial calculations.

The generated result is a concept until reviewed.

## API boundary

Introduce a versioned Office -> Calc calculation-context contract.

Suggested endpoints/concepts:

- project calculation document-set;
- calculation facts;
- quantity take-off;
- calculation-context snapshot.

Calc stores the context/snapshot reference and provenance required for audit, not duplicate Office document truth.

## Review principle

Automation proposes; review confirms.

The user should be able to see:

- which project documents were considered;
- which were selected or excluded;
- what facts came from each source;
- conflicts between sources;
- unresolved facts;
- what the concept calculation derived from those facts.

## First implementation slice

1. Expose the Project document candidates relevant to calculation.
2. Add CalculationDocumentSet with system proposal + human selection.
3. Produce structured facts for already-supported supplier quotation recognition.
4. Add width/height fact extraction when present in recognized product descriptions/details.
5. Build the first quantity take-off from quantity + width + height.
6. Expose a CalculationContext snapshot to Calc.
7. Let Calc create a reviewable concept from that context.

Do not start by adding more document-specific logic inside Calc.
