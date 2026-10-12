<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteOfficeLoginFormRenderer;

require_once __DIR__ . '/../src/Intake/WebsiteOfficeLoginFormRenderer.php';

$renderer = new WebsiteOfficeLoginFormRenderer();
$token = bin2hex(random_bytes(32));
$html = $renderer->render($token);
foreach (['BREBO Office', 'method="post"', 'action="/office/inloggen"', 'type="password"', 'autocomplete="username"', 'name="csrf_token"', $token] as $required) {
  if (!str_contains($html, $required)) {
    throw new RuntimeException('Missing Office login form element: ' . $required);
  }
}
if (!str_contains($renderer->render($token, true), 'role="alert"')) {
  throw new RuntimeException('Login failure is not accessible.');
}
try {
  $renderer->render('<script>');
  throw new RuntimeException('Unsafe CSRF value was accepted.');
}
catch (InvalidArgumentException) {
  // Expected.
}
echo "Independent Office login form rendering checks passed.\n";
