<?php
declare(strict_types=1);

// Office-test browser handoff. The launch token is never placed in HTML,
// URLs, logs, browser storage or a cross-origin request.
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('Content-Type: text/html; charset=utf-8');
$deny = static function(int $status): never {
    http_response_code($status);
    exit('Calculatie is nog niet beschikbaar.');
};
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
    || getenv('OFFICE_CALC_ENABLED') !== 'enabled'
    || getenv('OFFICE_CALC_LAUNCH_ENABLED') !== 'enabled'
    || getenv('OFFICE_CALC_BROWSER_HANDOFF_ENABLED') !== 'enabled'
    || getenv('OFFICE_CALC_SAME_ORIGIN_PROXY_VERIFIED') !== 'enabled') {
    $deny(404);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') $deny(405);
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowedHost = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($host) || !is_string($allowedHost) || $allowedHost === ''
    || !hash_equals($host, $allowedHost)
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') $deny(403);
$projectId = filter_var($_GET['project_id'] ?? null, FILTER_VALIDATE_INT);
$calculationId = filter_var($_GET['calculation_id'] ?? null, FILTER_VALIDATE_INT);
if (!is_int($projectId) || $projectId <= 0 || !is_int($calculationId) || $calculationId <= 0) $deny(400);
require_once dirname(__DIR__) . '/src/Intake/OfficeMount.php';
$session = $_COOKIE[\Brebo\Office\Intake\OfficeMount::cookieName('session')] ?? null;
if (!is_string($session) || strlen($session) < 32) $deny(403);
// This GET page issues no grant. The POST endpoint independently authenticates,
// authorizes, checks CSRF, and issues the grant only on explicit user action.
$csrf = $_COOKIE['office_project_csrf'] ?? null;
if (!is_string($csrf) || preg_match('/^[a-f0-9]{64}$/D', $csrf) !== 1) {
    $csrf = bin2hex(random_bytes(32));
    if (!setcookie('office_project_csrf', $csrf, [
        'expires' => 0,
        'path' => '/office-test',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ])) {
        $deny(503);
    }
}
$nonce = base64_encode(random_bytes(18));
header("Content-Security-Policy: default-src 'none'; script-src 'nonce-{$nonce}'; style-src 'unsafe-inline'; connect-src 'self'; form-action 'none'; base-uri 'none'; frame-ancestors 'none'");
?>
<!doctype html>
<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Calculatie openen – BREBO Office</title>
<style>body{font:16px system-ui,sans-serif;max-width:560px;margin:8vh auto;padding:24px}button{padding:12px 20px;cursor:pointer}button:disabled{opacity:.6}#status{margin-top:16px}</style></head>
<body><h1>Calculatie openen</h1><p>Open de geselecteerde calculatie vanuit BREBO Office.</p>
<button type="button" id="open">Open calculatie</button><p id="status" role="status" aria-live="polite"></p>
<script nonce="<?= htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') ?>">
(() => {
  const button = document.getElementById('open');
  const status = document.getElementById('status');
  button.addEventListener('click', async () => {
    button.disabled = true;
    status.textContent = 'Calculatie wordt geopend…';
    try {
      const form = new URLSearchParams({
        project_id: <?= json_encode((string)$projectId) ?>,
        calculation_id: <?= json_encode((string)$calculationId) ?>,
        csrf: <?= json_encode($csrf) ?>
      });
      const launch = await fetch('/office-test/calc/start', {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: form.toString(), redirect: 'error'
      });
      if (!launch.ok) throw new Error('Office heeft de toegang geweigerd.');
      const data = await launch.json();
      if (data.ok !== true || typeof data.token !== 'string') throw new Error('Geen geldig starttoken.');
      const consumed = await fetch('/calc-app/api/launch/consume', {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({token: data.token}), redirect: 'error'
      });
      if (!consumed.ok) throw new Error('Calc heeft de toegang geweigerd.');
      const result = await consumed.json();
      if (result.status !== 'ok') throw new Error('Calc heeft de sessie niet bevestigd.');
      window.location.replace('/calc-app/');
    } catch (error) {
      status.textContent = error instanceof Error ? error.message : 'Calculatie openen is mislukt.';
      button.disabled = false;
    }
  });
})();
</script></body></html>
