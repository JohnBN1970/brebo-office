<?php
declare(strict_types=1);
// Office-test settings: site-administrator-only, read-only configuration overview.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
$method=$_SERVER['REQUEST_METHOD']??'';
$isEmail=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH)==='/office-test/instellingen/email';
$enabled=$isEmail && getenv('OFFICE_TEST_MAIL_PROVISIONING_ENABLED')==='enabled';
if ($method!=='GET' && !($method==='POST' && $enabled)) { header('Allow: GET'); http_response_code(405); exit; }
require_once dirname(__DIR__).'/src/Intake/OfficeMount.php';
if (!\Brebo\Office\Intake\OfficeMount::isTest()) { http_response_code(404); exit; }
foreach (['WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php',
  'WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
  require_once dirname(__DIR__).'/src/Intake/'.$file;
}
require_once dirname(__DIR__).'/src/Approval/OfficeTestSiteAdministratorAccess.php';
$h=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
try {
  $dsn=getenv('OFFICE_INTAKE_PDO_DSN');
  if (!is_string($dsn)||$dsn==='') throw new RuntimeException('Database unavailable');
  $db=new PDO($dsn,getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  if ($db->query('SELECT DATABASE()')->fetchColumn()!=='brebo_office_test') throw new RuntimeException('Wrong database');
  $sessions=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
  $operator=$sessions->currentOperator();
  if ($operator===null) {http_response_code(401);exit('Aanmelden vereist.');}
  if (!(new \Brebo\Office\Approval\OfficeTestSiteAdministratorAccess($db))
    ->isAuthorized((string)$operator->operatorId,true)) {http_response_code(403);exit('Alleen sitebeheerder.');}
  $result=null;
  $csrf='';
  if ($enabled) {
    if (!session_start(['cookie_secure'=>true,'cookie_httponly'=>true,'cookie_samesite'=>'Strict','use_strict_mode'=>true])) throw new RuntimeException('Session unavailable');
    if (($_SESSION['mail_provision_operator']??null)!==(string)$operator->operatorId) {
      $_SESSION['mail_provision_operator']=(string)$operator->operatorId;
      unset($_SESSION['mail_provision_csrf']);
    }
    $csrf=$_SESSION['mail_provision_csrf']??=bin2hex(random_bytes(32));
    if ($method==='POST') {
      if (!is_string($_POST['csrf']??null) || !hash_equals($csrf,$_POST['csrf'])) {http_response_code(403);exit('Ongeldig formulier.');}
      try {
        $core=dirname(__DIR__).'/packages/brebo-mail-core/src/';
        if (!is_dir($core)) $core=dirname(__DIR__,2).'/packages/brebo-mail-core/src/';
        foreach (['Contract/GatewayHttpClientInterface.php','Contract/MailGatewayInterface.php','Domain/GatewayRequestSignature.php','Infrastructure/HttpMailGateway.php'] as $file) require_once $core.$file;
        require_once dirname(__DIR__).'/src/Mail/OfficeGatewayHttpClient.php';
        require_once dirname(__DIR__).'/src/Mail/OfficeTestMailboxProvisioning.php';
        $client=new \Brebo\Office\Mail\OfficeGatewayHttpClient();
        $gateway=new \Brebo\Mail\Infrastructure\HttpMailGateway($client);
        require_once dirname(__DIR__).'/src/Mail/OfficeTestMailboxRegistration.php';
        $provisioning=new \Brebo\Office\Mail\OfficeTestMailboxProvisioning($gateway);
        $registration=new \Brebo\Office\Mail\OfficeTestMailboxRegistration($db,$provisioning);
        $address=is_string($_POST['address']??null)?$_POST['address']:'';
        $result=($_POST['action']??'')==='reconcile' ? $registration->reconcile($address,$client) : $registration->register($address);
      } catch (Throwable $e) {
        error_log('Office mailbox staging failed: '.$e::class);
        $result=['status'=>'failed'];
      }
      $_SESSION['mail_provision_csrf']=bin2hex(random_bytes(32));
      $csrf=$_SESSION['mail_provision_csrf'];
    }
    session_write_close();
  }
  $mailboxes=[];
  $mailCounts=[];
  if (parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH)==='/office-test/instellingen/email') {
    $mailboxes=$db->query("SELECT id,address,label,mailbox_type,active,credential_ref
      FROM office_mail_mailboxes ORDER BY id LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    if ($enabled) {
      $mailboxes=$db->query("SELECT id,address,label,mailbox_type,active,credential_ref,gateway_ref
        FROM office_mail_mailboxes ORDER BY id LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    }
    $mailCounts=$db->query('SELECT mailbox_id,COUNT(*) AS total FROM office_mail_messages GROUP BY mailbox_id')->fetchAll(PDO::FETCH_KEY_PAIR);
  }
} catch (Throwable $e) {
  error_log('Office settings unavailable: '.$e::class);
  http_response_code(503);exit('Instellingen tijdelijk niet beschikbaar.');
}
$isEmail=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH)==='/office-test/instellingen/email';
?><!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $isEmail?'E-mailinstellingen':'Instellingen' ?> · BREBO Office</title>
<style>
:root{font-family:system-ui,-apple-system,Segoe UI,sans-serif;color:#18334c;background:#f1f4fa}
*{box-sizing:border-box}body{margin:0}main{max-width:1200px;margin:36px auto;padding:0 24px}
a{color:#195c92;text-decoration:none}a:hover{text-decoration:underline}header{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:25px}
h1{font-size:27px;margin:0}h2{font-size:18px;margin:0 0 15px}h3{font-size:15px;margin:0 0 8px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(245px,1fr));gap:16px}
.card{background:white;border:1px solid #e0e6ee;border-radius:12px;padding:23px;min-height:150px}
.card a{display:block;padding:7px 0}.muted{color:#66798c;font-size:14px}
table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:13px;border-bottom:1px solid #e7ebf0}
th{font-size:13px;color:#60748a}.tag{display:inline-block;border-radius:20px;background:#f1f3f7;padding:5px 10px;font-size:12px}
.notice{background:#fff7e8;border:1px solid #f1dcae;border-radius:9px;padding:15px;margin-top:18px}
@media(max-width:650px){main{margin:20px auto;padding:0 14px}table{font-size:13px}th,td{padding:8px}}
</style></head><body><main>
<header><div><p class="muted"><a href="/office-test/">Office</a> / <a href="/office-test/instellingen">Instellingen</a><?= $isEmail?' / E-mail':'' ?></p>
<h1><?= $isEmail?'E-mailinstellingen':'Instellingen' ?></h1></div>
<a href="/office-test/email">Naar E-mail</a></header>
<?php if (!$isEmail): ?>
<div class="grid">
<section class="card"><h2>Algemeen</h2><p class="muted">Organisatie en gebruikers</p><a href="/office-test/instellingen/organisatie/projectrechten">Organisatie en projectrechten ›</a></section>
<section class="card"><h2>Financieel</h2><p class="muted">Standaardvaluta en bedragnotatie</p><a href="/office-test/instellingen/organisatie/financieel">Financiële instellingen ›</a></section>
<section class="card"><h2>Beveiliging</h2><p class="muted">Rollen, rechten en goedkeuring</p><a href="/office-test/instellingen/organisatie/projectrechten">Projectrechten ›</a></section>
<section class="card"><h2>Kanalen</h2><p class="muted">E-mail en communicatie</p><a href="/office-test/instellingen/email">E-mailinstellingen ›</a></section>
<section class="card"><h2>Procesbeheer</h2><p class="muted">Blauwdrukken en goedkeuringsflows</p><a href="/office-test/instellingen/blauwdrukken">Blauwdrukinstellingen ›</a></section>
<section class="card"><h2>API &amp; Integraties</h2><p class="muted">Koppelingen, API-sleutels en webhooks</p><span class="tag">Beheer volgt</span></section>
<section class="card"><h2>Nummerreeksen</h2><p class="muted">Project-, offerte- en factuurnummers</p><span class="tag">Nog niet aangesloten</span></section>
<section class="card"><h2>Veldenbeheer</h2><p class="muted">Eigen velden, keuzelijsten en formulieren</p><span class="tag">Nog niet aangesloten</span></section>
</div>
<?php else: ?>
<?php if ($enabled): ?>
<section class="card"><h2>Testmailbox aanvragen</h2>
<p class="muted">Alleen testmail.brebobv.nl. Aanvragen activeren geen mailbox.</p>
<?php if ($result!==null): ?><p role="status"><?= $result['status']==='staged'?'Mailbox geregistreerd in Office en Gateway; nog inactief.':'Aanvraag niet bevestigd; controleer Office en Gateway voordat opnieuw wordt geprobeerd.' ?></p><?php endif; ?>
<form method="post" action="/office-test/instellingen/email">
<input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
<label for="address">E-mailadres</label>
<input id="address" name="address" type="email" required maxlength="100" placeholder="naam@testmail.brebobv.nl">
<button type="submit" name="action" value="register">Testmailbox aanvragen</button>
<button type="submit" name="action" value="reconcile">Bestaande reservering herstellen</button>
</form></section>
<?php endif; ?>
<section class="card"><h2>Geregistreerde mailboxen</h2>
<p class="muted">Actuele Office-test-databasegegevens. Inloggegevens worden nooit getoond.</p>
<table><thead><tr><th>Mailbox</th><th>Type</th><th>Status</th><th>Configuratiereferentie</th><th>Berichten</th></tr></thead><tbody>
<?php foreach($mailboxes as $box): ?><tr><td><?= $h($box['address']) ?><br><span class="muted"><?= $h($box['label']) ?></span></td>
<td><?= $h($box['mailbox_type']) ?></td><td><span class="tag"><?= (int)$box['active']===1?'Actief':($enabled && str_ends_with($box['address'],'@testmail.brebobv.nl') ? (empty($box['gateway_ref'])?'Controle vereist':'Geregistreerd, inactief'):'Inactief') ?></span></td>
<td><?= $enabled && !empty($box['gateway_ref'])?'Gateway geregistreerd':($box['credential_ref']!==''?'Credential ingesteld':'Niet ingesteld') ?></td><td><?= (int)($mailCounts[$box['id']]??0) ?></td></tr><?php endforeach; ?>
<?php if (!$mailboxes): ?><tr><td colspan="5">Geen mailboxen geregistreerd.</td></tr><?php endif; ?>
</tbody></table></section>
<div class="grid" style="margin-top:16px">
<section class="card"><h2>IMAP-verbinding</h2><p class="muted">PHP IMAP: <?= extension_loaded("imap")?"beschikbaar":"ontbreekt" ?>. De daadwerkelijke verbinding is nog niet getest.</p><span class="tag">Controle vereist</span></section>
<section class="card"><h2>Synchronisatie</h2><p class="muted">Automatische verwerking wordt pas na verbindingscontrole vrijgegeven.</p><span class="tag">Geen bediening beschikbaar</span></section>
<section class="card"><h2>Toegangsrechten</h2><p class="muted">Mailboxrechten worden via de bestaande Office-autorisatie afgedwongen.</p></section>
</div>
<p class="notice">Mailboxactivering, credentials wijzigen en synchronisatie starten zijn nog niet beschikbaar.</p>
<?php endif; ?>
</main></body></html>
