<?php
declare(strict_types=1);
/** Read-only Office process handbook landing page. No role-based mandates inferred. */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { header('Allow: GET'); http_response_code(405); exit; }
if (getenv('OFFICE_BASE_PATH') !== '/office-test') { http_response_code(404); exit; }
$host=$_SERVER['HTTP_HOST'] ?? '';
if (!is_string($host) || !hash_equals((string)getenv('OFFICE_PUBLIC_HOST'),$host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') { http_response_code(403); exit; }
try {
  foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php',
    'WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php',
    'WebsiteOfficeCrmCookieSessionProvider.php'] as $file)
    require_once dirname(__DIR__).'/src/Intake/'.$file;
  $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',
    getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  $provider=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
  if ($provider->currentOperator()===null) {
    header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'),true,302); exit;
  }
} catch (Throwable $e) { http_response_code(503); exit('Proceshandboek tijdelijk niet beschikbaar.'); }
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Proceshandboek · BREBO Office</title><style>body{margin:0;background:#f4f6fa;color:#203044;font:16px system-ui,sans-serif}main{max-width:1050px;margin:35px auto;padding:0 22px}.panel{background:#fff;border:1px solid #dce2ed;border-radius:12px;padding:24px;margin:16px 0}a{color:#2459aa}small{color:#586a80}h1{margin-bottom:8px}</style></head><body><main><h1>BREBO Proceshandboek</h1><p>Leesportaal voor alle ingelogde Office-medewerkers.</p><section class="panel"><h2>Mijn processen</h2><p>De koppeling tussen uw formeel vastgestelde functie en de proceshoofdstukken wordt voorbereid. Er worden geen rollen of bevoegdheden afgeleid zonder gevalideerde bronkoppeling.</p></section><section class="panel"><h2>Volledig handboek</h2><p>Het normatieve Proceshandboek PH-100 versie 1.0 (7 augustus 2026) is de hoofdbron. De repository bevat momenteel documentatie en een beheerste ontwikkelappendix, maar nog geen geverifieerde, volledig leesbare PH-100-publicatie voor dit portaal.</p><p>De volledige tekst verschijnt hier pas nadat het beheerste bronbestand is aangesloten. De appendix wordt niet als vervanging gepresenteerd.</p></section><section class="panel"><h2>Documentstatus</h2><p><strong>PH-100:</strong> bronpublicatie nog aan te sluiten.</p><p><strong>Appendix A:</strong> aanvullingen op PH-100, geen zelfstandig handboek.</p><small>Dit portaal is uitsluitend voor lezen; wijziging van processen en mandaten verloopt via formeel BMS-beheer.</small></section></main></body></html>';
