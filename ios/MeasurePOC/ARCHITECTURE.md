# BREBO Measure scanner architecture

The scanner is deliberately split into four layers:

1. **Capture** - ARKit scene depth / mesh and device diagnostics.
2. **Geometry** - deterministic plane/boundary/candidate calculations.
3. **Evidence** - measured/detected/calculated provenance, confidence and uncertainty.
4. **Office transport** - device-neutral observation payloads matching the Measure API.

AI is not permitted to invent hard dimensions. Future AI may classify ambiguous geometry or request a rescan, while deterministic geometry and explicit evidence remain the source for dimensions.

The current branch proves the build/distribution path and the tap-free capture shell. Automatic plane extraction/classification from retained ARKit samples is the next algorithmic slice after the cloud compile is green.
