<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteOfficeLoginCsrf;

require_once __DIR__ . '/../src/Intake/WebsiteOfficeLoginCsrf.php';

$token = WebsiteOfficeLoginCsrf::issue();
if (strlen($token) !== 64 || !WebsiteOfficeLoginCsrf::verify($token, $token)) {
  throw new RuntimeException('Valid login CSRF token rejected.');
}
foreach ([
  [null, $token],
  [$token, null],
  [$token, str_repeat('0', 64)],
  ['<script>', '<script>'],
  [strtoupper($token), $token],
] as [$submitted, $cookie]) {
  if (WebsiteOfficeLoginCsrf::verify($submitted, $cookie)) {
    throw new RuntimeException('Invalid login CSRF token accepted.');
  }
}
$options = WebsiteOfficeLoginCsrf::cookieOptions(time() + 3600);
if (!$options['secure'] || !$options['httponly'] || $options['samesite'] !== 'Strict') {
  throw new RuntimeException('Pre-login cookie is not hardened.');
}
echo "Independent Office login CSRF checks passed.\n";
