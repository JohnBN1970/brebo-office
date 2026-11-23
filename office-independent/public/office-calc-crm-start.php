<?php
declare(strict_types=1);

// Office-test only: a Deal may start a native Calc draft before a project exists.
// The draft ID is the existing office_calc_crm_drafts ID consumed by the native
// workbench; lines remain in calculations/calculation_versions/calculation_lines.
header('Cache-Control: no-store, private');
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$deny = static function(int $status): never {
    http_response_code($status);
    exit('Deal-calculatie is niet beschikbaar.');
};
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
    || getenv('OFFICE_TEST_CRM_ENABLED') !== 'enabled'
    || getenv('OFFICE_CRM_AUTH_MODE') !== 'session_only'
    || getenv('OFFICE_CRM_OPPORTUNITIES_SCHEMA_VERIFIED') !== 'enabled'
    || getenv('OFFICE_CALC_CRM_DRAFT_LAUNCH_ENABLED') !== 'enabled'
    || getenv('OFFICE_CALC_CRM_SCHEMA_VERIFIED') !== 'enabled') $deny(404);
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET','POST'], true)) {
    header('Allow: GET, POST');
    $deny(405);
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowed = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($host) || !is_string($allowed) || $allowed === ''
    || !hash_equals($allowed, $host)
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') $deny(403);
$rawId = $method === 'POST' ? ($_POST['opportunity_id'] ?? null) : ($_GET['opportunity_id'] ?? null);
if (!is_string($rawId) || !preg_match('/^[1-9][0-9]*$/D', $rawId)
    || filter_var($rawId, FILTER_VALIDATE_INT) === false) $deny(400);
$opportunityId = (int)$rawId;
if ($method === 'POST') {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    $fetchSite = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? null;
    $referer = $_SERVER['HTTP_REFERER'] ?? null;
    $expected = 'https://' . $host;
    if (is_string($origin)) {
        if (!hash_equals($expected, $origin)) $deny(403);
    } elseif ($fetchSite === 'same-origin') {
        // Same-origin browser request.
    } elseif (is_string($referer) && str_starts_with($referer, $expected . '/')) {
        // Exact HTTPS origin with a path separator.
    } else {
        $deny(403);
    }
    $size = $_SERVER['CONTENT_LENGTH'] ?? null;
    if (!is_string($size) || !ctype_digit($size) || (int)$size < 1 || (int)$size > 2048) $deny(413);
    if (!preg_match('/^application\/x-www-form-urlencoded(?:\s*;\s*charset=UTF-8)?$/iD', (string)($_SERVER['CONTENT_TYPE'] ?? ''))) $deny(415);
    require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcOpportunityLaunchInput.php';
    $payload = \Brebo\Office\Calculation\OfficeCalcOpportunityLaunchInput::parse($_POST);
    if ($payload === null || $payload['opportunity_id'] !== $opportunityId) $deny(400);
}
try {
    require_once dirname(__DIR__) . '/src/Intake/OfficeMount.php';
    require_once dirname(__DIR__) . '/src/Intake/WebsiteCrmOperatorContext.php';
    require_once dirname(__DIR__) . '/src/Intake/WebsiteOfficeCrmSessionRepository.php';
    $session = $_COOKIE[\Brebo\Office\Intake\OfficeMount::cookieName('session')] ?? null;
    if (!is_string($session) || $session === '') $deny(403);
    $db = new PDO(
        getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
        getenv('OFFICE_INTAKE_DB_USER') ?: '',
        getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $operator = (new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db))->findActive($session, time());
    if ($operator === null || !$operator->canEditCrm || !ctype_digit($operator->operatorId)) $deny(403);
    $actor = (int)$operator->operatorId;
    if ($actor < 1) $deny(403);
    $active = $db->prepare('SELECT 1 FROM office_operator_accounts WHERE id=? AND active=1 AND can_edit_crm=1 LIMIT 1');
    $active->execute([$actor]);
    if ($active->fetchColumn() === false) $deny(403);

    $csrf = $_COOKIE['office_project_csrf'] ?? null;
    if (!is_string($csrf) || !preg_match('/^[a-f0-9]{64}$/D', $csrf)) {
        if ($method === 'POST') $deny(403);
        $csrf = bin2hex(random_bytes(32));
        if (!setcookie('office_project_csrf', $csrf, [
            'path'=>'/office-test', 'secure'=>true, 'httponly'=>true, 'samesite'=>'Strict'
        ])) $deny(503);
    }
    if ($method === 'POST' && (!is_string($_POST['csrf'] ?? null) || !hash_equals($csrf, $_POST['csrf']))) $deny(403);

    if ($method === 'POST') $db->beginTransaction();
    $sql = 'SELECT id,title,revision,stage,status FROM office_crm_opportunities WHERE id=?'
        . ($method === 'POST' ? ' FOR UPDATE' : '');
    $q = $db->prepare($sql);
    $q->execute([$opportunityId]);
    $deal = $q->fetch(PDO::FETCH_ASSOC);
    if (!is_array($deal)
        || $deal['status'] !== 'open'
        || !in_array($deal['stage'], ['prospect','opportunity','proposal','negotiation'], true)
        || !ctype_digit((string)$deal['revision'])
        || (int)$deal['revision'] < 1
        || ($method === 'POST' && (int)$deal['revision'] !== $payload['revision'])) {
        if ($db->inTransaction()) $db->rollBack();
        $deny(409);
    }
    $q = $db->prepare('SELECT id,crm_revision,created_by,project_id FROM office_calc_crm_drafts WHERE opportunity_id=?');
    $q->execute([$opportunityId]);
    $draft = $q->fetch(PDO::FETCH_ASSOC);
    if (is_array($draft) && ((int)$draft['created_by'] !== $actor
        || (int)$draft['crm_revision'] !== (int)$deal['revision']
        || $draft['project_id'] !== null)) {
        if ($db->inTransaction()) $db->rollBack();
        $deny(403);
    }
    if ($method === 'POST') {
        if (is_array($draft)) {
            $draftId = (int)$draft['id'];
        } else {
            // The unique opportunity key must exist on the verified test schema.
            // Locking the Deal row serializes creation; a conflicting INSERT fails closed.
            // One canonical Office calculation identity for both Deal and project contexts.
            // Deal lock and this transaction prevent duplicate project-free creation.
            $identity = $db->prepare('INSERT INTO office_calc_calculations (project_id) VALUES (NULL)');
            $identity->execute();
            $calculationId = (int)$db->lastInsertId();
            if ($calculationId < 1) throw new RuntimeException('Office calculation identity unavailable');
            $insert = $db->prepare('INSERT INTO office_calc_crm_drafts (opportunity_id,crm_revision,created_by,project_id,calculation_id) VALUES (?,?,?,NULL,?)');
            $insert->execute([$opportunityId,(int)$deal['revision'],$actor,$calculationId]);
            $draftId = (int)$db->lastInsertId();
        }
        if ($draftId < 1) throw new RuntimeException('Draft identity unavailable');
        $db->commit();
        header('Location: /office-test/calculatie?draft_id='.$draftId, true, 303);
        exit;
    }
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) $db->rollBack();
    $deny(503);
}
$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
echo '<!doctype html><html lang="nl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
echo '<title>Calculatie starten · BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#24374b;max-width:660px;margin:8vh auto;padding:24px}main{background:white;border-radius:12px;padding:28px}button{background:#33529a;color:white;border:0;border-radius:6px;padding:12px 18px}a{color:#33529a}</style>';
echo '<main><small>BREBO OFFICE / DEALS / CALCULATIE</small><h1>Calculatie starten</h1>';
echo '<p>Deal: <strong>'.$h((string)$deal['title']).'</strong></p><p>Er wordt geen project aangemaakt. De calculatie blijft gekoppeld aan deze deal.</p>';
echo '<form method="post" action="/office-test/calculatie/start"><input type="hidden" name="opportunity_id" value="'.$opportunityId.'"><input type="hidden" name="revision" value="'.(int)$deal['revision'].'"><input type="hidden" name="csrf" value="'.$h($csrf).'"><button type="submit">'.(is_array($draft)?'Bestaande calculatie openen':'Conceptcalculatie aanmaken').'</button></form>';
echo '<p><a href="/office-test/crm/verkoopkansen">Terug naar Deals</a></p></main>';
