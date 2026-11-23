<?php

declare(strict_types=1);

/**
 * Read-only Maildir delivery preflight. No service changes, no MX changes.
 * Run as root for permission visibility; does not create directories.
 */
$root = rtrim((string) (getenv('BREBO_MAILDIR_ROOT') ?: '/var/vmail'), '/');
if ($root === '' || $root === '/' || !str_starts_with($root, '/')) {
  fwrite(STDERR, "MAILDIR_PREFLIGHT=BLOCKED: invalid storage root\n");
  exit(2);
}
if (!is_dir($root)) {
  fwrite(STDERR, "MAILDIR_PREFLIGHT=BLOCKED: storage root missing\n");
  exit(2);
}
$stat = stat($root);
if ($stat === false || is_link($root)) {
  fwrite(STDERR, "MAILDIR_PREFLIGHT=BLOCKED: invalid storage directory\n");
  exit(2);
}
$mode = $stat['mode'] & 0777;
if (($mode & 0002) !== 0) {
  fwrite(STDERR, "MAILDIR_PREFLIGHT=BLOCKED: world-writable storage root\n");
  exit(2);
}
$uid = $stat['uid'];
$gid = $stat['gid'];
if ($uid === 0 || $gid === 0) {
  fwrite(STDERR, "MAILDIR_PREFLIGHT=BLOCKED: storage must be owned by dedicated non-root account\n");
  exit(2);
}
echo "MAILDIR_PREFLIGHT=PASS owner_uid=$uid group_gid=$gid\n";
