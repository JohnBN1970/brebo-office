#!/usr/bin/env bash
# Install an isolated HTTP-only Nginx vhost. Does not modify DNS or TLS.
set -euo pipefail
[[ $(id -u) -eq 0 ]] || { echo ROOT_REQUIRED >&2; exit 10; }
name=office-test.brebobv.nl
target=/etc/nginx/sites-available/$name
enabled=/etc/nginx/sites-enabled/$name
root=/srv/brebo/office-test/current/public
socket=/run/php/brebo-office-test.sock
[[ -S $socket ]] || { echo OFFICE_TEST_FPM_SOCKET_MISSING >&2; exit 11; }
[[ -d /srv/brebo/office-test && ! -L /srv/brebo/office-test ]] || exit 12
[[ ! -e $target && ! -L $target && ! -e $enabled && ! -L $enabled ]] || { echo VHOST_ALREADY_EXISTS >&2; exit 13; }
tmp=$(mktemp /etc/nginx/sites-available/.office-test.XXXXXXXX)
trap 'rm -f -- "$tmp"' EXIT
cat > "$tmp" <<'NGINX'
server {
    listen 80;
    listen [::]:80;
    server_name office-test.brebobv.nl;
    root /srv/brebo/office-test/current/public;
    index index.php;
    autoindex off;
    server_tokens off;
    location ~ /\\. { deny all; }
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \\.php$ {
        try_files $uri =404;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_pass unix:/run/php/brebo-office-test.sock;
    }
    location ~* \\.(?:env|ini|log|sql|bak|old)$ { deny all; }
}
NGINX
chmod 644 "$tmp"
chown root:root "$tmp"
mv "$tmp" "$target"
ln -s "$target" "$enabled"
if ! nginx -t; then
  rm -f "$enabled" "$target"
  echo NGINX_VALIDATION_FAILED_ROLLED_BACK >&2
  exit 14
fi
systemctl reload nginx
echo OFFICE_TEST_NGINX_HTTP_READY
echo APPLICATION_RELEASE_STILL_REQUIRED
echo DNS_AND_TLS_NOT_CHANGED
