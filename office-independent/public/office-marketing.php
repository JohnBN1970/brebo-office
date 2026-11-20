<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) { header('Allow: GET, POST'); http_response_code(405); exit; }
if (getenv('OFFICE_TEST_MARKETING_ENABLED') !== 'enabled'
    || getenv('OFFICE_CRM_AUTH_MODE') !== 'session_only'
    || getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled'
    || getenv('OFFICE_LOGIN_ENABLED') !== 'enabled') {
    http_response_code(503); exit('Marketing is niet beschikbaar.');
}
$host = (string)($_SERVER['HTTP_HOST'] ?? '');
$allowed = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed) || $allowed === '' || !hash_equals($allowed, $host)
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    http_response_code(403); exit;
}
try {
    foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php',
              'WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
        require_once dirname(__DIR__).'/src/Intake/'.$file;
    }
    $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '', getenv('OFFICE_INTAKE_DB_USER') ?: '',
        getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
        new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time());
    $operator = $provider->currentOperator();
    if ($operator === null) {
        header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302); exit;
    }
    $active = $db->prepare('SELECT 1 FROM office_operator_accounts WHERE id=? AND active=1 LIMIT 1');
    $active->execute([$operator->operatorId]);
    if (!$active->fetchColumn()) { http_response_code(403); exit('Geen toegang.'); }
} catch (Throwable $e) { http_response_code(503); exit('Marketing tijdelijk niet beschikbaar.'); }
require_once dirname(__DIR__).'/src/Crm/OfficeMarketingCampaignLinkRepository.php';
$repo = new \Brebo\Office\Crm\OfficeMarketingCampaignLinkRepository($db);
$ready = getenv('OFFICE_TEST_MARKETING_CRM_LINKS_SCHEMA_VERIFIED') === 'enabled';
$write = $ready && getenv('OFFICE_TEST_MARKETING_CRM_LINKS_WRITE_ENABLED') === 'enabled'
    && getenv('OFFICE_CRM_WRITE_ENABLED') === 'enabled';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('BREBO_MARKETING_FORM');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/office-test/marketing','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    if (!session_start()) { http_response_code(503); exit('Formuliersessie niet beschikbaar.'); }
}
if (!isset($_SESSION['marketing_csrf'])) $_SESSION['marketing_csrf'] = bin2hex(random_bytes(32));
$error = ''; $notice = '';
if ($method === 'POST') {
    if (!$write) { http_response_code(403); exit('Marketing schrijven niet vrijgegeven.'); }
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['marketing_csrf'], $_POST['csrf'])) {
        http_response_code(403); exit('Ongeldige formulierbeveiliging.');
    }
    try {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'campaign') {
            $repo->createCampaign((string)($_POST['code'] ?? ''), (string)($_POST['name'] ?? ''), null);
        } elseif ($action === 'link') {
            $repo->link((int)($_POST['campaign_id'] ?? 0), (string)($_POST['target_type'] ?? ''),
                (int)($_POST['target_id'] ?? 0), null);
        } else { throw new InvalidArgumentException('Ongeldige actie.'); }
        $_SESSION['marketing_csrf'] = bin2hex(random_bytes(32));
        header('Location: /office-test/marketing?saved=1', true, 303); exit;
    } catch (Throwable $e) { $error = 'Opslaan mislukt. Controleer de campagne, het CRM-record en eventuele bestaande koppeling.'; }
}
$campaigns = [];
if ($ready) {
    try { $campaigns = $db->query('SELECT id,code,name FROM office_marketing_campaigns ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable $e) { $ready = false; $write = false; }
}
$h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$base = \Brebo\Office\Intake\OfficeMount::route('crm');
?><!doctype html>
<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Marketing | BREBO Office</title>
<style>body{font:16px system-ui,sans-serif;max-width:960px;margin:40px auto;padding:0 20px;color:#20253a}a{color:#344c9d}section{border:1px solid #d9dce7;border-radius:12px;padding:20px;margin:16px 0}h1{margin-bottom:8px}</style></head>
<body><main><h1>BREBO Marketing</h1><p>Interne werkruimte voor externe marketing. CRM blijft eigenaar van relaties, leads en verkoopkansen.</p>
<section><h2>Campagnes en acquisitie</h2>
<?php if (!$ready): ?><p>CRM-campagnekoppeling wacht op gecontroleerde database-installatie.</p>
<?php else: ?>
<?php if ($error !== ''): ?><p role="alert"><?= $h($error) ?></p><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><p>Campagnekoppeling opgeslagen.</p><?php endif; ?>
<?php if ($write): ?>
<form method="post"><input type="hidden" name="csrf" value="<?= $h($_SESSION['marketing_csrf']) ?>"><input type="hidden" name="action" value="campaign"><h3>Nieuwe campagne</h3><label>Code <input name="code" maxlength="64" required pattern="[a-zA-Z0-9_-]+"></label><label>Naam <input name="name" maxlength="255" required></label><button type="submit">Campagne opslaan</button></form>
<form method="post"><input type="hidden" name="csrf" value="<?= $h($_SESSION['marketing_csrf']) ?>"><input type="hidden" name="action" value="link"><h3>Koppelen aan bestaand CRM-record</h3><label>Campagne <select name="campaign_id" required><?php foreach ($campaigns as $campaign): ?><option value="<?= (int)$campaign['id'] ?>"><?= $h($campaign['name']) ?> (<?= $h($campaign['code']) ?>)</option><?php endforeach; ?></select></label><label>CRM-type <select name="target_type"><option value="lead">Lead</option><option value="opportunity">Verkoopkans</option></select></label><label>Bestaand CRM-ID <input type="number" name="target_id" min="1" required></label><button type="submit">Koppelen</button></form>
<?php else: ?><p>Alleen-lezen: schrijfrechten voor campagnekoppelingen zijn uitgeschakeld.</p><?php endif; ?>
<h3>Bestaande campagnes</h3><ul><?php foreach ($campaigns as $campaign): ?><li><?= $h($campaign['name']) ?> (<?= $h($campaign['code']) ?>)</li><?php endforeach; ?></ul>
<?php endif; ?></section>
<section><h2>Websiteaanvragen en leads</h2><p>Registratie en opvolging verlopen via de bestaande CRM-werkstroom.</p><a href="<?= $h($base) ?>">Open CRM</a></section>
<section><h2>Marketingmateriaal</h2><p>Folders, publicaties en referentieprojecten worden hier beheerd na aansluiting van de documentmodule.</p></section>
<section><h2>Resultaten</h2><p>Conversie, offertewaarde en omzet worden pas getoond zodra bronkoppelingen zijn gecontroleerd.</p></section>
</main></body></html>
