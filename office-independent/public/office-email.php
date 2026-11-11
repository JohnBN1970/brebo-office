<?php
declare(strict_types=1);

// Native Office mail landing page. No Drupal bootstrap, tables or APIs.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['GET','POST'], true)) {
  header('Allow: GET, POST');
  http_response_code(405);
  exit;
}
if (getenv('OFFICE_BASE_PATH') !== '/office-test') {
  http_response_code(404);
  exit;
}
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
$allowedHost = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowedHost) || $allowedHost === '' || !hash_equals($allowedHost, $host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(403);
  exit;
}
try {
  foreach ([
    'WebsiteCrmOperatorContext.php',
    'WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php',
    'WebsiteOfficeCrmCookieSessionProvider.php',
  ] as $file) require_once dirname(__DIR__) . '/src/Intake/' . $file;
  $db = new PDO(
    getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
    getenv('OFFICE_INTAKE_DB_USER') ?: '',
    getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
  );
  $operator = (new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time()
  ))->currentOperator();
  if ($operator === null) {
    header('Location: /office-test/inloggen', true, 302);
    exit;
  }
  $mailboxes = [];
  $messages = [];
  $proposals = [];
  $boardEnabled = false;
  $boardCards = [];
  $csrfToken = '';
  $mailSchemaReady = getenv('OFFICE_NATIVE_MAIL_SCHEMA_VERIFIED') === 'enabled';
  if ($mailSchemaReady) {
    require_once dirname(__DIR__) . '/src/Mail/OfficeNativeMailboxRepository.php';
    $operatorId = filter_var($operator->operatorId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!is_int($operatorId)) throw new RuntimeException('Ongeldige Office-gebruiker.');
    $mailRepo = new \Brebo\Office\Mail\OfficeNativeMailboxRepository($db);
    $mailboxes = $mailRepo->visibleMailboxes($operatorId);
    $boardEnabled = getenv('OFFICE_MAIL_BOARD_SCHEMA_VERIFIED') === 'enabled';
    $boardCards = [];
    if ($boardEnabled) {
      require_once dirname(__DIR__) . '/src/Mail/OfficeMailBoardRepository.php';
      $board = new \Brebo\Office\Mail\OfficeMailBoardRepository($db);
      $csrfSecret = getenv('OFFICE_MAIL_BOARD_CSRF_SECRET');
      if (!is_string($csrfSecret) || strlen($csrfSecret) < 32) {
        throw new RuntimeException('Mailbord CSRF configuratie ontbreekt.');
      }
      $csrfToken = hash_hmac('sha256', $operatorId . '|' . hash('sha256', json_encode($_COOKIE, JSON_THROW_ON_ERROR)), $csrfSecret);
      if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if (($origin !== '' && !hash_equals('https://' . $allowedHost, $origin))
          || ($origin === '' && !str_starts_with($referer, 'https://' . $allowedHost . '/'))
          || !is_string($_POST['csrf'] ?? null)
          || !hash_equals($csrfToken, $_POST['csrf'])) {
          http_response_code(403); exit('Ongeldige aanvraag.');
        }
        $messageId = filter_var($_POST['message_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $module = $_POST['module'] ?? null;
        if (!is_int($messageId) || !is_string($module)) { http_response_code(422); exit('Ongeldige verplaatsing.'); }
        $moved = $board->move($messageId, $operatorId, $module);
        header('Location: /office-test/email?move=' . ($moved ? 'saved' : 'denied') . '#mailbord', true, 303);
        exit;
      }
      $boardCards = $board->cards($operatorId);
    } elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
      http_response_code(503); exit('Mailbord niet actief.');
    }
    $requested = filter_var($_GET['mailbox'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (is_int($requested)) $messages = $mailRepo->inbox($requested, $operatorId);
  }
} catch (Throwable $e) {
  error_log('office_mail_home_unavailable: ' . $e::class);
  http_response_code(503);
  exit('E-mail tijdelijk niet beschikbaar.');
}
$reviewEnabled = getenv('OFFICE_PERSONAL_MAIL_REVIEW_IDENTITY_VERIFIED') === 'enabled';
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>E-mail - BREBO Office</title>
<style>
body{margin:0;background:#f4f6fa;color:#1b2c3c;font:15px system-ui,sans-serif}
main{max-width:1100px;margin:36px auto;padding:0 20px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}
article{background:#fff;border:1px solid #dce3eb;border-radius:12px;padding:22px}
h2{margin-top:0}table{width:100%;border-collapse:collapse;background:white}td,th{padding:12px;border-bottom:1px solid #dce3eb;text-align:left}a{color:#175a91}.muted{color:#586c80}
.status{font-size:13px;font-weight:650;color:#805a19}.board{display:flex;gap:12px;overflow-x:auto;padding:12px 0}.lane{flex:0 0 225px;background:#e9eef6;border-radius:10px;padding:12px;min-height:190px}.lane h3{margin:0 0 12px}.mail-card{padding:12px;margin:8px 0;border-radius:8px;cursor:grab}.mail-card p{overflow-wrap:anywhere}.mail-card form{display:grid;gap:7px}.mail-card select{max-width:100%}
</style></head><body><main>
<h1>E-mail</h1>
<p class="muted">Native Office e-mailomgeving. Drupal is geen onderdeel van deze pagina.</p>
<div class="grid">
<article><h2>Inbox</h2><p>Functionele mailboxen en berichten.</p>
<?php if (!$mailSchemaReady): ?><p class="status">Native database nog niet geactiveerd</p>
<?php elseif ($mailboxes === []): ?><p class="status">Geen actieve mailboxen geconfigureerd</p>
<?php else: ?>
<?php foreach ($mailboxes as $mailbox): ?>
<p><a href="/office-test/email?mailbox=<?= (int)$mailbox['id'] ?>"><?= htmlspecialchars((string)$mailbox['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a></p>
<?php endforeach; ?>
<?php endif; ?></article>
<article><h2>Verzonden</h2><p>Verzonden correspondentie vanuit Office.</p><p class="status">Nog niet aangesloten</p></article>
<article><h2>Persoonlijke e-mail</h2><p>Alleen expliciet geselecteerde berichten gaan naar Office.</p><p class="status">Native push nog niet aangesloten</p></article>
<article><h2>Modulebord</h2><p>Office AI stelt een eigenaar voor; verplaats berichten naar de juiste module.</p><p><a href="#mailbord">Open modulebord</a></p></article>
</div>
<?php if ($mailSchemaReady): ?>
<section id="mailbord"><h2>E-mail per module</h2><p class="muted">Persoonlijke berichten staan uitsluitend voor de eigenaar in Privé. Overdracht naar een zakelijke module vereist een afzonderlijke, expliciete actie en is nog niet beschikbaar.</p>
<?php if (!$boardEnabled): ?><p class="status">Modulebord-database nog niet geactiveerd.</p>
<?php else: ?>
<?php if (($_GET['move'] ?? '') === 'saved'): ?><p>Bericht verplaatst. De wijziging is vastgelegd.</p><?php endif; ?>
<div class="board">
<?php foreach (['nieuw'=>'Nieuw','prive'=>'Privé','crm'=>'CRM','projecten'=>'Projecten','finance'=>'Finance','documenten'=>'Documenten','inspecties'=>'Inspecties'] as $key=>$label): ?>
<section class="lane" data-module="<?= $key ?>"><h3><?= $label ?></h3>
<?php foreach ($boardCards as $card): if ($card['module_key'] !== $key) continue; ?>
<article class="mail-card" draggable="true" data-message="<?= (int)$card['id'] ?>">
<strong><?= htmlspecialchars((string)$card['subject'], ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></strong>
<p><?= htmlspecialchars((string)$card['sender'], ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></p>
<?php if (!empty($card['suggested_module'])): ?><small>AI-voorstel: <?= htmlspecialchars((string)$card['suggested_module'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></small><?php endif; ?>
<?php if ($key !== 'prive'): ?><form method="post" action="/office-test/email">
<input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken,ENT_QUOTES,'UTF-8') ?>">
<input type="hidden" name="message_id" value="<?= (int)$card['id'] ?>">
<label>Verplaatsen <select name="module">
<?php foreach (['nieuw'=>'Nieuw','prive'=>'Privé','crm'=>'CRM','projecten'=>'Projecten','finance'=>'Finance','documenten'=>'Documenten','inspecties'=>'Inspecties'] as $destination=>$destinationLabel): ?>
<option value="<?= $destination ?>" <?= $destination===$key?'selected':'' ?>><?= $destinationLabel ?></option>
<?php endforeach; ?></select></label><button type="submit">Verplaats</button>
</form><?php else: ?><small>Privé: niet automatisch deelbaar</small><?php endif; ?></article>
<?php endforeach; ?></section>
<?php endforeach; ?></div>
<script>
(() => {
 let active=null;
 document.querySelectorAll('.mail-card').forEach(card=>{
   card.addEventListener('dragstart',event=>{active=card;event.dataTransfer.setData('text/plain',card.dataset.message);});
   card.addEventListener('dragend',()=>{active=null;});
 });
 document.querySelectorAll('.lane').forEach(lane=>{
   lane.addEventListener('dragover',event=>event.preventDefault());
   lane.addEventListener('drop',event=>{
     event.preventDefault();
     if(!active)return;
     const form=active.querySelector('form');
     form.querySelector('select[name="module"]').value=lane.dataset.module;
     form.requestSubmit();
   });
 });
})();
</script>
<?php endif; ?></section>
<?php endif; ?>
<?php if ($messages !== []): ?>
<section><h2>Berichten</h2><table><thead><tr><th>Afzender</th><th>Onderwerp</th><th>Ontvangen</th></tr></thead><tbody>
<?php foreach ($messages as $message): ?>
<tr><td><?= htmlspecialchars((string)$message['sender'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td><td><?= htmlspecialchars((string)$message['subject'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td><td><?= htmlspecialchars((string)$message['received_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td></tr>
<?php endforeach; ?></tbody></table></section>
<?php endif; ?>
<p><a href="/office-test/">Terug naar Office</a></p>
</main></body></html>
