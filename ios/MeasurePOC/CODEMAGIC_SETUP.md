# Codemagic first run

Use the repository workflow, not a manually recreated build configuration.

1. Sign in to Codemagic and add GitHub as repository provider.
2. Select repository `JohnBN1970/brebo-office`.
3. Choose configuration file `codemagic.yaml` from the repository root.
4. Select workflow `BREBO Measure iOS` (`brebo-measure-ios`).
5. Select branch `feature/measure-ios-cloud-build`.
6. Start the build.

The first run is intentionally unsigned and uses the iOS Simulator SDK. No Apple Developer secret is required for this compile/test gate.

If the build is green, the next change is signing/TestFlight. Do not add Apple credentials to GitHub files; keep them in Codemagic/App Store Connect secret storage.

If the build fails, keep the complete failing command/error from Codemagic. That result is the next engineering input.
