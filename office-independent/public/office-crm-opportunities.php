<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { header('Allow: GET'); http_response_code(405); exit; }
if (getenv('OFFICE_TEST_CRM_ENABLED') !== 'enabled' || getenv('OFFICE_CRM_AUTH_MODE') !== 'session_only' || getenv('OFFICE_CRM_OPPORTUNITIES_SCHEMA_VERIFIED') !== 'enabled') {
    http_response_code(503); exit('Verkoopkansen zijn nog niet beschikbaar.');
}
$host=$_SERVER['HTTP_HOST']??'';
$allowed=getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed) || $allowed==='' || !hash_equals($allowed,(string)$host) || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') { http_response_code(403); exit; }
try {
    foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
        require_once dirname(__DIR__).'/src/Intake/'.$file;
    }
    $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $provider=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
        new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
    $operator=$provider->currentOperator();
    if ($operator===null) { header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'),true,302); exit; }
    $active=$db->prepare('SELECT 1 FROM office_operator_accounts WHERE id=? AND active=1 LIMIT 1');
    $active->execute([$operator->operatorId]);
    if ($active->fetchColumn()===false) { http_response_code(403); exit('Geen CRM-toegang.'); }
    require_once dirname(__DIR__).'/src/Crm/OfficeCrmFunnel.php';
    require_once dirname(__DIR__).'/src/Crm/OfficeCrmFunnelViews.php';
    require_once dirname(__DIR__).'/src/Crm/OfficeCrmOpportunityRepository.php';
    $items=(new \Brebo\Office\Crm\OfficeCrmOpportunityRepository($db))->listForViews();
} catch (Throwable $e) { http_response_code(503); exit('Verkoopkansen tijdelijk niet beschikbaar.'); }
$view=\Brebo\Office\Crm\OfficeCrmFunnel::normalizeView((string)($_GET['view']??'kanban'));
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verkoopkansen · BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#19293a;margin:0}main{max-width:1400px;margin:32px auto;padding:20px}a{color:#175a91}table{width:100%;border-collapse:collapse;background:#fff}td,th{padding:12px;border-bottom:1px solid #ddd;text-align:left}.crm-view-switch{display:flex;gap:16px;margin:20px 0}</style></head><body><main><h1>Verkoopkansen</h1><p><a href="/office-test/crm/verkoopkansen/nieuw">Nieuwe verkoopkans</a></p><p>Van prospect naar offerte en opdracht. Opnamen zijn optioneel.</p>';
echo \Brebo\Office\Crm\OfficeCrmFunnelViews::render($items,$view,'/office-test/crm/verkoopkansen');
echo '</main></body></html>';
