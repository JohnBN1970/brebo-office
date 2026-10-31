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
    http_response_code(503); exit('Communicatie niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowed = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed) || $allowed === '' || !is_string($host)
    || !hash_equals($allowed, $host)
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    http_response_code(403); exit;
}
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if (!is_int($id)) { http_response_code(400); exit('Ongeldig bericht.'); }
try {
    foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
        require_once dirname(__DIR__) . '/src/Intake/' . $file;
    }
    require_once dirname(__DIR__) . '/src/Communication/OfficeCommunicationMailboxAccess.php';
    $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
        getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'u213420663_office_test') {
        throw new RuntimeException('Wrong database');
    }
    $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
        new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time());
    $operator = $provider->currentOperator();
    if ($operator === null) {
        header('Location: /office-test/inloggen', true, 302); exit;
    }
    $stmt = $db->prepare('SELECT id, mailbox_id, sender_address, subject, body_text, received_at
        FROM office_communication_messages WHERE id = :id LIMIT 1');
    $stmt->execute([':id'=>$id]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$message) { http_response_code(404); exit('Bericht niet gevonden.'); }
    $access = new \Brebo\Office\Communication\OfficeCommunicationMailboxAccess($db);
    if (!$access->canRead((string)$operator->operatorId, (int)$message['mailbox_id'])) {
        http_response_code(403); exit('Geen toegang tot dit bericht.');
    }
    $attachments = $db->prepare('SELECT filename, mime_type, byte_size
        FROM office_communication_attachments WHERE message_id = :id ORDER BY id');
    $attachments->execute([':id'=>$id]);
    $files = $attachments->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(503); exit('Bericht tijdelijk niet beschikbaar.');
}
$e = static fn(mixed $v): string => htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Bericht · BREBO Office</title><style>body{background:#f4f6f9;color:#1b2634;font:16px system-ui,sans-serif;margin:0}main{max-width:900px;margin:40px auto;padding:0 20px}article{background:#fff;border:1px solid #dbe1e8;border-radius:12px;padding:24px}pre{font:inherit;white-space:pre-wrap;overflow-wrap:anywhere}li{margin:8px 0}a{color:#175a91}</style></head><body><main><p><a href="/office-test/communicatie">Terug naar inbox</a></p><article><h1>' . $e($message['subject']) . '</h1><p><strong>Van:</strong> ' . $e($message['sender_address']) . '</p><p><strong>Ontvangen:</strong> ' . $e($message['received_at']) . '</p><hr><pre>' . $e($message['body_text']) . '</pre><h2>Bijlagen (' . count($files) . ')</h2>';
if ($files === []) echo '<p>Geen bijlagen.</p>';
else {
    echo '<ul>';
    foreach ($files as $file) {
        echo '<li>' . $e($file['filename']) . ' (' . $e($file['mime_type']) . ', ' . (int)$file['byte_size'] . ' bytes) — downloaden volgt</li>';
    }
    echo '</ul>';
}
echo '</article></main></body></html>';
