<?php
declare(strict_types=1);

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
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
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!is_int($id)) { http_response_code(400); exit('Ongeldige bijlage.'); }

try {
    foreach (['OfficeMount.php', 'WebsiteCrmOperatorContext.php',
        'WebsiteCrmOperatorSessionProviderInterface.php',
        'WebsiteOfficeCrmSessionRepository.php',
        'WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
        require_once dirname(__DIR__) . '/src/Intake/' . $file;
    }
    require_once dirname(__DIR__) . '/src/Communication/OfficeCommunicationMailboxAccess.php';
    $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
        getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'u213420663_office_test') {
        throw new RuntimeException('Wrong database');
    }
    $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
        new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time());
    $operator = $provider->currentOperator();
    if ($operator === null) {
        header('Location: /office-test/inloggen', true, 302); exit;
    }
    $stmt = $db->prepare('SELECT a.sha256, a.filename, a.byte_size, m.mailbox_id
        FROM office_communication_attachments a
        INNER JOIN office_communication_messages m ON m.id = a.message_id
        WHERE a.id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$attachment) { http_response_code(404); exit('Bijlage niet gevonden.'); }
    $access = new \Brebo\Office\Communication\OfficeCommunicationMailboxAccess($db);
    if (!$access->canRead((string) $operator->operatorId, (int) $attachment['mailbox_id'])) {
        http_response_code(403); exit('Geen toegang tot deze bijlage.');
    }
    $hash = (string) $attachment['sha256'];
    $size = (int) $attachment['byte_size'];
    if (!preg_match('/^[a-f0-9]{64}$/D', $hash) || $size < 1 || $size > 15 * 1024 * 1024) {
        throw new RuntimeException('Invalid attachment metadata');
    }
    $vault = '/home/u213420663/office-test-mail-vault';
    $root = realpath($vault);
    $webroot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    if ($root === false || $webroot === false || is_link($vault)
        || $root === $webroot || str_starts_with($root, rtrim($webroot, '/') . '/')) {
        throw new RuntimeException('Unsafe vault');
    }
    $dir = $root . '/' . substr($hash, 0, 2);
    $path = $dir . '/' . $hash;
    if (is_link($dir) || is_link($path) || !is_file($path)
        || realpath($path) !== $path || filesize($path) !== $size
        || !hash_equals($hash, hash_file('sha256', $path))) {
        throw new RuntimeException('Attachment integrity failure');
    }
    $name = trim((string) $attachment['filename']);
    $name = preg_replace('/[\x00-\x1f\x7f\/\\\\]+/', '_', $name) ?? 'bijlage';
    if ($name === '' || $name === '.' || $name === '..') $name = 'bijlage';
    $name = mb_substr($name, 0, 180, 'UTF-8');
    $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: 'bijlage';
    $handle = fopen($path, 'rb');
    if ($handle === false) throw new RuntimeException('Attachment unreadable');
} catch (Throwable $e) {
    http_response_code(503); exit('Bijlage tijdelijk niet beschikbaar.');
}
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $fallback . '"; filename*=UTF-8' . "''" . rawurlencode($name));
header('Content-Length: ' . $size);
header('X-Content-Type-Options: nosniff');
fpassthru($handle);
fclose($handle);
