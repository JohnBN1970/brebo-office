<?php
declare(strict_types=1);
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; img-src 'self'; connect-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
header('X-Robots-Tag: noindex, nofollow, noarchive');

function locked(int $status=403): never {
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Persoonlijke uitnodiging · BREBO OnSite</title><link rel="icon" href="icon.svg"><link rel="stylesheet" href="style.css"><script src="gate.js" defer></script></head><body><main class="wrap hero"><div class="hero-copy"><p class="eyebrow">BREBO ONSITE</p><h1>Jouw persoonlijke<br><em>uitnodiging.</em></h1><p class="intro" id="gate-message" role="status">Open de persoonlijke OnSite-link die je van BREBO hebt ontvangen. Alleen met een geldige uitnodiging kun je de installatiepagina openen.</p><p>Is je link verlopen of al gebruikt? Vraag je BREBO-contactpersoon om een nieuwe uitnodiging.</p><noscript>Schakel JavaScript in om je persoonlijke uitnodiging te controleren.</noscript></div></main></body></html>';
    exit;
}
function jsonResponse(bool $ok, int $status): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>$ok]);
    exit;
}

try {
    if (($_SERVER['HTTP_HOST'] ?? '') !== 'brebobv.nl' || ($_SERVER['HTTPS'] ?? '') !== 'on') locked();
    $domain = dirname(__DIR__, 2);
    $c = require $domain . '/office_calc_test/config/config.php';
    if (($c['OFFICE_BASE_PATH'] ?? '') !== '/office-test' || ($c['OFFICE_PUBLIC_HOST'] ?? '') !== 'brebobv.nl'
        || ($c['OFFICE_ONSITE_ACTIVATION_ENABLED'] ?? '') !== 'enabled' || ($c['OFFICE_ONSITE_SCHEMA_VERIFIED'] ?? '') !== 'enabled'
        || !preg_match('/(?:^|;)dbname=u213420663_office_test(?:;|$)/', $c['OFFICE_INTAKE_PDO_DSN'] ?? '')) locked(503);
    // This entire runtime, including the APK and PHP session files, is outside public_html.
    $runtime = $domain . '/onsite_landing/releases/' . trim(file_get_contents(__DIR__ . '/.release'));
    require $runtime . '/invitation.php';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_name('ONSITE_INSTALL');
    session_save_path($domain . '/onsite_landing/sessions');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/onsite/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    session_start();
    $db = new PDO($c['OFFICE_INTAKE_PDO_DSN'], $c['OFFICE_INTAKE_DB_USER'], $c['OFFICE_INTAKE_DB_PASSWORD'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $action = $_GET['action'] ?? '';
    if ($action === 'locked') locked();
    if ($action === 'unlock') {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
            || ($_SERVER['HTTP_ORIGIN'] ?? '') !== 'https://brebobv.nl'
            || strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') jsonResponse(false,403);
        // Bound both request length and per-session retries; never log the submitted token.
        $now = time();
        $attempts = array_values(array_filter($_SESSION['attempts'] ?? [], fn($t)=>$t>$now-60));
        if (count($attempts)>=12) jsonResponse(false,429);
        $attempts[]=$now;
        $_SESSION['attempts']=$attempts;
        unset($_SESSION['invitation_hash']);
        $body = file_get_contents('php://input', false, null, 0, 2049);
        $payload = strlen($body)<=2048 ? json_decode($body,true) : null;
        $token = is_array($payload) && is_string($payload['activation_token'] ?? null) ? $payload['activation_token'] : '';
        $hash = onsiteTokenHash($token);
        if (!$hash || !onsiteInvitation($db,$hash)) jsonResponse(false,403);
        session_regenerate_id(true);
        $_SESSION['invitation_hash']=$hash;
        session_write_close();
        jsonResponse(true,200);
    }
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET','HEAD'], true)) locked();
    $hash = $_SESSION['invitation_hash'] ?? '';
    $employee = onsiteInvitation($db,$hash);
    if (!$employee) {
        unset($_SESSION['invitation_hash']);
        session_write_close();
        locked();
    }
    session_write_close();
    if ($action === 'download') {
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="OnSite-Android-test.apk"');
        header('Content-Length: ' . filesize($runtime . '/OnSite-Android-test.apk'));
        if ($_SERVER['REQUEST_METHOD']==='GET') readfile($runtime . '/OnSite-Android-test.apk');
        exit;
    }
    if ($action !== '') locked();
    header('Content-Type: text/html; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD']==='HEAD') exit;
    $employeeName=htmlspecialchars((string)$employee['display_name'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
    require $runtime . '/page.php';
} catch (Throwable $e) {
    // Deliberately omit token, names, configuration and exception details from public/log output.
    locked(503);
}
