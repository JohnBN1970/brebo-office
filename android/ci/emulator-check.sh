set -uo pipefail
mkdir -p app/build/emulator-artifacts
status=0
bash gradlew -PonsiteApiBaseUrl=https://127.0.0.1:1 :app:connectedDebugAndroidTest --console=plain || status=$?
adb pull /sdcard/Download/onsite-welcome.png app/build/emulator-artifacts/onsite-welcome.png || true
adb logcat -d > app/build/emulator-artifacts/logcat.txt || true
exit "$status"
