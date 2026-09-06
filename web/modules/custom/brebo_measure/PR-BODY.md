## Summary
Adds the first Office-side JSON workflow for BREBO Measure on top of the domain merged in #604.

## Functional slice
- read a canonical opening;
- create a measurement assignment;
- create a versioned capture with source/device context;
- append observations with provenance, confidence and uncertainty.

## Validation-first design
The branch also fixes the first field-test contract: sensor and independent control observations coexist, repeated captures remain auditable, and mobile accuracy is an empirical question rather than an assumption.

## Explicitly not production release
A green/accepted capture is not permission to order a frame. Production geometry approval remains a later Office workflow.

## Next
Lifecycle + validation writes, Office launch action, then the first Apple LiDAR proof against a real opening.
