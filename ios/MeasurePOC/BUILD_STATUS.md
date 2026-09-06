# Build status

Branch: `feature/measure-ios-cloud-build`

Repository preparation is complete for the first external macOS compile attempt:

- native SwiftUI/ARKit sources are present;
- XcodeGen project definition is present;
- Codemagic workflow is present;
- deterministic unit test target is present;
- primary interaction is automatic scanning, not point tapping;
- production release is fail-closed by default.

The next evidence must come from a real macOS/Xcode build runner. GitHub alone cannot validate Xcode/ARKit compilation in this repository. Run the `brebo-measure-ios` Codemagic workflow on this branch and use its compile/test result as the gate before Apple signing/TestFlight is configured.
