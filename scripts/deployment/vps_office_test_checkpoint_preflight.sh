#!/usr/bin/env bash
# Read-only inspection of Office-test release and rollback prerequisites.
set -euo pipefail
base=/srv/brebo/office-test
fail(){ printf 'CHECKPOINT_PREFLIGHT_BLOCKED:%s\n' "$1"; exit 1; }
[[ "$(id -un)" == "brebo-deploy" ]] || fail "unexpected_user"
[[ -d "$base" && ! -L "$base" ]] || fail "unsafe_test_root"
for name in releases shared checkpoints; do
  p="$base/$name"
  [[ -d "$p" && ! -L "$p" ]] || fail "unsafe_$name"
done
printf 'CHECKPOINT_PREFLIGHT_ROOT_OK\n'
df -Pk "$base" | awk 'NR==2 {printf "CHECKPOINT_PREFLIGHT_AVAILABLE_KB=%s\n",$4}'
for name in current active; do
  p="$base/$name"
  if [[ -L "$p" ]]; then
    resolved="$(readlink -f -- "$p")" || fail "broken_$name"
    case "$resolved" in "$base"/releases/*) ;; *) fail "pointer_outside_releases_$name";; esac
    [[ -d "$resolved" ]] || fail "missing_release_$name"
    printf 'CHECKPOINT_PREFLIGHT_%s_RELEASE=%s\n' "$name" "${resolved##*/}"
  elif [[ -e "$p" ]]; then
    printf 'CHECKPOINT_PREFLIGHT_%s_NON_SYMLINK\n' "$name"
  else
    printf 'CHECKPOINT_PREFLIGHT_%s_ABSENT\n' "$name"
  fi
done
printf 'CHECKPOINT_PREFLIGHT_RELEASE_DIR_COUNT=%s\n' "$(find "$base/releases" -mindepth 1 -maxdepth 1 -type d | wc -l)"
printf 'CHECKPOINT_PREFLIGHT_CHECKPOINT_DIR_COUNT=%s\n' "$(find "$base/checkpoints" -mindepth 1 -maxdepth 1 -type d | wc -l)"
printf 'CHECKPOINT_PREFLIGHT_INSPECTION_COMPLETE_NO_CHANGES\n'
