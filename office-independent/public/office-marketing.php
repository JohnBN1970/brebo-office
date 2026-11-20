<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { header('Allow: GET'); http_response_code(405); exit; }
if (getenv('OFFICE_TEST_MARKETING_ENABLED') !== 'enabled'
    || getenv('OFFICE_CRM_AUTH_MODE') !== 'session_only'
    || getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled'
    || getenv('OFFICE_LOGIN_ENABLED') !== 'enabled') {
    http_response_code(503); exit('Marketing is niet beschikbaar.');
}
$host = (string)($_SERVER['HTTP_HOST'] ?? '');
$allowed = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed) || $allowed === '' || !hash_equals($allowed, $host)
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    http_response_code(403); exit;
}
try {
    foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php',
              'WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
        require_once dirname(__DIR__).'/src/Intake/'.$file;
    }
    $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '', getenv('OFFICE_INTAKE_DB_USER') ?: '',
        getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
        new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time());
    $operator = $provider->currentOperator();
    if ($operator === null) {
        header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302); exit;
    }
    $active = $db->prepare('SELECT 1 FROM office_operator_accounts WHERE id=? AND active=1 LIMIT 1');
    $active->execute([$operator->operatorId]);
    if (!$active->fetchColumn()) { http_response_code(403); exit('Geen toegang.'); }
} catch (Throwable $e) { http_response_code(503); exit('Marketing tijdelijk niet beschikbaar.'); }
$h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$base = \Brebo\Office\Intake\OfficeMount::route('crm');
?><!doctype html>
<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Marketing | BREBO Office</title>
<style>body{font:16px system-ui,sans-serif;max-width:960px;margin:40px auto;padding:0 20px;color:#20253a}a{color:#344c9d}section{border:1px solid #d9dce7;border-radius:12px;padding:20px;margin:16px 0}h1{margin-bottom:8px}</style></head>
<body><main><h1>BREBO Marketing</h1><p>Interne werkruimte voor externe marketing. CRM blijft eigenaar van relaties, leads en deals.</p>
<section><h2>Campagnes en acquisitie</h2><p>Campagnebeheer wordt in de volgende fase aangesloten.</p></section>
<section><h2>Websiteaanvragen en leads</h2><p>Registratie en opvolging verlopen via de bestaande CRM-werkstroom.</p><a href="<?= $h($base) ?>">Open CRM</a></section>
<section><h2>Marketingmateriaal</h2><p>Folders, publicaties en referentieprojecten worden hier beheerd na aansluiting van de documentmodule.</p></section>
<section><h2>Resultaten</h2><p>Conversie, offertewaarde en omzet worden pas getoond zodra bronkoppelingen zijn gecontroleerd.</p></section>
</main></body></html>
