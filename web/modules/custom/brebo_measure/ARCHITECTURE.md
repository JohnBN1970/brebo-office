# Architecture boundary

BREBO Office owns project/building/opening identity, measurement history, validation and production approval.

A Measure client owns temporary capture interaction and sensor acquisition.

The geometry vocabulary is shared across mobile LiDAR, manual/laser references, a future Precision Kit and FrameBot. Source-specific acquisition may evolve without changing the canonical opening identity.

Safety- or production-critical decisions must use deterministic rules and explicit approval states rather than inferred AI output alone.
