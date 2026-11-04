<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { header('Allow: GET'); http_response_code(405); exit; }
if (getenv('OFFICE_TEST_CRM_ENABLED') !== 'enabled'
    || getenv('OFFICE_CRM_AUTH_MODE') !== 'session_only'
    || getenv('OFFICE_CRM_RELATIONS_SCHEMA_VERIFIED') !== 'enabled') {
    http_response_code(503); exit('Relatiebeheer is nog niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowed = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed) || $allowed === '' || !hash_equals($allowed, (string)$host)
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') { http_response_code(403); exit; }
try {
    foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file)
        require_once dirname(__DIR__) . '/src/Intake/' . $file;
    $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '', getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
        new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time());
    $operator = $provider->currentOperator();
    if ($operator === null) { header('Location: ' . \Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302); exit; }
    $active = $db->prepare('SELECT 1 FROM office_operator_accounts WHERE id=? AND active=1 LIMIT 1');
    $active->execute([$operator->operatorId]);
    if ($active->fetchColumn() === false) { http_response_code(403); exit('Geen CRM-toegang.'); }
    $search = trim((string)($_GET['q'] ?? ''));
    if (strlen($search) > 100) { http_response_code(400); exit('Zoekterm te lang.'); }
    $role = (string)($_GET['role'] ?? '');
    if (!in_array($role, ['', 'customer','prospect','supplier'], true)) { http_response_code(400); exit('Ongeldige rol.'); }
    $sql = "SELECT r.id,r.display_name,r.relation_kind,r.city,r.email,r.status,
            GROUP_CONCAT(DISTINCT rr.role_code ORDER BY rr.role_code SEPARATOR ', ') AS roles,
            GROUP_CONCAT(DISTINCT manager.display_name ORDER BY manager.display_name SEPARATOR ', ') AS managers,
            COUNT(DISTINCT rc.contact_id) AS contact_count
            FROM office_crm_relations r
            LEFT JOIN office_crm_relation_roles rr ON rr.relation_id=r.id
            LEFT JOIN office_crm_relation_contacts rc ON rc.relation_id=r.id
            LEFT JOIN office_crm_relation_representations rep ON rep.principal_relation_id=r.id
              AND rep.representation_type='property_management'
              AND (rep.valid_from IS NULL OR rep.valid_from<=CURRENT_DATE())
              AND (rep.valid_until IS NULL OR rep.valid_until>=CURRENT_DATE())
            LEFT JOIN office_crm_relations manager ON manager.id=rep.representative_relation_id
            WHERE r.status='active'";
    $params = [];
    if ($search !== '') { $sql .= ' AND (r.display_name LIKE ? OR r.city LIKE ?)'; $params[]='%'.$search.'%'; $params[]='%'.$search.'%'; }
    if ($role !== '') { $sql .= ' AND EXISTS (SELECT 1 FROM office_crm_relation_roles filter_role WHERE filter_role.relation_id=r.id AND filter_role.role_code=?)'; $params[]=$role; }
    $sql .= ' GROUP BY r.id,r.display_name,r.relation_kind,r.city,r.email,r.status ORDER BY r.display_name,r.id LIMIT 250';
    $stmt=$db->prepare($sql); $stmt->execute($params); $relations=$stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { http_response_code(503); exit('Relatiebeheer tijdelijk niet beschikbaar.'); }
$h=static fn(mixed $v):string=>htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Relaties · BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#19293a;margin:0}main{max-width:1200px;margin:32px auto;padding:20px}section{background:#fff;border:1px solid #dbe1e8;border-radius:12px;padding:22px;overflow:auto}table{width:100%;border-collapse:collapse}td,th{padding:12px;border-bottom:1px solid #dbe1e8;text-align:left}form{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px}input,select,button{padding:9px;border:1px solid #b8c5d3;border-radius:6px}a{color:#175a91}</style></head><body><main><h1>Relaties</h1><p>Klanten, prospects, leveranciers en vertegenwoordiging door vastgoedbeheerders.</p><section><form method="get"><label>Zoeken <input name="q" value="'.$h($search).'"></label><label>Rol <select name="role">';
foreach ([''=>'Alle relaties','customer'=>'Klanten','prospect'=>'Prospects','supplier'=>'Leveranciers'] as $key=>$label)
    echo '<option value="'.$h($key).'"'.($role===$key?' selected':'').'>'.$h($label).'</option>';
echo '</select></label><button type="submit">Filter</button></form>';
if (getenv('OFFICE_TEST_CRM_WRITE_ENABLED')==='enabled') echo '<p><a href="/office-test/crm/relaties/nieuw">+ Nieuwe relatie</a></p>';
if ($relations === []) echo '<p>Geen relaties gevonden.</p>';
else { echo '<table><thead><tr><th>Relatie</th><th>Soort</th><th>Rollen</th><th>Vestigingsplaats</th><th>Contactpersonen</th><th>Vastgoedbeheerder</th></tr></thead><tbody>';
foreach ($relations as $item) echo '<tr><td>'.$h($item['display_name']).'</td><td>'.$h($item['relation_kind']).'</td><td>'.$h($item['roles']??'').'</td><td>'.$h($item['city']??'').'</td><td>'.$h($item['contact_count']).'</td><td>'.$h($item['managers']??'').'</td></tr>';
echo '</tbody></table>'; }
echo '</section></main></body></html>';
