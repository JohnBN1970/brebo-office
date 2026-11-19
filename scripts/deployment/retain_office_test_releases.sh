#!/usr/bin/env bash
# Office-test CI release retention. Run only after successful acceptance.
# Dry-run by default. Never touches production, legacy folders, or backups.
set -euo pipefail
mode="${1:---dry-run}"
[[ "$mode" == "--dry-run" || "$mode" == "--apply" ]] || exit 2
base="$HOME/domains/brebobv.nl/office_calc_test"
[[ -d "$base" && ! -L "$base" && -f "$base/config/config.php" && ! -L "$base/config/config.php" ]] || exit 3
base="$(cd "$base" && pwd -P)"
[[ "$base" == "$HOME/domains/brebobv.nl/office_calc_test" ]] || exit 3
# Reuse the deployment lock; prevent cleanup racing a deployment.
exec 9>"$base/.development-deploy.lock"
flock -w 120 9 || exit 4
config="$base/config/config.php"
active="$(php -r '
$c=require $argv[1];
if (!is_array($c) || ($c["OFFICE_BASE_PATH"]??"")!=="/office-test") exit(2);
echo $c["OFFICE_PRIVATE_ROOT"]??"";
' "$config")" || exit 5
[[ "$active" == "$base"/release-ci-*/private && -d "$active" && ! -L "$active" ]] || exit 5
active="$(cd "$active" && pwd -P)"
[[ "$active" == "$base"/release-ci-*/private ]] || exit 5
# Checkpoint config from the just accepted deployment may be needed for rollback.
protected_previous=""
if [[ -n "${2:-}" ]]; then
  sha="$2"
  [[ "$sha" =~ ^[0-9a-f]{40}$ ]] || exit 6
  checkpoint="$base/development-checkpoints/$sha/config.php"
  if [[ -f "$checkpoint" ]]; then
    protected_previous="$(php -r '
$c=require $argv[1];
if (!is_array($c) || ($c["OFFICE_BASE_PATH"]??"")!=="/office-test") exit(2);
echo $c["OFFICE_PRIVATE_ROOT"]??"";
' "$checkpoint")" || exit 6
    [[ "$protected_previous" == "$base"/release-ci-*/private ]] || exit 6
  fi
fi
mapfile -t releases < <(
  find "$base" -mindepth 1 -maxdepth 1 -type d -name 'release-ci-*' -printf '%T@ %f\n' |
  sort -rn | awk '{print $2}'
)
kept=0
for name in "${releases[@]}"; do
  [[ "$name" =~ ^release-ci-[0-9a-f]{40}$ ]] || continue
  dir="$base/$name"
  [[ ! -L "$dir" && -d "$dir/private" && ! -L "$dir/private" ]] || continue
  if (( kept < 5 )); then
    kept=$((kept+1))
    echo "KEEP_RECENT=$name"
    continue
  fi
  if [[ "$active" == "$dir/private" || "$protected_previous" == "$dir/private" ]]; then
    echo "KEEP_PROTECTED=$name"
    continue
  fi
  if [[ "$mode" == "--dry-run" ]]; then
    echo "WOULD_REMOVE=$name"
  else
    rm -rf -- "$dir"
    echo "REMOVED=$name"
  fi
done
echo "RETENTION_MODE=$mode"
