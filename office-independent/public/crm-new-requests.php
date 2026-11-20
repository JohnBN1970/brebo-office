<?php
declare(strict_types=1);

/** Native read-only CRM requests view for the isolated Office test router. */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET'); http_response_code(405); exit;
}
$base = getenv('OFFICE_BASE_PATH') === '/office-test' ? '/office-test' : '/office';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (!in_array($path, [$base.'/crm/aanvragen', $base.'/crm/aanvragen/detail'], true)) {
    http_response_code(404); exit;
}
if (getenv('OFFICE_TEST_CRM_ENABLED') !== 'enabled'
    || getenv('OFFICE_CRM_AUTH_MODE') !== 'session_only'
    || getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled'
    || getenv('OFFICE_LOGIN_ENABLED') !== 'enabled'
    || getenv('OFFICE_CRM_LEADS_SCHEMA_VERIFIED') !== 'enabled') {
    http_response_code(503); exit('Aanvragen niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowed = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed) || $allowed === '' || !hash_equals($allowed, (string)$host)
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    http_response_code(403); exit;
}
try {
    foreach (['OfficeMount.php', 'WebsiteCrmOperatorContext.php',
              'WebsiteCrmOperatorSessionProviderInterface.php',
              'WebsiteOfficeCrmSessionRepository.php',
              'WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
        require_once dirname(__DIR__).'/src/Intake/'.$file;
    }
    $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
        getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
        new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time());
    $operator = $provider->currentOperator();
    if ($operator === null) {
        header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302); exit;
    }
    $active = $db->prepare('SELECT 1 FROM office_operator_accounts WHERE id=? AND active=1 LIMIT 1');
    $active->execute([$operator->operatorId]);
    if ($active->fetchColumn() === false) {
        http_response_code(403); exit('Geen CRM-toegang.');
    }
    if ($path === $base.'/crm/aanvragen/detail') {
        $rawId = $_GET['id'] ?? null;
        if (!is_string($rawId) || !ctype_digit($rawId) || strlen($rawId) > 18 || (int)$rawId < 1) {
            http_response_code(400); exit('Ongeldig aanvraagnummer.');
        }
        $stmt = $db->prepare("SELECT l.id,l.title,l.stage,l.status,l.description,l.source_detail,l.campaign,l.received_at,
            s.label AS source_name,r.display_name AS relation_name
            FROM office_crm_leads l JOIN office_crm_lead_sources s ON s.id=l.source_id
            LEFT JOIN office_crm_relations r ON r.id=l.relation_id WHERE l.id=? LIMIT 1");
        $stmt->execute([(int)$rawId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) { http_response_code(404); exit('Aanvraag niet gevonden.'); }
    } else {
        $stmt = $db->query("SELECT l.id,l.title,l.stage,l.received_at,l.source_detail,l.campaign,
            s.label AS source_name,r.display_name AS relation_name
            FROM office_crm_leads l JOIN office_crm_lead_sources s ON s.id=l.source_id
            LEFT JOIN office_crm_relations r ON r.id=l.relation_id
            WHERE l.status='active' AND l.converted_opportunity_id IS NULL
            ORDER BY l.received_at DESC,l.id DESC LIMIT 500");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    http_response_code(503); exit('Aanvragen tijdelijk niet beschikbaar.');
}
$h = static fn(mixed $value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Aanvragen · BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#19293a;margin:0}main{max-width:1200px;margin:32px auto;padding:20px}section{background:white;padding:24px;border-radius:12px;border:1px solid #dbe1e8}table{width:100%;border-collapse:collapse}th,td{padding:12px;text-align:left;border-bottom:1px solid #ddd}a{color:#175a91}</style></head><body><main><p><a href="'.$h($base).'/crm/verkoopkansen">Verkoopkansen</a> · <a href="'.$h($base).'/crm/relaties">Relaties</a></p>';
if (isset($item)) {
    echo '<h1>'.$h($item['title']).'</h1><section><p><strong>Bron:</strong> '.$h($item['source_name']).' '.$h($item['source_detail']).'</p><p><strong>Campagne:</strong> '.$h($item['campaign']).'</p><p><strong>Relatie:</strong> '.$h($item['relation_name']).'</p><p><strong>Fase:</strong> '.$h($item['stage']).'</p><p><strong>Status:</strong> '.$h($item['status']).'</p><p><strong>Omschrijving:</strong> '.$h($item['description']).'</p><p><strong>Ontvangen:</strong> '.$h($item['received_at']).'</p></section>';
} else {
    echo '<h1>Aanvragen · Leads</h1><p>Alleen actieve, nog niet omgezette leads. Bestaande klanten horen bij Verkoopkansen.</p><p><a href="'.$h($base).'/crm/aanvragen/nieuw">+ Nieuwe lead</a></p><section>';
    if ($items === []) {
        echo '<p>Geen actieve leads gevonden.</p>';
    } else {
        echo '<table><thead><tr><th>Aanvraag</th><th>Relatie</th><th>Bron</th><th>Campagne</th><th>Fase</th><th>Ontvangen</th></tr></thead><tbody>';
        foreach ($items as $row) {
            echo '<tr><td><a href="'.$h($base).'/crm/aanvragen/detail?id='.(int)$row['id'].'">'.$h($row['title']).'</a></td><td>'.$h($row['relation_name']).'</td><td>'.$h($row['source_name']).' '.$h($row['source_detail']).'</td><td>'.$h($row['campaign']).'</td><td>'.$h($row['stage']).'</td><td>'.$h($row['received_at']).'</td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '</section>';
}
echo '</main></body></html>';
