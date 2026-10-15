<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyIntegrityAudit.php';

$dsn = getenv('OFFICE_INTAKE_PDO_DSN');
if (!is_string($dsn) || $dsn === '') {
  fwrite(STDERR, "Independent CRM database DSN missing.\n");
  exit(2);
}
try {
  $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  $result = (new \Brebo\Office\Intake\WebsiteCrmLegacyIntegrityAudit($db))->report();
  fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n");
  exit($result['blocking_issues'] === 0 ? 0 : 1);
}
catch (Throwable $e) {
  fwrite(STDERR, "CRM integrity audit unavailable: {$e->getMessage()}\n");
  exit(2);
}
