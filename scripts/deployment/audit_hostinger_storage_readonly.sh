#!/usr/bin/env bash
# Read-only Hostinger release inventory. Never delete or modify server files.
set -euo pipefail
root="${1:-$HOME/domains/brebobv.nl/office_calc_test}"
if [[ ! -d "$root" ]]; then echo "AUDIT_BLOCKED_ROOT_MISSING"; exit 2; fi
root="$(cd "$root" && pwd -P)"
case "$root" in "$HOME"/domains/brebobv.nl/office_calc_test) ;; *) echo "AUDIT_BLOCKED_UNEXPECTED_ROOT"; exit 2;; esac
echo "AUDIT_MODE=READ_ONLY"
echo "AUDIT_ROOT=office_calc_test"
echo "RELEASE_DIRECTORY_SUMMARY"
find "$root" -mindepth 1 -maxdepth 1 -type d -name 'release-ci-*' -print0 |
while IFS= read -r -d '' path; do
  name="${path##*/}"
  [[ "$name" =~ ^release-ci-[A-Za-z0-9._-]+$ ]] || continue
  bytes="$(du -sk -- "$path" 2>/dev/null | awk '{print $1}')"
  files="$(find "$path" -xdev -type f 2>/dev/null | wc -l | tr -d ' ')"
  printf 'RELEASE=%s SIZE_KIB=%s FILES=%s\n' "$name" "${bytes:-unknown}" "$files"
done
echo "AUDIT_FINISHED_NO_CHANGES"
