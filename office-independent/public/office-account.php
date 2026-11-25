<?php
declare(strict_types=1);
use Brebo\Office\Intake\OfficeMount;
use Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider;
use Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository;
use Brebo\Office\Intake\OfficeTheme;
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
if (($_SERVER['REQUEST_METHOD']??'') !== 'GET') {header('Allow: GET');http_response_code(405);exit;}
foreach (['OfficeMount','WebsiteCrmOperatorContext','WebsiteCrmOperatorSessionProviderInterface','WebsiteOfficeCrmSessionRepository','WebsiteOfficeCrmCookieSessionProvider','OfficeTheme'] as $file) require_once dirname(__DIR__).'/src/Intake/'.$file.'.php';
if (!OfficeMount::isTest() || getenv('OFFICE_LOGIN_ENABLED')!=='enabled') {http_response_code(404);exit;}
header('Content-Security-Policy: '.OfficeTheme::policy("default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'"));
try {
  $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  if ($db->query('SELECT DATABASE()')->fetchColumn()!=='brebo_office_test') throw new RuntimeException('Wrong database');
  $operator=(new WebsiteOfficeCrmCookieSessionProvider(new WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time()))->currentOperator();
  if ($operator===null) {header('Location: /office-test/inloggen',true,302);exit;}
  $stmt=$db->prepare('SELECT username FROM office_operator_accounts WHERE id=? AND active=1');
  $stmt->execute([$operator->operatorId]);
  $account=$stmt->fetch(PDO::FETCH_ASSOC);
  if (!$account) {http_response_code(403);exit('Account niet beschikbaar.');}
} catch (Throwable $e) {http_response_code(503);exit('Account tijdelijk niet beschikbaar.');}
$h=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
?><!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mijn account · BREBO Office</title><style>.account-layout{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:20px}.account-layout .account-card{padding:24px}.account-card h2{margin:0 0 20px}.account-card dl{display:grid;grid-template-columns:minmax(110px,1fr) 1fr;gap:14px;margin:0}.account-card dd{margin:0;font-weight:600}.account-card p{margin:18px 0 0}.account-intro{margin:0 0 24px}</style></head><body><main>
<h1>Mijn account</h1>
<p class="account-intro">Beheer je persoonlijke Office-account.</p><div class="account-layout"><section class="card account-card"><h2>Accountgegevens</h2><dl><dt>Gebruikersnaam</dt><dd><?= $h($account['username']) ?></dd><dt>Status</dt><dd>Actief</dd><dt>CRM bewerken</dt><dd><?= $operator->canEditCrm?'Toegestaan':'Niet toegestaan' ?></dd></dl></section><section class="card account-card"><h2>Organisatie en bevoegdheden</h2><p>Bevoegdheden worden beheerd door de beheerder. De functie in de testweergave wijzigt je werkelijke rechten niet.</p></section></div>
<p><a href="/office-test/">Terug naar mijn werkplek</a></p>
</main></body></html>
