<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; style-src \'unsafe-inline\'; form-action \'self\'; base-uri \'none\'; frame-ancestors \'none\'');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { http_response_code(405); exit; }
if (getenv('OFFICE_BASE_PATH') !== '/office-test' || getenv('OFFICE_FINANCE_ENABLED') !== 'enabled' || getenv('OFFICE_FINANCE_SCHEMA_VERIFIED') !== 'enabled' || getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled') { http_response_code(503); exit; }
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!is_string($host) || !hash_equals((string)(getenv('OFFICE_PUBLIC_HOST') ?: ''), $host) || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') { http_response_code(403); exit; }
try {
  foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) require_once dirname(__DIR__) . '/src/Intake/' . $file;
  require_once dirname(__DIR__) . '/src/Finance/bootstrap.php';
  $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '', getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  $operator = (new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time()))->currentOperator();
  if ($operator === null) { header('Location: ' . \Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302); exit; }
  $access = new \Brebo\Office\Finance\OfficeFinanceAccess($db, $operator->operatorId);
  if (!$access->allows()) { http_response_code(403); exit; }
  $projects = $access->draftProjects();
  $e = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verkoopfactuurconcept · BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f3f6fa;color:#17283a;margin:0;padding:24px}main{max-width:850px;margin:auto;background:white;border-radius:12px;padding:24px}label{display:block;margin:16px 0}input,select{display:block;width:100%;padding:10px;box-sizing:border-box}a{color:#155c87}</style></head><body><main><p><a href="'.$e(\Brebo\Office\Intake\OfficeMount::route('finance/facturen')).'">Terug naar facturen</a></p><h1>Nieuw verkoopfactuurconcept</h1><p>Voorbereiding: opslaan is nog niet ingeschakeld. Er wordt geen factuurnummer uitgegeven en niets verzonden.</p><label>Klantnaam<input maxlength="160" disabled></label><label>Office-project<select disabled><option>Project kiezen</option>';
  foreach ($projects as $project) echo '<option>'.$e($project['title']).'</option>';
  echo '</select></label><h2>Factuurregels</h2><label>Omschrijving<input maxlength="240" disabled></label><label>Aantal<input disabled></label><label>Eenheidsprijs<input disabled></label><label>Btw-regime (verplicht)<select disabled><option>Wordt geladen uit Office Instellingen</option></select></label><p>Dit scherm wordt geactiveerd zodra de btw-regimes en database-opslag gecontroleerd zijn.</p></main></body></html>';
} catch (Throwable $e) {
  error_log('Office sales draft page unavailable: ' . get_class($e));
  http_response_code(503);
  echo 'Verkoopfactuurconcept tijdelijk niet beschikbaar.';
}
