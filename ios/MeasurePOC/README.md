# BREBO Measure iOS POC

Minimal proof of concept for the first real BREBO Measure field experiment.

## Goal

Prove one thin vertical slice on a LiDAR-capable iPhone/iPad:

1. start an ARKit world-tracking session with scene depth;
2. let the operator select two points in the camera view;
3. resolve both selections to 3D world coordinates;
4. show their straight-line distance in millimetres;
5. prepare one BREBO Measure observation payload for the Office API introduced in PR #609.

This is not yet a production app and does not determine manufacturing dimensions.

## Scope limits

- no automatic opening recognition;
- no production approval;
- no AI validation;
- no point-cloud persistence yet;
- no authentication flow yet;
- no automatic upload until the API endpoint and auth mechanism are validated on the target environment.

## Build

The source is deliberately small and dependency-free. Create an iOS SwiftUI app target in Xcode, add the files in `Sources/`, and add the camera usage description below to the app target's Info settings:

`Privacy - Camera Usage Description` = `BREBO Measure gebruikt de camera en LiDAR om kozijnsparingen in 3D op te nemen.`

Target a LiDAR-capable iPhone/iPad and iOS 17 or newer for the POC.

## Success criterion

The same physical reference distance is measured repeatedly and compared with an independent laser/tape reference. We log repeatability and absolute deviation before adding more automation.
