<?php
declare(strict_types=1);

// Office-test only: secure CRM opportunity -> projectless Calc draft.
// GET shows a confirmation; only same-origin, CSRF-protected POST creates a draft.
// The Calc line store stays canonical in calculations/calculation_versions/calculation_lines.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

$deny = static function (int $code, string $reason='UNSPECIFIED'): never {
    // Fixed non-sensitive failure codes only; never expose session, SQL or secrets.
    if (!preg_match('/^[A-Z][A-Z0-9_]{1,30}$/D', $reason)) $reason='UNSPECIFIED';
    header('X-Brebo-Calc-Diagnostic: '.$reason);
    http_response_code($code);
    exit('CRM-calculatie niet beschikbaar (code '.$reason.').');
};

if (getenv('OFFICE_BASE_PATH') !== '/office-test'
    || getenv('OFFICE_TEST_CRM_ENABLED') !== 'enabled'
    || getenv('OFFICE_CRM_AUTH_MODE') !== 'session_only'
    || getenv('OFFICE_CALC_CRM_DRAFT_LAUNCH_ENABLED') !== 'enabled'
    || getenv('OFFICE_CALC_CRM_SCHEMA_VERIFIED') !== 'enabled') $deny(404,'FLAGS');

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    $deny(405,'METHOD');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowed = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($host) || !is_string($allowed) || $allowed === ''
    || !hash_equals($allowed, $host)
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') $deny(403,'ACCESS');

$opportunity = filter_var(
    $method === 'POST' ? ($_POST['opportunity_id'] ?? null) : ($_GET['opportunity_id'] ?? null),
    FILTER_VALIDATE_INT
);
if (!is_int($opportunity) || $opportunity < 1) $deny(400,'OPPORTUNITY_ID');

if ($method === 'POST') {
    if (!hash_equals('https://' . $host, (string)($_SERVER['HTTP_ORIGIN'] ?? ''))) $deny(403,'ORIGIN');
    $size = $_SERVER['CONTENT_LENGTH'] ?? '';
    if (!is_string($size) || !ctype_digit($size) || (int)$size > 2048) $deny(413,'BODY_SIZE');
    if (!preg_match('/^application\/x-www-form-urlencoded(?:\s*;\s*charset=UTF-8)?$/iD',
        (string)($_SERVER['CONTENT_TYPE'] ?? ''))) $deny(415,'BODY_TYPE');
}

try {
    require_once dirname(__DIR__) . '/src/Intake/OfficeMount.php';
    require_once dirname(__DIR__) . '/src/Intake/WebsiteCrmOperatorContext.php';
    require_once dirname(__DIR__) . '/src/Intake/WebsiteOfficeCrmSessionRepository.php';
    $token = $_COOKIE[\Brebo\Office\Intake\OfficeMount::cookieName('session')] ?? null;
    if (!is_string($token)) $deny(403,'SESSION_COOKIE');
    $db = new PDO(
        getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
        getenv('OFFICE_INTAKE_DB_USER') ?: '',
        getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $operator = (new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db))
        ->findActive($token, time());
    if ($operator === null || !$operator->canEditCrm
        || !ctype_digit($operator->operatorId)) $deny(403,'SESSION_RIGHTS');
    $actor = (int)$operator->operatorId;
    if ($actor < 1) $deny(403,'ACTOR_ID');

    $csrf = $_COOKIE['office_project_csrf'] ?? null;
    if (!is_string($csrf) || !preg_match('/^[a-f0-9]{64}$/D', $csrf)) {
        if ($method === 'POST') $deny(403,'CSRF_COOKIE');
        $csrf = bin2hex(random_bytes(32));
        if (!setcookie('office_project_csrf', $csrf, [
            'path' => '/office-test', 'secure' => true,
            'httponly' => true, 'samesite' => 'Strict'
        ])) $deny(503,'SERVER');
    }
    if ($method === 'POST') {
        $submitted = $_POST['csrf'] ?? null;
        if (!is_string($submitted) || !hash_equals($csrf, $submitted)) $deny(403,'CSRF_MISMATCH');
        $db->beginTransaction();
    }

    $q = $db->prepare(
        'SELECT id, revision, stage, status, title FROM office_crm_opportunities WHERE id=?' .
        ($method === 'POST' ? ' FOR UPDATE' : '')
    );
    $q->execute([$opportunity]);
    $record = $q->fetch(PDO::FETCH_ASSOC);
    if (!is_array($record) || $record['status'] !== 'open'
        || !in_array($record['stage'], ['prospect', 'opportunity', 'proposal', 'negotiation'], true)
        || !ctype_digit((string)$record['revision'])) {
        if ($db->inTransaction()) $db->rollBack();
        $deny(403,'OPPORTUNITY_STATE');
    }

    $q = $db->prepare(
        'SELECT id, crm_revision, created_by, project_id FROM office_calc_crm_drafts WHERE opportunity_id=?'
    );
    $q->execute([$opportunity]);
    $existing = $q->fetch(PDO::FETCH_ASSOC);
    if (is_array($existing) && (
        (int)$existing['created_by'] !== $actor
        || (int)$existing['crm_revision'] !== (int)$record['revision']
        || $existing['project_id'] !== null
    )) {
        if ($db->inTransaction()) $db->rollBack();
        $deny(403,'DRAFT_OWNER_OR_REVISION');
    }

    if ($method === 'POST') {
        if (is_array($existing)) {
            $draftId = (int)$existing['id'];
        } else {
            $insert = $db->prepare(
                'INSERT INTO office_calc_crm_drafts (opportunity_id,crm_revision,created_by,project_id) VALUES (?,?,?,NULL)'
            );
            $insert->execute([$opportunity, (int)$record['revision'], $actor]);
            $draftId = (int)$db->lastInsertId();
        }
        $db->commit();
        header('Location: /office-test/calculatie?draft_id=' . $draftId, true, 303);
        exit;
    }
} catch (Throwable $error) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) $db->rollBack();
    $deny(503, $error instanceof PDOException ? 'DATABASE' : 'RUNTIME');
}

$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
header('Content-Type: text/html; charset=utf-8');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
echo '<!doctype html><html lang="nl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
echo '<title>Calculatie starten · BREBO Office</title>';
echo '<style>body{font:16px system-ui,sans-serif;background:#f5f7fa;color:#213349;max-width:650px;margin:9vh auto;padding:24px}main{background:#fff;border-radius:14px;padding:28px}button{background:#36469d;border:0;color:#fff;border-radius:8px;padding:12px 18px;font-weight:700}a{color:#36469d}</style>';
echo '<main><small>BREBO OFFICE / CRM / CALCULATIE</small><h1>Calculatie starten</h1><p>Verkoopkans: <strong>' .
    $h((string)$record['title']) . '</strong></p><p>Er wordt nog geen project aangemaakt. De calculatie blijft gekoppeld aan deze verkoopkans.</p>';
echo '<form method="post" action="/office-test/calculatie/start"><input type="hidden" name="opportunity_id" value="' .
    $opportunity . '"><input type="hidden" name="csrf" value="' . $h($csrf) .
    '"><button type="submit">' . (is_array($existing) ? 'Bestaande calculatie openen' : 'Conceptcalculatie aanmaken') .
    '</button></form><p><a href="/office-test/crm/verkoopkansen">Terug naar verkoopkansen</a></p></main></html>';
