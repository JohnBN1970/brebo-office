# Next gate

Stop adding product scope here until the first hosted Xcode build has run.

Current branch already contains enough to answer the next question: does the generated native SwiftUI/ARKit project compile and do the deterministic unit tests pass on a real macOS/Xcode runner?

Run Codemagic workflow `brebo-measure-ios` on branch `feature/measure-ios-cloud-build`.

- Green: configure Apple signing/TestFlight next.
- Red: use the exact compiler/test error as the next patch input.

Do not infer physical LiDAR accuracy from a simulator/cloud compile. Physical accuracy starts only after installation on the LiDAR iPhone.
