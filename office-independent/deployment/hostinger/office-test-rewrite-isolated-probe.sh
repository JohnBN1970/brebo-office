#!/bin/sh
# Isolated Apache rewrite test, never modifies /office-test.
set -eu
ROOT=/home/u213420663/domains/brebobv.nl
DIR="$ROOT/public_html/office-rewrite-probe-20261025"
URL="https://brebobv.nl/office-rewrite-probe-20261025"
test ! -e "$DIR" || { echo "PROBE_EXISTS: STOP"; exit 1; }
mkdir -m 755 "$DIR"
cleanup() {
  rm -f "$DIR/.htaccess" "$DIR/index.php"
  rmdir "$DIR" 2>/dev/null || true
}
trap cleanup EXIT
trap 'exit 130' HUP INT TERM
cat > "$DIR/.htaccess" <<'RULES'
DirectoryIndex index.php
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^inzet/dienst/nieuw/?$ index.php [END,QSA]
RewriteRule ^inzet/medewerker/personeelsnummer/?$ index.php [END,QSA]
</IfModule>
RULES
cat > "$DIR/index.php" <<'PHP'
<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-BREBO-Rewrite-Probe: reached');
http_response_code(209);
echo "BREBO_REWRITE_PROBE_OK\n";
PHP
chmod 644 "$DIR/.htaccess" "$DIR/index.php"
FAILED=0
for item in 'DIRECT|/index.php' 'SHIFT|/inzet/dienst/nieuw' 'PERSONNEL|/inzet/medewerker/personeelsnummer'; do
  label=${item%%|*}
  route=${item#*|}
  headers=$(mktemp)
  body=$(mktemp)
  status=$(curl -sS --max-time 15 -D "$headers" -o "$body" -w '%{http_code}' "$URL$route?probe=20261025") || status=000
  if [ "$status" = 209 ] && grep -qi '^x-brebo-rewrite-probe: reached' "$headers" && grep -q '^BREBO_REWRITE_PROBE_OK' "$body"; then
    echo "$label: GROEN (HTTP 209; PHP bereikt)"
  else
    echo "$label: ROOD (HTTP $status)"
    echo "RESPONSE_CONTENT_TYPE: $(grep -i '^content-type:' "$headers" | head -n 1 || true)"
    FAILED=1
  fi
  rm -f "$headers" "$body"
done
if [ "$FAILED" -eq 0 ]; then echo "ISOLATED_REWRITE_PROBE: GROEN"; else echo "ISOLATED_REWRITE_PROBE: ROOD"; fi
exit "$FAILED"
