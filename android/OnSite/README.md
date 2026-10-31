# BREBO OnSite Android

Isolated Android foundation; does not change iOS or Office-test. Kotlin + Jetpack Compose, minSdk 26, targetSdk 35.

## Build
Open `android/OnSite` in Android Studio with JDK 17 and installed Android SDK 35. Build the `app` module. No APK or successful build is claimed yet.

## Contract (to implement)
- POST `/office-test/api/onsite/v1/activate`: `{"activation_token":"..."}`, response `device_token`
- GET `/office-test/api/onsite/v1/bootstrap`: Bearer device token; employee, projects and building zones
- POST `/office-test/api/onsite/v1/presence`: Bearer token; stable `event_id` and nested `event` with project_id, zone_id, kind (in/out), occurred_at

## Next gates
1. Verified WhatsApp activation deep-link handling and Android Keystore token storage.
2. Contract tests against Office-test, without changing server routes.
3. Explicit foreground/background location permissions and Android geofencing.
4. Durable encrypted queue, retry/backoff and idempotent event delivery.
5. Real device acceptance with screen locked and battery optimizations.

Do not use the legacy sboffice default endpoint. Never log device tokens.
