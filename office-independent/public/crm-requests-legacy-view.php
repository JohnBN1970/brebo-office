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
    || getenv('OFFICE_CRM_OPPORTUNITIES_SCHEMA_VERIFIED') !== 'enabled') {
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
        $stmt = $db->prepare('SELECT o.id,o.title,o.stage,o.status,o.description,o.next_action,o.next_action_at,r.display_name AS relation_name
            FROM office_crm_opportunities o JOIN office_crm_relations r ON r.id=o.relation_id WHERE o.id=? LIMIT 1');
        $stmt->execute([(int)$rawId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) { http_response_code(404); exit('Aanvraag niet gevonden.'); }
    } else {
        require_once dirname(__DIR__).'/src/Crm/OfficeCrmOpportunityRepository.php';
        $items = (new \Brebo\Office\Crm\OfficeCrmOpportunityRepository($db))->listForViews();
    }
} catch (Throwable $e) {
    http_response_code(503); exit('Aanvragen tijdelijk niet beschikbaar.');
}
$h = static fn(mixed $value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Aanvragen · BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#19293a;margin:0}main{max-width:1200px;margin:32px auto;padding:20px}section{background:white;padding:24px;border-radius:12px;border:1px solid #dbe1e8}table{width:100%;border-collapse:collapse}th,td{padding:12px;text-align:left;border-bottom:1px solid #ddd}a{color:#175a91}</style></head><body><main><p><a href="'.$h($base).'/crm/verkoopkansen">Deals</a> · <a href="'.$h($base).'/crm/relaties">Relaties</a></p>';
if (isset($item)) {
    echo '<h1>'.$h($item['title']).'</h1><section><p><strong>Relatie:</strong> '.$h($item['relation_name']).'</p><p><strong>Fase:</strong> '.$h($item['stage']).'</p><p><strong>Status:</strong> '.$h($item['status']).'</p><p><strong>Omschrijving:</strong> '.$h($item['description']).'</p><p><strong>Volgende actie:</strong> '.$h($item['next_action']).'</p><p><strong>Opvolgdatum:</strong> '.$h($item['next_action_at']).'</p></section>';
} else {
    echo '<h1>Aanvragen</h1><p>Actuele deals en hun opvolging.</p><section>';
    if ($items === []) {
        echo '<p>Geen aanvragen gevonden.</p>';
    } else {
        echo '<table><thead><tr><th>Aanvraag</th><th>Relatie</th><th>Fase</th><th>Volgende actie</th><th>Opvolgdatum</th></tr></thead><tbody>';
        foreach ($items as $row) {
            echo '<tr><td><a href="'.$h($base).'/crm/aanvragen/detail?id='.(int)$row['id'].'">'.$h($row['name']).'</a></td><td>'.$h($row['relation_name']).'</td><td>'.$h($row['stage']).'</td><td>'.$h($row['next_action']).'</td><td>'.$h($row['due_date']).'</td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '</section>';
}
echo '</main></body></html>';
