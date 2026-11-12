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
  $duplicateCandidates = [];
  $selectedMailId = null;
  $mailSchemaReady = getenv('OFFICE_NATIVE_MAIL_SCHEMA_VERIFIED') === 'enabled';
  if ($mailSchemaReady) {
    require_once dirname(__DIR__) . '/src/Mail/OfficeNativeMailboxRepository.php';
    $operatorId = (string) $operator->operatorId;
    if ($operatorId === '' || strlen($operatorId) > 128) throw new RuntimeException('Ongeldige Office-gebruiker.');
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
      $selectedMailId = filter_var($_GET['inspect_mail'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
      if (is_int($selectedMailId)) {
        require_once dirname(__DIR__) . '/src/Mail/OfficeMailDuplicateCandidates.php';
        $duplicateCandidates = (new \Brebo\Office\Mail\OfficeMailDuplicateCandidates($db))->forMessage($selectedMailId, $operatorId);
      }
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
 .candidate-panel{background:#fff;border:1px solid #dce3eb;border-radius:12px;padding:18px;margin:16px 0}.candidate-panel li{margin:8px 0}.status{font-size:13px;font-weight:650;color:#805a19}.board{display:flex;gap:0;overflow-x:auto;padding:12px 0;align-items:stretch}.lane{box-sizing:border-box;position:relative;flex:0 0 225px;min-width:145px;background:#e9eef6;border-radius:6px;padding:12px;min-height:190px;border-right:1px solid #c8d1dd}.lane h3{padding-right:8px}.lane-resizer{position:absolute;right:-5px;top:0;bottom:0;width:10px;cursor:col-resize;z-index:3;touch-action:none}.lane-resizer:hover,.lane-resizer:focus-visible{background:#3579ac55;outline:none}.board.is-resizing,.board.is-resizing *{cursor:col-resize!important;user-select:none!important}.lane h3{margin:0 0 12px}.mail-card{padding:12px;margin:8px 0;border-radius:8px;cursor:grab}.mail-card p{overflow-wrap:anywhere}.mail-card form{display:grid;gap:7px}.mail-card select{max-width:100%}
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
<?php if (is_int($selectedMailId)): ?>
<div class="candidate-panel"><h3>Controle mogelijke dubbelingen - bericht #<?= (int)$selectedMailId ?></h3>
<p class="muted">Dit zijn uitsluitend mogelijke vervolgberichten met dezelfde afzender en hetzelfde onderwerp. Er wordt niets automatisch gekoppeld of aangemaakt.</p>
<?php if ($duplicateCandidates === []): ?><p>Geen overeenkomende e-mails gevonden in de gecontroleerde selectie.</p>
<?php else: ?><ul><?php foreach ($duplicateCandidates as $candidate): ?><li><strong><?= htmlspecialchars($candidate['subject'], ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></strong> - <?= htmlspecialchars($candidate['received_at'], ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?> (bericht #<?= (int)$candidate['id'] ?>)</li><?php endforeach; ?></ul><?php endif; ?>
<p><a href="/office-test/email#mailbord">Sluiten</a></p></div>
<?php endif; ?>
<?php if (($_GET['move'] ?? '') === 'saved'): ?><p>Bericht verplaatst. De wijziging is vastgelegd.</p><?php endif; ?>
<div class="board" id="office-mail-columns">
<?php foreach (['nieuw'=>'Nieuw','prive'=>'Privé','crm'=>'CRM','projecten'=>'Projecten','finance'=>'Finance','documenten'=>'Documenten','inspecties'=>'Inspecties'] as $key=>$label): ?>
<section class="lane" data-module="<?= $key ?>"><h3><?= $label ?></h3>
<?php foreach ($boardCards as $card): if ($card['module_key'] !== $key) continue; ?>
<article class="mail-card" draggable="true" data-message="<?= (int)$card['id'] ?>">
<strong><?= htmlspecialchars((string)$card['subject'], ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></strong>
<p><a href="/office-test/email?inspect_mail=<?= (int)$card['id'] ?>#mailbord">Controleer dubbelingen</a></p>
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
<?php endforeach; ?><div class="lane-resizer" role="separator" tabindex="0" aria-orientation="vertical" aria-label="Breedte <?= htmlspecialchars($label,ENT_QUOTES,'UTF-8') ?> aanpassen" title="Sleep om kolombreedte aan te passen"></div></section>
<?php endforeach; ?></div>
<script>
(() => {
 let active=null;
 document.querySelectorAll('.mail-card').forEach(card=>{
   card.addEventListener('dragstart',event=>{active=card;event.dataTransfer.setData('text/plain',card.dataset.message);});
   card.addEventListener('dragend',()=>{active=null;});
 });
 document.querySelectorAll('.lane').forEach(lane=>{
   lane.addEventListener('dragover',event=>{if(active && active.querySelector('form') && lane.dataset.module!=='prive')event.preventDefault();});
   lane.addEventListener('drop',event=>{
     event.preventDefault();
     if(!active)return;
     const form=active.querySelector('form');
     if(!form || lane.dataset.module==='prive')return;
     form.querySelector('select[name="module"]').value=lane.dataset.module;
     form.requestSubmit();
   });
 });

 // Column separators resize only the column to their left; message drag/drop remains separate.
 const board=document.getElementById('office-mail-columns');
 const lanes=Array.from(board.querySelectorAll('.lane'));
 const storageKey='brebo-office-mail-widths-v1-<?= hash('sha256', (string)($operatorId ?? '')) ?>';
 const minWidth=145,maxWidth=900;
 const clamp=n=>Math.max(minWidth,Math.min(maxWidth,n));
 try {
   const stored=JSON.parse(localStorage.getItem(storageKey)||'{}');
   if(stored && typeof stored==='object' && !Array.isArray(stored)){
     lanes.forEach(lane=>{
       const value=stored[lane.dataset.module];
       if(Number.isFinite(value))lane.style.flexBasis=clamp(value)+'px';
     });
   }
 }catch(_error){}
 const saveWidths=()=>{
   const values={};
   lanes.forEach(lane=>values[lane.dataset.module]=Math.round(lane.getBoundingClientRect().width));
   try{localStorage.setItem(storageKey,JSON.stringify(values));}catch(_error){}
 };
 lanes.forEach(lane=>{
   const handle=lane.querySelector('.lane-resizer');
   handle.addEventListener('pointerdown',event=>{
     if(event.button!==0)return;
     event.preventDefault();
     const startX=event.clientX;
     const startWidth=lane.getBoundingClientRect().width;
     board.classList.add('is-resizing');
     handle.setPointerCapture(event.pointerId);
     const onMove=moveEvent=>{
       lane.style.flexBasis=clamp(startWidth+moveEvent.clientX-startX)+'px';
     };
     const finish=()=>{
       handle.removeEventListener('pointermove',onMove);
       handle.removeEventListener('pointerup',finish);
       handle.removeEventListener('pointercancel',finish);
       board.classList.remove('is-resizing');
       saveWidths();
     };
     handle.addEventListener('pointermove',onMove);
     handle.addEventListener('pointerup',finish);
     handle.addEventListener('pointercancel',finish);
   });
   handle.addEventListener('keydown',event=>{
     if(event.key!=='ArrowLeft'&&event.key!=='ArrowRight')return;
     event.preventDefault();
     lane.style.flexBasis=clamp(lane.getBoundingClientRect().width+(event.key==='ArrowRight'?20:-20))+'px';
     saveWidths();
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
