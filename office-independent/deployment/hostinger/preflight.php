<?php

declare(strict_types=1);

// Read-only Hostinger CLI preflight. Run via PHP CLI, never publish this script.
// Usage: php office-independent/deployment/hostinger/preflight.php
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}
$checks = [
  'PHP >= 8.1' => PHP_VERSION_ID >= 80100,
  'PDO MySQL' => extension_loaded('pdo_mysql'),
  'OpenSSL' => extension_loaded('openssl'),
  'random_bytes' => function_exists('random_bytes'),
  'password_hash' => function_exists('password_hash'),
  'realpath' => function_exists('realpath'),
];
$root = getenv('OFFICE_PRIVATE_ROOT');
$private = is_string($root) && $root !== '' ? realpath($root) : false;
$checks['private root configured and readable'] = is_string($private) && is_dir($private . '/src') && is_dir($private . '/public') && is_readable($private . '/src');
$checks['private root outside public_html'] = is_string($private) && !str_contains($private . '/', '/public_html/');
$checks['no symlinks in private tree'] = is_string($private) && !is_link($private);
$checks['production database DSN configured'] = is_string(getenv('OFFICE_INTAKE_PDO_DSN')) && getenv('OFFICE_INTAKE_PDO_DSN') !== '';
$checks['production database username configured'] = is_string(getenv('OFFICE_INTAKE_DB_USER')) && getenv('OFFICE_INTAKE_DB_USER') !== '';
$checks['production database password configured'] = is_string(getenv('OFFICE_INTAKE_DB_PASSWORD')) && getenv('OFFICE_INTAKE_DB_PASSWORD') !== '';
$checks['Office public host configured'] = getenv('OFFICE_PUBLIC_HOST') === 'office.brebobv.nl';
foreach ($checks as $name => $passed) {
  echo ($passed ? '[OK] ' : '[FAIL] ') . $name . PHP_EOL;
}
echo "No credentials or database content were displayed or modified.\n";
exit(in_array(false, $checks, true) ? 1 : 0);
