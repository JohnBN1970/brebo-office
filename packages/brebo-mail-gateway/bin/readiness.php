<?php

declare(strict_types=1);

$required = [
  'BREBO_MAIL_GATEWAY_SERVER_KEY_ID',
  'BREBO_MAIL_GATEWAY_SERVER_SECRET',
  'BREBO_MAIL_GATEWAY_DATA_DIR',
  'BREBO_MAILSTACK_CONFIG_DIR',
];

$errors = [];
foreach ($required as $name) {
  if (trim((string) getenv($name)) === '') {
    $errors[] = $name . ' ontbreekt';
  }
}

$dataDir = rtrim((string) getenv('BREBO_MAIL_GATEWAY_DATA_DIR'), DIRECTORY_SEPARATOR);
$configDir = rtrim((string) getenv('BREBO_MAILSTACK_CONFIG_DIR'), DIRECTORY_SEPARATOR);

foreach ([$dataDir => 'data', $configDir => 'config'] as $directory => $label) {
  if ($directory === '') {
    continue;
  }
  if (!is_dir($directory) || !is_writable($directory)) {
    $errors[] = $label . ' directory is niet schrijfbaar: ' . $directory;
  }
}

$reloadEnabled = in_array(
  mb_strtolower(trim((string) getenv('BREBO_MAILSTACK_RELOAD_ENABLED'))),
  ['1', 'true', 'yes', 'on'],
  TRUE,
);

if ($reloadEnabled) {
  $errors[] = 'mailstack reload staat aan; pre-cutover readiness vereist reload=0';
}

if ($errors !== []) {
  foreach ($errors as $error) {
    fwrite(STDERR, 'ERROR: ' . $error . PHP_EOL);
  }
  exit(1);
}

echo "BREBO_MAIL_GATEWAY_READINESS=PASS\n";
echo "BREBO_MAILSTACK_RELOAD=DISABLED\n";
