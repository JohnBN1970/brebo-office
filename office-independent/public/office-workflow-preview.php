<?php
declare(strict_types=1);
/** Read-only workflow preview. No publication or execution endpoint. */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { header('Allow: GET'); http_response_code(405); exit; }
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
    || getenv('OFFICE_WORKFLOW_PREVIEW_ENABLED') !== 'enabled'
    || getenv('OFFICE_CRM_AUTH_MODE') !== 'session_only') {
    http_response_code(404); exit('Pagina niet beschikbaar.');
}
$host = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($host) || $host === '' || !hash_equals($host, (string)($_SERVER['HTTP_HOST'] ?? ''))
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    http_response_code(403); exit;
}
try {
    foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php',
        'WebsiteCrmOperatorSessionProviderInterface.php',
        'WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
        require_once dirname(__DIR__) . '/src/Intake/' . $file;
    }
    $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
        getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
        new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time());
    $operator = $provider->currentOperator();
    if ($operator === null) {
        header('Location: /office-test/inloggen', true, 302); exit;
    }
    $active = $db->prepare('SELECT 1 FROM office_operator_accounts WHERE id = ? AND active = 1 LIMIT 1');
    $active->execute([$operator->operatorId]);
    if ($active->fetchColumn() === false) { http_response_code(403); exit('Geen toegang.'); }
} catch (\Throwable $e) {
    http_response_code(503); exit('Workflowoverzicht tijdelijk niet beschikbaar.');
}
$nodes = ['Intake','CRM','Calculatie (optioneel)','Offertestudio (optioneel)','Acceptatie','Projecten','Uitvoering','FinanceCenter'];
$edges = [
 ['Registreren','Intake → CRM','crm.update_status','always'],
 ['Berekenen','CRM → Calculatie','crm.create_task','always'],
 ['Offerteconcept','Calculatie → Offertestudio','quotation.create_draft','calculation.exists'],
 ['Directe opdracht','CRM → Acceptatie','crm.update_status','acceptance.verified'],
 ['Opdracht starten','Acceptatie → Projecten','project.create','acceptance.verified'],
 ['Werkbegroting','Acceptatie → Projecten','budget.transfer_accepted_calculation','acceptance.verified'],
 ['Uitvoering','Projecten → Uitvoering','notification.create','always'],
 ['Financiële opvolging','Uitvoering → FinanceCenter','notification.create','always'],
];
function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?><!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Office · Workflows</title><style>
body{font:15px system-ui,sans-serif;background:#f5f7fb;color:#182b48;margin:0;padding:30px}
main{max-width:1080px;margin:auto}h1{margin-bottom:5px}p{color:#52617b}
.flow{display:flex;gap:8px;flex-wrap:wrap;margin:24px 0}
.node{background:#fff;border:1px solid #cbd6e8;border-radius:10px;padding:14px 16px;font-weight:600}
.arrow{align-self:center;color:#426da4}.panel{background:white;border:1px solid #dce4ef;border-radius:12px;padding:20px;overflow:auto}
table{border-collapse:collapse;width:100%}th,td{padding:12px;text-align:left;border-bottom:1px solid #e7ecf4}
th{color:#4a5970}small{color:#64748b}
</style></head><body><main><h1>Installatie / Workflows</h1>
<p>BREBO commerciële blauwdruk · alleen-lezen voorbeeld · geen actieve procesuitvoering</p>
<div class="flow"><?php foreach ($nodes as $i=>$node): ?>
<?php if ($i>0): ?><span class="arrow" aria-hidden="true">→</span><?php endif; ?>
<div class="node"><?=h($node)?></div><?php endforeach; ?></div>
<section class="panel"><h2>Acties op verbindingen</h2>
<table><thead><tr><th>Verbinding</th><th>Route</th><th>Actie</th><th>Voorwaarde</th></tr></thead><tbody>
<?php foreach ($edges as [$label,$route,$action,$condition]): ?>
<tr><td><?=h($label)?></td><td><?=h($route)?></td><td><code><?=h($action)?></code></td><td><?=h($condition)?></td></tr>
<?php endforeach; ?></tbody></table>
<p><small>Dit is een statische demonstratie, niet de gepubliceerde databaseconfiguratie. Wijzigen, slepen en publiceren volgen later.</small></p>
</section></main></body></html>
