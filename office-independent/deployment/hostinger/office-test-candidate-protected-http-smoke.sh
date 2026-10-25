#!/bin/sh
# Protected, GET-only HTTP smoke of candidate router. Does not change active router.
set -eu
ROOT=/home/u213420663/domains/brebobv.nl
PUBLIC="$ROOT/public_html"
CANDIDATE=${1:-/tmp/office-test-router-candidate.php}
DIR="$PUBLIC/office-candidate-probe-20261025"
PASSFILE="$ROOT/office_calc_test/office-candidate-probe-20261025.htpasswd"
PHP=/opt/alt/php83/usr/bin/php
[ -r "$CANDIDATE" ] || { echo "CANDIDATE: ROOD"; exit 1; }
[ ! -e "$DIR" ] && [ ! -e "$PASSFILE" ] || { echo "PROBE_ALREADY_EXISTS: ROOD"; exit 1; }
umask 022
mkdir -m 755 "$DIR"
cleanup() {
  rm -f "$DIR/index.php" "$DIR/.htaccess" "$PASSFILE"
  rmdir "$DIR" 2>/dev/null || true
}
trap cleanup EXIT HUP INT TERM
PASSWORD=$(openssl rand -hex 18)
HASH=$(openssl passwd -apr1 "$PASSWORD")
printf 'brebo-test:%s\n' "$HASH" > "$PASSFILE"
chmod 644 "$PASSFILE"
cat > "$DIR/.htaccess" <<EOF
AuthType Basic
AuthName "BREBO Isolated Candidate Smoke"
AuthUserFile $PASSFILE
Require valid-user
DirectoryIndex index.php
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^ index.php [END,QSA]
</IfModule>
EOF
cat > "$DIR/index.php" <<'PHPFILE'
<?php
declare(strict_types=1);
// GET-only public probe; real candidate is private and never copied to webroot.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  http_response_code(405);
  exit;
}
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$routes = [
  '/office-candidate-probe-20261025/unknown' => '/office-test/__router_preflight_missing_20261024',
  '/office-candidate-probe-20261025/dienst' => '/office-test/inzet/dienst/nieuw',
  '/office-candidate-probe-20261025/personeel' => '/office-test/inzet/medewerker/personeelsnummer',
];
if (!is_string($path) || !isset($routes[$path])) {
  http_response_code(404);
  exit;
}
$_SERVER['REQUEST_URI'] = $routes[$path];
require '/tmp/office-test-router-candidate.php';
PHPFILE
chmod 644 "$DIR/index.php" "$DIR/.htaccess"
BASE=https://brebobv.nl/office-candidate-probe-20261025
ANON=$(curl -sS --max-time 12 -o /dev/null -w '%{http_code}' "$BASE/unknown") || ANON=000
if [ "$ANON" != 401 ]; then
  echo "AUTH_GATE: ROOD (HTTP $ANON, verwacht 401)"
  exit 1
fi
echo "AUTH_GATE: GROEN (HTTP 401)"
FAILED=0
for item in 'UNKNOWN|unknown|404' 'DIENST_GET|dienst|405' 'PERSONEEL_GET|personeel|405'; do
  label=${item%%|*}; rest=${item#*|}; path=${rest%%|*}; expected=${rest##*|}
  status=$(curl -sS --max-time 12 --basic -u "brebo-test:$PASSWORD" -o /dev/null -w '%{http_code}' "$BASE/$path") || status=000
  if [ "$status" = "$expected" ]; then
    echo "$label: GROEN (HTTP $status)"
  else
    echo "$label: ROOD (HTTP $status, verwacht $expected)"
    FAILED=1
  fi
done
if [ "$FAILED" -eq 0 ]; then echo "PROTECTED_HTTP_SMOKE: GROEN"; else echo "PROTECTED_HTTP_SMOKE: ROOD"; fi
exit "$FAILED"
