<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteOfficeLoginController;
use Brebo\Office\Intake\WebsiteOfficeOperatorAuthenticator;
use Brebo\Office\Intake\WebsiteOfficeCrmSessionIssuer;
use Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository;

require_once __DIR__ . '/../src/Intake/WebsiteCrmOperatorContext.php';
require_once __DIR__ . '/../src/Intake/WebsiteOfficeOperatorAuthenticator.php';
require_once __DIR__ . '/../src/Intake/WebsiteOfficeCrmSessionIssuer.php';
require_once __DIR__ . '/../src/Intake/WebsiteOfficeCrmSessionRepository.php';
require_once __DIR__ . '/../src/Intake/WebsiteOfficeLoginController.php';

$dsn = getenv('OFFICE_INTAKE_TEST_DSN');
if (!$dsn) {
  echo "Skipping Office login MySQL integration test (no DSN).\n";
  exit(0);
}
$db = new PDO($dsn, getenv('OFFICE_INTAKE_TEST_USER') ?: '', getenv('OFFICE_INTAKE_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE IF NOT EXISTS office_operator_accounts (id VARCHAR(128) PRIMARY KEY, username VARCHAR(190) UNIQUE, password_hash VARCHAR(255), can_edit_crm TINYINT, active TINYINT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
$db->exec('CREATE TABLE IF NOT EXISTS office_operator_sessions (token_hash CHAR(64) PRIMARY KEY, operator_id VARCHAR(128), can_edit_crm TINYINT, expires_at DATETIME, revoked_at DATETIME NULL)');
$operator = 'login-test-' . bin2hex(random_bytes(5));
$insert = $db->prepare('INSERT INTO office_operator_accounts (id, username, password_hash, can_edit_crm, active) VALUES (?, ?, ?, 1, 1)');
$insert->execute([$operator, $operator, password_hash('Secure-test-password-2026', PASSWORD_DEFAULT)]);
$controller = new WebsiteOfficeLoginController(new WebsiteOfficeOperatorAuthenticator($db), new WebsiteOfficeCrmSessionIssuer($db));
$now = time();
if ($controller->login($operator, 'wrong-password', $now) !== null) {
  throw new RuntimeException('Wrong Office password issued a session.');
}
$token = $controller->login($operator, 'Secure-test-password-2026', $now);
if ($token === null || (new WebsiteOfficeCrmSessionRepository($db))->findActive($token, $now)?->operatorId !== $operator) {
  throw new RuntimeException('Valid Office login did not create an authenticated session.');
}
$controller->logout($token);
if ((new WebsiteOfficeCrmSessionRepository($db))->findActive($token, $now) !== null) {
  throw new RuntimeException('Office logout did not revoke session.');
}
// A revoked cookie must not regain access, even if the account remains active.
if ((new WebsiteOfficeCrmSessionRepository($db))->findActive($token, $now + 1) !== null) {
  throw new RuntimeException('Logged-out cookie regained CRM access.');
}
$secondToken = $controller->login($operator, 'Secure-test-password-2026', $now);
if ($secondToken === null || $secondToken === $token || (new WebsiteOfficeCrmSessionRepository($db))->findActive($secondToken, $now) === null) {
  throw new RuntimeException('New login after logout did not create an independent session.');
}
$controller->logout($secondToken);
$options = WebsiteOfficeLoginController::cookie($token, $now + 3600);
if (!$options['secure'] || !$options['httponly'] || $options['samesite'] !== 'Strict') {
  throw new RuntimeException('Office session cookie is not hardened.');
}
echo "Independent Office login and logout checks passed.\n";
