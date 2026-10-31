<?php
declare(strict_types=1);

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST'); http_response_code(405); exit;
}
if (getenv('OFFICE_COMMUNICATION_ENABLED') !== 'enabled'
    || getenv('OFFICE_COMMUNICATION_SCHEMA_VERIFIED') !== 'enabled'
    || getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled'
    || getenv('OFFICE_BASE_PATH') !== '/office-test') {
    http_response_code(503); exit('Mailboxbeheer niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!is_string($host) || $host === '' || $host !== getenv('OFFICE_PUBLIC_HOST')
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    http_response_code(403); exit;
}
try {
    foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
        require_once dirname(__DIR__) . '/src/Intake/' . $file;
    }
    require_once dirname(__DIR__) . '/src/Communication/OfficeCommunicationAccess.php';
    require_once dirname(__DIR__) . '/src/Communication/OfficeMailboxRegistry.php';
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
    if (!$access->allows('review')) {
        http_response_code(403); exit('Geen beheerrechten voor Communicatie.');
    }
    $registry = new \Brebo\Office\Communication\OfficeMailboxRegistry($db);
    // Separate CSRF session bound to the authenticated Office operator session.
    session_name('BREBO_COMMUNICATION_CSRF');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/office-test',
        'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
    if (!session_start()) throw new RuntimeException('CSRF session unavailable');
    if (($_SESSION['operator_session'] ?? null) !== $operator->sessionId) {
        session_regenerate_id(true);
        $_SESSION = ['operator_session' => $operator->sessionId, 'csrf' => bin2hex(random_bytes(32))];
    }
    $error = '';
    if ($method === 'POST') {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $expectedOrigin = 'https://' . $host;
        $token = $_POST['csrf'] ?? null;
        if (!is_string($origin) || !hash_equals($expectedOrigin, $origin)
            || !is_string($token) || !hash_equals((string) $_SESSION['csrf'], $token)) {
            http_response_code(403); exit('Ongeldige aanvraag.');
        }
        try {
            if (($_POST['action'] ?? '') === 'register') {
                $registry->register((string) ($_POST['address'] ?? ''), (string) ($_POST['display_name'] ?? ''));
            } elseif (($_POST['action'] ?? '') === 'disable') {
                $registry->disable(filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: 0);
            } else {
                throw new InvalidArgumentException('Onbekende actie.');
            }
            header('Location: /office-test/communicatie/mailboxen?opgeslagen=1', true, 303);
            exit;
        } catch (InvalidArgumentException|PDOException $e) {
            $error = 'Controleer de invoer. Dit adres bestaat mogelijk al.';
        }
    }
    $rows = $registry->all();
    $csrf = (string) $_SESSION['csrf'];
    session_write_close();
} catch (Throwable $e) {
    http_response_code(503); exit('Mailboxbeheer tijdelijk niet beschikbaar.');
}
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mailboxen · BREBO Office</title><style>body{font:16px system-ui;background:#f4f6fa;color:#172638;margin:0}main{max-width:980px;margin:40px auto;padding:0 20px}section{background:white;padding:24px;border:1px solid #dde3eb;border-radius:12px;margin:16px 0}label{display:block;margin:12px 0}input{padding:10px;width:min(100%,420px);box-sizing:border-box}button{padding:9px 14px;cursor:pointer}table{border-collapse:collapse;width:100%}th,td{padding:12px;border-bottom:1px solid #e4e8ee;text-align:left}small{color:#526175}</style></head><body><main><p><a href="/office-test/communicatie">← Communicatie</a></p><h1>Mailboxen</h1><p>Registreer bestaande Zoho-mailboxen. Synchronisatie en verzenden staan nog uit.</p>';
if ($error !== '') echo '<p role="alert">' . $h($error) . '</p>';
if (isset($_GET['opgeslagen'])) echo '<p>Wijziging opgeslagen.</p>';
echo '<section><h2>Mailbox toevoegen</h2><form method="post" action="/office-test/communicatie/mailboxen"><input type="hidden" name="csrf" value="' . $h($csrf) . '"><input type="hidden" name="action" value="register"><label>Naam<br><input name="display_name" required maxlength="255" autocomplete="off"></label><label>Zoho e-mailadres<br><input name="address" type="email" required maxlength="191" autocomplete="off"></label><button type="submit">Mailbox registreren</button></form></section>';
echo '<section><h2>Geregistreerde mailboxen</h2><table><thead><tr><th>Naam</th><th>Adres</th><th>Status</th><th>Actie</th></tr></thead><tbody>';
foreach ($rows as $row) {
    echo '<tr><td>' . $h($row['display_name']) . '</td><td>' . $h($row['mailbox_key']) . '</td><td>' . ((int) $row['enabled'] === 1 ? 'Actief' : 'Niet gekoppeld') . '</td><td>';
    if ((int) $row['enabled'] === 1) {
        echo '<form method="post" action="/office-test/communicatie/mailboxen"><input type="hidden" name="csrf" value="' . $h($csrf) . '"><input type="hidden" name="action" value="disable"><input type="hidden" name="id" value="' . (int) $row['id'] . '"><button type="submit">Uitschakelen</button></form>';
    }
    echo '</td></tr>';
}
echo '</tbody></table></section></main></body></html>';
