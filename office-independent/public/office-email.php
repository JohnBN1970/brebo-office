<?php
declare(strict_types=1);

// Native Office mail landing page. No Drupal bootstrap, tables or APIs.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  header('Allow: GET');
  http_response_code(405);
  exit;
}
if (getenv('OFFICE_BASE_PATH') !== '/office-test') {
  http_response_code(404);
  exit;
}
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
$allowedHost = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowedHost) || $allowedHost === '' || !hash_equals($allowedHost, $host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(403);
  exit;
}
try {
  foreach ([
    'WebsiteCrmOperatorContext.php',
    'WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php',
    'WebsiteOfficeCrmCookieSessionProvider.php',
  ] as $file) require_once dirname(__DIR__) . '/src/Intake/' . $file;
  $db = new PDO(
    getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
    getenv('OFFICE_INTAKE_DB_USER') ?: '',
    getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
  );
  $operator = (new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time()
  ))->currentOperator();
  if ($operator === null) {
    header('Location: /office-test/inloggen', true, 302);
    exit;
  }
} catch (Throwable $e) {
  error_log('office_mail_home_unavailable: ' . $e::class);
  http_response_code(503);
  exit('E-mail tijdelijk niet beschikbaar.');
}
$reviewEnabled = getenv('OFFICE_PERSONAL_MAIL_REVIEW_IDENTITY_VERIFIED') === 'enabled';
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>E-mail - BREBO Office</title>
<style>
body{margin:0;background:#f4f6fa;color:#1b2c3c;font:15px system-ui,sans-serif}
main{max-width:1100px;margin:36px auto;padding:0 20px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}
article{background:#fff;border:1px solid #dce3eb;border-radius:12px;padding:22px}
h2{margin-top:0}a{color:#175a91}.muted{color:#586c80}
.status{font-size:13px;font-weight:650;color:#805a19}
</style></head><body><main>
<h1>E-mail</h1>
<p class="muted">Native Office e-mailomgeving. Drupal is geen onderdeel van deze pagina.</p>
<div class="grid">
<article><h2>Inbox</h2><p>Functionele mailboxen en berichten.</p><p class="status">Native synchronisatie nog niet aangesloten</p></article>
<article><h2>Verzonden</h2><p>Verzonden correspondentie vanuit Office.</p><p class="status">Nog niet aangesloten</p></article>
<article><h2>Persoonlijke e-mail</h2><p>Alleen expliciet geselecteerde berichten gaan naar Office.</p><p class="status">Native push nog niet aangesloten</p></article>
<article><h2>Mailcontrole</h2><p>Beoordeel voorgestelde dossierkoppelingen voordat ze worden toegepast.</p>
<?php if ($reviewEnabled): ?>
<p><a href="/office-test/mail/controle">Open mailcontrole</a></p>
<?php else: ?>
<p class="status">Wacht op geverifieerde Office-identiteit</p>
<?php endif; ?></article>
</div>
<p><a href="/office-test/">Terug naar Office</a></p>
</main></body></html>
