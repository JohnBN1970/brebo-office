<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteOfficeLoginThrottle;

require_once __DIR__ . '/../src/Intake/WebsiteOfficeLoginThrottle.php';

$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
if (!$dsn) {
  echo "Skipping Office login throttle MySQL test (no DSN).\n";
  exit(0);
}
$db = new PDO($dsn, getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
WebsiteOfficeLoginThrottle::install($db);
$throttle = new WebsiteOfficeLoginThrottle($db);
$key = 'test:' . bin2hex(random_bytes(16));
$now = time();
if (!$throttle->allowed($key, $now)) {
  throw new RuntimeException('Fresh account unexpectedly blocked.');
}
for ($i = 0; $i < 5; $i++) {
  $throttle->failed($key, $now);
}
if ($throttle->allowed($key, $now + 1)) {
  throw new RuntimeException('Five failed attempts did not block login.');
}
if (!$throttle->allowed($key, $now + 901)) {
  throw new RuntimeException('Login did not unblock after fifteen minutes.');
}
$throttle->reset($key);
if (!$throttle->allowed($key, $now)) {
  throw new RuntimeException('Reset did not clear throttle.');
}
echo "Independent Office login throttling checks passed.\n";
