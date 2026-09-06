# BREBO Measure - tap-free scanner slice

This branch changes the proof direction after the field UX experiment with a generic LiDAR measuring app.

The primary interaction is now:

`Start scan -> move around opening -> collect depth/mesh -> analyse geometry -> result`

The operator does not select exact measurement endpoints.

## Current implemented slice

- reproducible Xcode project definition through XcodeGen;
- Codemagic macOS build workflow;
- ARKit world tracking with scene depth and mesh reconstruction;
- tap-free scan start/stop UI;
- live depth-frame and mesh-anchor collection counters;
- explicit opening geometry result model;
- fail-closed deterministic detector boundary ready for plane classification/intersection.

## Next proof

1. get the unsigned cloud compile green;
2. connect Apple signing and TestFlight;
3. run this scanner on one real rectangular opening;
4. retain enough mesh/depth samples to fit stable planes;
5. classify four opening boundaries and intersect them;
6. compare derived widths/heights with independent reference measurements.

No production dimension may be approved merely because ARKit returned a mesh. Accuracy and uncertainty must be proven empirically.
