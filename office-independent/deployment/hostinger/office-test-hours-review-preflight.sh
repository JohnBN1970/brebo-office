#!/usr/bin/env bash
set -eu
# Read-only preflight for BREBO Office-test OnSite hours review.
# Run from the deployed private release directory. Never writes to production.
root="$(pwd)"
required="public/office-projects.php public/office-onsite-hours-review.php src/Inzet/OnSiteHoursReviewRepository.php src/Inzet/OnSiteHoursReviewReport.php src/Inzet/OnSiteHoursReviewRequest.php src/Inzet/OnSiteHoursReview.php src/Inzet/OfficeInzetHoursOverview.php src/Project/OfficeNativeProjectAccess.php"
printf '%s\n' '=== Office-test hours review preflight ==='
for file in $required; do
  if [ ! -f "$root/$file" ]; then
    printf 'MISSING: %s\n' "$file"
    exit 1
  fi
  php -l "$root/$file" >/dev/null
  printf 'OK: %s\n' "$file"
done
if [ ! -f "$root/deployment/hostinger/office-test-index.php" ]; then
  printf '%s\n' 'INFO: router manifest not in private release; verify public router separately.'
else
  grep -Fq "office-onsite-hours-review.php" "$root/deployment/hostinger/office-test-index.php" || {
    printf '%s\n' 'MISSING: review router mapping'
    exit 1
  }
fi
printf '%s\n' 'CODE PREFLIGHT PASS'
printf '%s\n' 'NOT VERIFIED: feature flags, Office session, DB schema, actual review write, CSV/PDF rendering.'
