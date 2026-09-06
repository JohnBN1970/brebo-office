# BREBO Measure cloud build

This path keeps development in GitHub/Windows while compiling iOS on a hosted macOS runner.

## Phase 1 - unsigned build proof

The repository root contains `codemagic.yaml`. The first workflow intentionally generates the Xcode project from `project.yml` and performs an unsigned iOS Simulator build. This proves that the source tree compiles without requiring Apple signing credentials.

1. Connect the `JohnBN1970/brebo-office` repository in Codemagic.
2. Select the `brebo-measure-ios` workflow.
3. Build branch `feature/measure-ios-cloud-build`.
4. Fix compilation/project-generation issues until the cloud build is green.

## Phase 2 - physical iPhone / TestFlight

Only after Phase 1 is green:

- connect Apple Developer / App Store Connect credentials in Codemagic;
- create/register the final BREBO Measure bundle identifier;
- enable automatic code signing;
- archive for a physical iOS device;
- publish the build to TestFlight;
- install it on the LiDAR-capable test iPhone.

Signing credentials and API keys must stay in Codemagic/Apple secret storage and must never be committed to this repository.

## Product gate

Cloud distribution is infrastructure only. It does not change the Measure validation rule: the first useful scanner must automatically collect spatial data and derive opening geometry without requiring the operator to tap exact measurement endpoints.
