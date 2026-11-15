#!/usr/bin/env bash
# BREBO read-only Hostinger storage inventory. Never deletes or changes files.
set -euo pipefail
export LC_ALL=C
root="${1:-$HOME/domains/brebobv.nl}"
test -d "$root" || { echo "AUDIT_ROOT_MISSING"; exit 2; }
root="$(cd "$root" && pwd -P)"
case "$root" in "$HOME"/domains/*) ;; *) echo "AUDIT_ROOT_NOT_ALLOWED"; exit 2;; esac
echo "BREBO_STORAGE_AUDIT_V1"
echo "ROOT=$root"
date -u '+AUDIT_UTC=%Y-%m-%dT%H:%M:%SZ'
df -h "$root" | tail -n 1 | awk '{print "FILESYSTEM_SIZE="$2" USED="$3" AVAILABLE="$4" USE_PERCENT="$5}'
echo "TOP_LEVEL_SIZES_KIB"
du -k -d 1 "$root" 2>/dev/null | sort -nr | head -n 30 || true
echo "RELEASE_DIRECTORY_COUNTS_AND_SIZES_KIB"
for base in "$HOME/domains/brebobv.nl/office_calc_test" "$HOME/domains/brebobv.nl/public_html"; do
  if test -d "$base"; then
    echo "BASE=$(basename "$base")"
    find "$base" -maxdepth 1 -type d -name 'release-ci-*' -print | wc -l | awk '{print "RELEASE_COUNT="$1}'
    find "$base" -maxdepth 1 -type d -name 'release-ci-*' -exec du -sk {} + 2>/dev/null | sort -nr | head -n 15 | awk '{print "RELEASE_KIB="$1" RELEASE_DIR="$(NF)}' || true
  fi
done
echo "LARGE_FILE_SUMMARY_COUNTS_ONLY"
for threshold in 10240 102400 1048576; do
  count="$(find "$root" -type f -size +"$threshold"k 2>/dev/null | wc -l)"
  echo "FILES_OVER_${threshold}KIB=$count"
done
echo "CANDIDATE_COUNTS_NOT_DELETE_AUTHORIZATION"
for pattern in '*.zip' '*.tar' '*.tgz' '*.bak' '*.old' '*.log'; do
  count="$(find "$root" -type f -name "$pattern" 2>/dev/null | wc -l)"
  echo "PATTERN=$pattern COUNT=$count"
done
echo "NO_FILES_CHANGED"
