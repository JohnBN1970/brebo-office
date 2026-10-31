<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  header('Allow: GET'); http_response_code(405); exit;
}
if (getenv('OFFICE_COMMUNICATION_ENABLED') !== 'enabled'
  || getenv('OFFICE_COMMUNICATION_SCHEMA_VERIFIED') !== 'enabled'
  || getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled'
  || getenv('OFFICE_BASE_PATH') !== '/office-test') {
  http_response_code(503); exit('Communicatie is nog niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowed = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed) || $allowed === '' || !hash_equals($allowed, (string)$host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(403); exit;
}
try {
  foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
    require_once dirname(__DIR__) . '/src/Intake/' . $file;
  }
  require_once dirname(__DIR__) . '/src/Communication/OfficeCommunicationAccess.php';
  require_once dirname(__DIR__) . '/src/Communication/OfficeCommunicationInboxView.php';
  $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
    getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
  if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'u213420663_office_test') {
    throw new RuntimeException('Test database required');
  }
  $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time());
  $operator = $provider->currentOperator();
  if ($operator === null) {
    header('Location: ' . \Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302); exit;
  }
  $access = new \Brebo\Office\Communication\OfficeCommunicationAccess($db, $operator->operatorId);
  if (!$access->allows('read') && !$access->allows('review')) {
    http_response_code(403); exit('Geen toegang tot Communicatie.');
  }
  $q = $db->query("SELECT received_at, sender_address, subject, processing_status
    FROM office_communication_messages ORDER BY id DESC LIMIT 100");
  $messages = $q->fetchAll(PDO::FETCH_ASSOC);
  $html = \Brebo\Office\Communication\OfficeCommunicationInboxView::render($messages);
} catch (Throwable $e) {
  http_response_code(503); exit('Communicatie tijdelijk niet beschikbaar.');
}
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Communicatie · BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f4f6fa;color:#172638;margin:0}main{max-width:1100px;margin:40px auto;padding:0 20px}section{background:#fff;padding:24px;border:1px solid #dde3eb;border-radius:12px}table{width:100%;border-collapse:collapse}td,th{padding:12px;border-bottom:1px solid #e4e8ee;text-align:left}a{color:#175a91}</style></head><body><main><p><a href="/office-test/">Mijn werkplek</a></p>' . $html . '</main></body></html>';
