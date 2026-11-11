<?php
declare(strict_types=1);

/**
 * Project-scoped address proposal review.
 * Session authorization, CSRF protection, and document context checks are mandatory.
 */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if (getenv('OFFICE_BASE_PATH') !== '/office-test') { http_response_code(404); exit; }
$host = (string)($_SERVER['HTTP_HOST'] ?? '');
$allowed = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed) || $allowed === '' || !hash_equals($allowed, $host)
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') { http_response_code(403); exit; }
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) { header('Allow: GET, POST'); http_response_code(405); exit; }
$projectId = filter_var($_GET['project_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
$documentId = filter_var($_GET['document_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if (!is_int($projectId) || !is_int($documentId)) { http_response_code(400); exit('Ongeldige documentcontext.'); }
try {
    foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
        require_once dirname(__DIR__) . '/src/Intake/' . $file;
    }
    require_once dirname(__DIR__) . '/src/Project/OfficeNativeProjectAccess.php';
    require_once dirname(__DIR__) . '/src/Documents/OfficePrivateFileStorage.php';
    require_once dirname(__DIR__) . '/src/Documents/OfficeDocumentRegistry.php';
    require_once dirname(__DIR__) . '/src/Documents/OfficeProjectDocumentService.php';
    $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '', getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
        new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time()
    );
    $operator = $provider->currentOperator();
    if ($operator === null) { header('Location: /office-test/inloggen', true, 302); exit; }
    $operatorId = filter_var($operator->operatorId, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if (!is_int($operatorId)) { http_response_code(403); exit; }
    $access = new \Brebo\Office\Project\OfficeNativeProjectAccess($db);
    $service = new \Brebo\Office\Documents\OfficeProjectDocumentService(
        $db,
        new \Brebo\Office\Documents\OfficePrivateFileStorage(getenv('OFFICE_DOCUMENT_PRIVATE_ROOT') ?: ''),
        new \Brebo\Office\Documents\OfficeDocumentRegistry($db),
        $access
    );
    $service->authorizedDownload($operatorId, $projectId, $documentId);
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (!session_start(['use_strict_mode'=>1,'cookie_httponly'=>1,'cookie_secure'=>1,'cookie_samesite'=>'Strict'])) {
            throw new RuntimeException('session_unavailable');
        }
    }
    if (!isset($_SESSION['office_address_csrf'])) $_SESSION['office_address_csrf'] = bin2hex(random_bytes(32));
    $notice = '';
    if ($method === 'POST') {
        if (!hash_equals((string)$_SESSION['office_address_csrf'], (string)($_POST['csrf'] ?? ''))) {
            http_response_code(403); exit('Ongeldige beveiligingscode.');
        }
        $proposalId = filter_var($_POST['proposal_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $decision = (string)($_POST['decision'] ?? '');
        if (!is_int($proposalId) || !in_array($decision, ['accepted','rejected'], true)) {
            http_response_code(400); exit('Ongeldige beslissing.');
        }
        $changed = $service->reviewProjectAddressProposal($operatorId, $projectId, $documentId, $proposalId, $decision);
        $notice = $changed ? 'Beoordeling opgeslagen.' : 'Voorstel niet aangepast. Controleer de status en gebouwmatch.';
    }
    $rows = $service->projectAddressProposals($operatorId, $projectId, $documentId);
} catch (Throwable $error) {
    http_response_code(503);
    exit('Documentvoorstellen tijdelijk niet beschikbaar.');
}
function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Adresvoorstellen – BREBO Office</title><style>
body{margin:0;background:#f4f6fa;color:#1b2c3c;font:15px system-ui,sans-serif}main{max-width:1100px;margin:32px auto;padding:0 20px}
table{width:100%;border-collapse:collapse;background:white}th,td{text-align:left;border-bottom:1px solid #dce3eb;padding:12px;vertical-align:top}
section{overflow:auto;border-radius:12px;border:1px solid #dce3eb}button{padding:7px 12px;margin:2px;border:0;border-radius:6px;background:#215d86;color:white;cursor:pointer}
button.reject{background:#6d477b}.muted{color:#5d6b79}.notice{font-weight:600}form{display:inline-block}
</style></head><body><main><h1>Adresvoorstellen uit document</h1>
<p class="muted">Alleen voorstellen. Er worden geen gebouwen, adressen of bewoners automatisch gewijzigd.</p>
<?php if ($notice !== ''): ?><p class="notice"><?=h($notice)?></p><?php endif; ?>
<section><table><thead><tr><th>Bron</th><th>Adres</th><th>Bewoner (optioneel)</th><th>Gebouwmatch</th><th>Status en beoordeling</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?>
<tr><td><?=h((string)$row['source_location'])?></td>
<td><?=h((string)$row['address_line'])?>, <?=h((string)$row['postal_code'])?> <?=h((string)$row['city'])?></td>
<td><?=h((string)($row['resident_name'] ?? ''))?><div class="muted"><?=h((string)($row['resident_email'] ?? ''))?></div></td>
<td><?=h((string)$row['match_status'])?><?= $row['suggested_building_id'] !== null ? ' · #'.(int)$row['suggested_building_id'] : '' ?></td>
<td><?=h((string)$row['review_status'])?>
<?php if ($row['review_status'] === 'pending'): ?>
<?php foreach (['accepted'=>'Accepteren','rejected'=>'Afwijzen'] as $decision=>$label): ?>
<?php if ($decision === 'accepted' && ($row['match_status'] !== 'exact_candidate' || $row['suggested_building_id'] === null)) continue; ?>
<form method="post"><input type="hidden" name="csrf" value="<?=h((string)$_SESSION['office_address_csrf'])?>">
<input type="hidden" name="proposal_id" value="<?=(int)$row['id']?>"><input type="hidden" name="decision" value="<?=h($decision)?>">
<button type="submit" <?= $decision === 'rejected' ? 'class="reject"' : '' ?>><?=h($label)?></button></form>
<?php endforeach; ?><?php endif; ?></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5">Geen adresvoorstellen beschikbaar.</td></tr><?php endif; ?>
</tbody></table></section></main></body></html>
