<?php
declare(strict_types=1);

// Test-only CRM workspace. No CRM write endpoints are enabled by this page.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  header('Allow: GET'); http_response_code(405); exit;
}
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
  || getenv('OFFICE_TEST_CRM_ENABLED') !== 'enabled'
  || getenv('OFFICE_CRM_AUTH_MODE') !== 'session_only'
  || getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled'
  || getenv('OFFICE_LOGIN_ENABLED') !== 'enabled') {
  http_response_code(503); exit('CRM is nog niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowedHost = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($host) || !is_string($allowedHost) || $allowedHost === ''
  || !hash_equals($allowedHost, $host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(403); exit;
}
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$labels = [
  '/office-test/crm/bedrijven' => 'Bedrijven',
  '/office-test/crm/contactpersonen' => 'Contactpersonen',
  '/office-test/crm/kansen' => 'Kansen',
  '/office-test/crm/prospects' => 'Prospects',
];
if (!is_string($path) || !isset($labels[$path])) {
  http_response_code(404); exit;
}
try {
  foreach ([
    'OfficeMount.php',
    'WebsiteCrmOperatorContext.php',
    'WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php',
    'WebsiteOfficeCrmCookieSessionProvider.php',
    'WebsiteOfficeCrmAccessPolicy.php',
  ] as $file) {
    require_once dirname(__DIR__) . '/src/Intake/' . $file;
  }
  $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
    getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
  $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time());
  (new \Brebo\Office\Intake\WebsiteOfficeCrmAccessPolicy($provider))->requireReader();
} catch (DomainException) {
  http_response_code(403); exit;
} catch (Throwable) {
  http_response_code(503); exit('CRM tijdelijk niet beschikbaar.');
}
require_once dirname(__DIR__) . '/src/Ui/OfficeModuleTabs.php';
$tabs = [];
foreach ($labels as $url => $label) {
  $tabs[] = ['key' => $url, 'label' => $label, 'url' => $url, 'enabled' => true];
}
$tabs[] = ['key' => '/office-test/crm/aanvragen', 'label' => 'Aanvragen', 'url' => '/office-test/crm/aanvragen', 'enabled' => true];
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$title = $labels[$path];
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'
  . $escape($title) . ' · BREBO CRM</title><style>'
  . ':root{font-family:system-ui,sans-serif;color:#17283a;background:#f3f6fa}*{box-sizing:border-box}body{margin:0}.shell{display:grid;grid-template-columns:250px minmax(0,1fr);min-height:100vh}.sidebar{background:#14263b;color:#eaf0f7;padding:28px 18px}.sidebar a{display:block;color:#eaf0f7;text-decoration:none;padding:12px;border-radius:8px}.sidebar a.current{background:#29445f}.main{min-width:0;padding:32px;max-width:1200px;width:100%}.panel{background:#fff;border:1px solid #dce4ed;border-radius:12px;padding:24px}.muted{color:#617185}.test{background:#fff0b3;padding:10px;font-weight:700}.sidebar strong{display:block;margin-bottom:24px}.sidebar a:focus-visible{outline:3px solid #e9a72c}@media(max-width:760px){.shell{display:block}.sidebar{padding:14px}.main{padding:18px}}'
  . \Brebo\Office\Ui\OfficeModuleTabs::css()
  . '</style></head><body><div class="shell"><aside class="sidebar"><strong>BREBO Office</strong><nav aria-label="Hoofdnavigatie"><a href="/office-test">Mijn werkplek</a><a class="current" aria-current="page" href="/office-test/crm/bedrijven">Relaties &amp; CRM</a><a href="/office-test/projecten">Projecten</a></nav></aside><main class="main"><div class="test">TESTOMGEVING</div><h1>Relaties &amp; CRM</h1>'
  . \Brebo\Office\Ui\OfficeModuleTabs::render($tabs, $path)
  . '<section class="panel"><h2>' . $escape($title) . '</h2><p class="muted">Nog geen ' . $escape(mb_strtolower($title)) . ' geregistreerd.</p><p>De navigatie is beschikbaar. Registreren, bewerken en koppelen worden aangesloten zodra het Office-native CRM-datamodel en de schrijfrechten zijn gecontroleerd.</p></section></main></div></body></html>';
