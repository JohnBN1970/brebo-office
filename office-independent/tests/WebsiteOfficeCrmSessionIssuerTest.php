<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteOfficeCrmSessionIssuer;
use Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository;
use Brebo\Office\Intake\WebsiteCrmOperatorContext;

require_once __DIR__ . '/../src/Intake/WebsiteCrmOperatorContext.php';
require_once __DIR__ . '/../src/Intake/WebsiteOfficeCrmSessionRepository.php';
require_once __DIR__ . '/../src/Intake/WebsiteOfficeCrmSessionIssuer.php';

$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
if (!$dsn) {
  echo "Skipping Office session MySQL integration test (no DSN).\n";
  exit(0);
}
$db = new PDO($dsn, getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE IF NOT EXISTS office_operator_sessions (token_hash CHAR(64) PRIMARY KEY, operator_id VARCHAR(128) NOT NULL, can_edit_crm TINYINT NOT NULL, expires_at DATETIME NOT NULL, revoked_at DATETIME NULL)');
$issuer = new WebsiteOfficeCrmSessionIssuer($db);
$reader = new WebsiteOfficeCrmSessionRepository($db);
$operator = 'office-test-' . bin2hex(random_bytes(6));
$now = time();
$token = $issuer->issue($operator, true, $now);
if (strlen($token) !== 64 || $reader->findActive($token, $now)?->operatorId !== $operator) {
  throw new RuntimeException('New Office session was not persisted or validated.');
}
$issuer->revoke($token);
if ($reader->findActive($token, $now) !== null) {
  throw new RuntimeException('Revoked Office session was accepted.');
}
$token2 = $issuer->issue($operator, true, $now);
$issuer->revokeAll($operator);
if ($reader->findActive($token2, $now) !== null) {
  throw new RuntimeException('Operator-wide revocation failed.');
}
echo "Office session issuance and revocation checks passed.\n";
