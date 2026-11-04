<?php
declare(strict_types=1);

// Read-only CLI inspection against the configured Office database.
// Run on the Office-test host with its existing environment; never via HTTP.
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}
require_once dirname(__DIR__) . '/src/Finance/OfficePaymentReadiness.php';

$dsn = getenv('OFFICE_INTAKE_PDO_DSN');
if (!is_string($dsn) || $dsn === '' || !str_starts_with($dsn, 'mysql:')) {
  fwrite(STDERR, "BLOCKED: Office MySQL DSN not configured\n");
  exit(2);
}
try {
  $pdo = new PDO(
    $dsn,
    getenv('OFFICE_INTAKE_DB_USER') ?: '',
    getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_EMULATE_PREPARES => false,
     PDO::ATTR_TIMEOUT => 5]
  );
  // This diagnostic never grants operator authority.
  $snapshot = \Brebo\Office\Finance\OfficePaymentReadiness::inspect(
    $pdo,
    getenv('OFFICE_PAYMENT_CANONICAL_SCHEMA_VERIFIED') === 'enabled',
    false
  );
  echo "OFFICE PAYMENT PREFLIGHT (READ ONLY)\n";
  foreach ($snapshot['checks'] as $key => $passed) {
    echo ($passed ? 'PASS' : 'BLOCK') . ' ' . $key . "\n";
  }
  echo "RESULT: BLOCKED (CLI has no operator authorization and cannot submit)\n";
  exit(0);
} catch (Throwable $e) {
  // Never print connection strings, SQL or credentials.
  fwrite(STDERR, "BLOCKED: Office database preflight unavailable\n");
  exit(2);
}
