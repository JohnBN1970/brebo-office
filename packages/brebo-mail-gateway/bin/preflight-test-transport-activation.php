<?php

declare(strict_types=1);

/**
 * Read-only preflight: verifies generated test transport fragments are not
 * mistaken for active Postfix configuration.
 */
$dir = rtrim((string) getenv('BREBO_MAILSTACK_CONFIG_DIR'), DIRECTORY_SEPARATOR);
if ($dir === '') {
  fwrite(STDERR, "BREBO_MAILSTACK_CONFIG_DIR is required.\n");
  exit(2);
}

$map = $dir . '/transport_map';
$main = $dir . '/main_cf_fragment';
$master = $dir . '/master_cf_fragment';
foreach ([$map, $main, $master, $map . '.db'] as $file) {
  if (!is_file($file) || !is_readable($file)) {
    fwrite(STDERR, "Missing or unreadable generated artifact: {$file}\n");
    exit(2);
  }
}

$expected = trim((string) file_get_contents($main));
if ($expected !== 'transport_maps = hash:/etc/brebo-mail-stack/generated/transport_map') {
  fwrite(STDERR, "Unexpected transport map declaration.\n");
  exit(2);
}

$masterText = (string) file_get_contents($master);
if (!str_contains($masterText, 'brebo_inbound unix') || !str_contains($masterText, 'brebo-mail-deliver')) {
  fwrite(STDERR, "Missing inbound pipe declaration.\n");
  exit(2);
}

$allowed = array_filter(array_map('trim', explode(',', (string) getenv('BREBO_MAIL_TEST_DOMAINS'))));
$allowed = array_map('strtolower', $allowed);
foreach (file($map, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
  $parts = preg_split('/\\s+/', trim($line), 2);
  $domain = strtolower($parts[0] ?? '');
  if (!in_array($domain, $allowed, TRUE) || ($parts[1] ?? '') !== 'brebo_inbound:') {
    fwrite(STDERR, "Untrusted or invalid test transport entry.\n");
    exit(2);
  }
}

echo "BREBO_MAIL_TEST_TRANSPORT_ARTIFACTS=PASS\n";
echo "BREBO_MAIL_TEST_TRANSPORT_ACTIVATION=NOT_VERIFIED\n";
