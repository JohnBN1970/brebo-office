#!/usr/bin/env bash
# Read-only database and schema inventory. Never imports SQL or prints credentials.
set -euo pipefail
umask 077
[[ $(id -u) -eq 0 ]] || { echo 'RUN_AS_ROOT' >&2; exit 10; }
env_file=/etc/brebo/office-test-db.env
[[ -f "$env_file" && ! -L "$env_file" ]] || { echo 'DB_ENV_MISSING' >&2; exit 11; }
[[ $(stat -c %U "$env_file") == root ]] || exit 12
set -a
# shellcheck disable=SC1090
source "$env_file"
set +a
: "${OFFICE_TEST_DB_NAME:?}" "${OFFICE_TEST_DB_USER:?}" "${OFFICE_TEST_DB_PASSWORD:?}"
[[ "$OFFICE_TEST_DB_NAME" == brebo_office_test ]] || { echo 'UNEXPECTED_DB_NAME' >&2; exit 13; }
command -v php >/dev/null || exit 14
php <<'PHP'
<?php
declare(strict_types=1);
$host=getenv('OFFICE_TEST_DB_HOST') ?: 'localhost';
$name=getenv('OFFICE_TEST_DB_NAME');
$user=getenv('OFFICE_TEST_DB_USER');
$pass=getenv('OFFICE_TEST_DB_PASSWORD');
if ($name !== 'brebo_office_test') {fwrite(STDERR,"DB_NAME_REJECTED\\n");exit(15);}
try {
 $db=new PDO('mysql:host='.$host.';dbname='.$name.';charset=utf8mb4',$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $tables=$db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
 echo 'OFFICE_TEST_DB_CONNECTION_OK'.PHP_EOL;
 echo 'OFFICE_TEST_DB_TABLE_COUNT='.count($tables).PHP_EOL;
 foreach ($tables as $table) echo 'TABLE='.$table.PHP_EOL;
} catch(Throwable $e) {fwrite(STDERR,"OFFICE_TEST_DB_PREFLIGHT_FAILED\\n");exit(16);}
PHP
release=/srv/brebo/office-test/releases
[[ -d "$release" ]] || exit 17
echo 'RELEASES:'
find "$release" -mindepth 2 -maxdepth 2 -name .office-source-sha -type f -print
echo 'SQL_SCHEMA_FILES:'
find "$release" -type f -name '*.sql' -print | sort
echo 'PREFLIGHT_READ_ONLY=YES'
