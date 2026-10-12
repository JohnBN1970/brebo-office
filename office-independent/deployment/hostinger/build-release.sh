#!/usr/bin/env bash
set -euo pipefail

# Build an inspectable, symlink-free Office release from the checked-out commit.
# Usage: bash office-independent/deployment/hostinger/build-release.sh /absolute/output/directory
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd -P)"
destination="${1:-}"
if [[ -z "$destination" || "$destination" != /* ]]; then
  echo "Specify an absolute output directory." >&2
  exit 2
fi
if [[ -e "$destination" ]]; then
  echo "Refusing to overwrite an existing release directory." >&2
  exit 2
fi
if [[ -L "$destination" || -L "$root/office-independent" ]]; then
  echo "Symlinks are forbidden." >&2
  exit 2
fi
if find "$root/office-independent" -type l -print -quit | grep -q .; then
  echo "Source tree contains a symlink; release aborted." >&2
  exit 2
fi
mkdir -p "$destination/public" "$destination/private"
cp "$root/office-independent/deployment/hostinger/index.php" "$destination/public/index.php"
cp "$root/office-independent/deployment/hostinger/.htaccess" "$destination/public/.htaccess"
cp -R "$root/office-independent/src" "$destination/private/src"
cp -R "$root/office-independent/public" "$destination/private/public"
cp -R "$root/office-independent/bin" "$destination/private/bin"
if find "$destination" -type l -print -quit | grep -q .; then
  echo "Release unexpectedly contains a symlink." >&2
  exit 1
fi
(
  cd "$destination"
  find public private -type f -print0 | sort -z | xargs -0 sha256sum > SHA256SUMS
)
git -C "$root" rev-parse HEAD > "$destination/GIT-REVISION"
echo "Release staged at $destination; verify SHA256SUMS and GIT-REVISION before upload."
