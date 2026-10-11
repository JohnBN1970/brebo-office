<?php

declare(strict_types=1);

$testDomains = array_values(array_filter(array_map(
  static fn(string $domain): string => mb_strtolower(trim($domain)),
  explode(',', (string) getenv('BREBO_MAIL_TEST_DOMAINS')),
)));

$protected = array_values(array_unique(array_filter(array_merge(
  ['brebobv.nl'],
  array_map(
    static fn(string $domain): string => mb_strtolower(trim($domain)),
    explode(',', (string) getenv('BREBO_MAIL_PROTECTED_DOMAINS')),
  ),
))));

$errors = [];
if ($testDomains === []) {
  $errors[] = 'BREBO_MAIL_TEST_DOMAINS ontbreekt.';
}

foreach ($testDomains as $domain) {
  if (filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === FALSE) {
    $errors[] = 'Ongeldig testdomein: ' . $domain;
    continue;
  }
  if (in_array($domain, $protected, TRUE)) {
    $errors[] = 'Protected production domain mag geen testdomein zijn: ' . $domain;
  }
}

$reloadEnabled = in_array(
  mb_strtolower(trim((string) getenv('BREBO_MAILSTACK_RELOAD_ENABLED'))),
  ['1', 'true', 'yes', 'on'],
  TRUE,
);
if ($reloadEnabled) {
  $errors[] = 'BREBO_MAILSTACK_RELOAD_ENABLED moet 0 blijven tijdens testhost preparation.';
}

if ($errors !== []) {
  foreach ($errors as $error) {
    fwrite(STDERR, 'ERROR: ' . $error . PHP_EOL);
  }
  exit(1);
}

echo 'BREBO_MAIL_TESTHOST_READINESS=PASS' . PHP_EOL;
echo 'TEST_DOMAINS=' . implode(',', $testDomains) . PHP_EOL;
echo 'PROTECTED_DOMAINS=' . implode(',', $protected) . PHP_EOL;
