<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository;
use Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider;
use Brebo\Office\Intake\WebsiteCrmOperatorSessionProviderInterface;

require_once __DIR__ . '/../src/Intake/WebsiteCrmOperatorContext.php';
require_once __DIR__ . '/../src/Intake/WebsiteCrmOperatorSessionProviderInterface.php';
require_once __DIR__ . '/../src/Intake/WebsiteOfficeCrmSessionRepository.php';
require_once __DIR__ . '/../src/Intake/WebsiteOfficeCrmCookieSessionProvider.php';

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_operator_sessions (token_hash TEXT PRIMARY KEY, operator_id TEXT NOT NULL, can_edit_crm INTEGER NOT NULL, expires_at TEXT NOT NULL, revoked_at TEXT NULL)');
$now = 1791763200;
$token = str_repeat('a', 64);
$insert = $db->prepare('INSERT INTO office_operator_sessions VALUES (?, ?, ?, ?, ?)');
$insert->execute([hash('sha256', $token), 'operator-42', 1, gmdate('Y-m-d H:i:s', $now + 3600), null]);
$repository = new WebsiteOfficeCrmSessionRepository($db);
$provider = new WebsiteOfficeCrmCookieSessionProvider($repository, ['office_session' => $token], $now);
$operator = $provider->currentOperator();
if ($operator === null || $operator->operatorId !== 'operator-42' || !$operator->canEditCrm || $operator->sessionId === $token) {
  throw new RuntimeException('Valid Office session was not verified securely.');
}
if ((new WebsiteOfficeCrmCookieSessionProvider($repository, [], $now))->currentOperator() !== null
  || $repository->findActive(str_repeat('b', 64), $now) !== null
  || $repository->findActive('short', $now) !== null
  || $repository->findActive($token, $now + 3601) !== null) {
  throw new RuntimeException('Invalid or expired Office session was accepted.');
}
$db->exec("UPDATE office_operator_sessions SET revoked_at = '2026-10-12 00:00:00'");
if ($repository->findActive($token, $now) !== null) {
  throw new RuntimeException('Revoked Office session was accepted.');
}
echo "Independent Office session validation checks passed.\n";
