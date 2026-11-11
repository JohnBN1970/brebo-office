<?php
declare(strict_types=1);

// Isolated Office-test-only server-to-server personal mail staging endpoint.
// Does not classify, copy attachments or create dossier relationships.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$fail = static function (int $status, string $code): never {
  http_response_code($status);
  echo json_encode(['error' => $code], JSON_THROW_ON_ERROR);
  exit;
};
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
  || getenv('OFFICE_PERSONAL_MAIL_PUSH_RECEIVER_ENABLED') !== 'enabled'
  || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  $fail(404, 'not_found');
}
$secret = getenv('OFFICE_PERSONAL_MAIL_PUSH_SECRET');
if (!is_string($secret) || strlen($secret) < 32) {
  $fail(503, 'not_configured');
}
$raw = file_get_contents('php://input', false, null, 0, 8193);
if (!is_string($raw) || $raw === '' || strlen($raw) > 8192
  || (isset($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 8192)) {
  $fail(413, 'invalid_payload_size');
}
require_once dirname(__DIR__) . '/src/Intake/OfficeIntakeSourceAuthenticator.php';
require_once dirname(__DIR__) . '/src/Intake/OfficeIntakeReplayGuard.php';
require_once dirname(__DIR__) . '/src/Mail/OfficeMailPushProposalRepository.php';
try {
  (new \Brebo\Office\Intake\OfficeIntakeSourceAuthenticator(['personal_mail' => $secret]))
    ->verify(
      (string) ($_SERVER['HTTP_X_OFFICE_INTAKE_SOURCE'] ?? ''),
      $raw,
      (string) ($_SERVER['HTTP_X_OFFICE_INTAKE_TIMESTAMP'] ?? ''),
      (string) ($_SERVER['HTTP_X_OFFICE_INTAKE_NONCE'] ?? ''),
      (string) ($_SERVER['HTTP_X_OFFICE_INTAKE_SIGNATURE'] ?? ''),
      time()
    );
} catch (DomainException $e) {
  $fail(401, 'unauthorized');
}
try {
  $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
  if (!is_array($data)
    || array_diff(array_keys($data), ['source_id', 'owner_uid', 'actor_uid', 'subject']) !== []
    || !is_string($data['source_id'] ?? null)
    || !is_string($data['subject'] ?? null)
    || !is_int($data['owner_uid'] ?? null)
    || !is_int($data['actor_uid'] ?? null)) {
    $fail(422, 'invalid_payload');
  }
  $db = new PDO(
    getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
    getenv('OFFICE_INTAKE_DB_USER') ?: '',
    getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
  );
  if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'u213420663_office_test') {
    $fail(503, 'not_configured');
  }
  (new \Brebo\Office\Intake\OfficeIntakeReplayGuard($db))
    ->reserve('personal_mail', (string) ($_SERVER['HTTP_X_OFFICE_INTAKE_NONCE'] ?? ''));
  $result = (new \Brebo\Office\Mail\OfficeMailPushProposalRepository($db))->stage($data);
  http_response_code(202);
  echo json_encode($result, JSON_THROW_ON_ERROR);
} catch (JsonException|RuntimeException|InvalidArgumentException $e) {
  $fail(422, 'invalid_payload');
} catch (DomainException $e) {
  $fail(401, 'unauthorized');
} catch (Throwable $e) {
  error_log('office_personal_mail_push_failed: ' . $e::class);
  $fail(503, 'unavailable');
}
