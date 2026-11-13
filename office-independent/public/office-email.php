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
  $crmRelationCandidates = [];
  $buildingProjectCandidates = ['buildings'=>[], 'projects'=>[]];
  $buildingRecognitionStatus = 'not_checked';
  $crmRecognitionStatus = 'not_checked';
  $selectedMailId = null;
  $openedMessage = null;
  $openedAttachments = [];
  $attachmentStatus = 'not_checked';
  $attachmentDuplicateCounts = [];
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
      // Attachment download: session-authenticated, access-controlled and never a public file URL.
      $downloadId = filter_var($_GET['download_attachment'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
      if (is_int($downloadId)) {
        $downloadStmt = $db->prepare("SELECT att.filename,att.content_type,att.byte_size,att.sha256,att.private_storage_key
          FROM office_mail_attachments att
          JOIN office_mail_messages m ON m.id=att.message_id
          JOIN office_mail_mailboxes b ON b.id=m.mailbox_id
          WHERE att.id=:attachment AND b.active=1 AND
          ((b.mailbox_type='functional' AND EXISTS
            (SELECT 1 FROM office_mail_mailbox_access a WHERE a.mailbox_id=b.id
             AND a.operator_id=:operator AND a.can_read=1))
           OR (b.mailbox_type='personal' AND b.owner_operator_id=:owner))
          LIMIT 1");
        $downloadStmt->execute([':attachment'=>$downloadId,':operator'=>$operatorId,':owner'=>$operatorId]);
        $downloadRow = $downloadStmt->fetch(PDO::FETCH_ASSOC);
        if (!$downloadRow) { http_response_code(404); exit('Bijlage niet gevonden.'); }
        $storageRoot = getenv('OFFICE_MAIL_ATTACHMENT_STORAGE_ROOT');
        if (!is_string($storageRoot) || $storageRoot === '') {
          http_response_code(503); exit('Private bijlagenopslag nog niet geconfigureerd.');
        }
        try {
          require_once dirname(__DIR__) . '/src/Documents/OfficePrivateFileStorage.php';
          $storage = new \\Brebo\\Office\\Documents\\OfficePrivateFileStorage($storageRoot);
          $filePath = $storage->authorizedPath((string)$downloadRow['private_storage_key']);
          if (!hash_equals((string)$downloadRow['sha256'], (string)hash_file('sha256',$filePath))
            || filesize($filePath) !== (int)$downloadRow['byte_size']) {
            throw new RuntimeException('attachment_integrity_mismatch');
          }
          $handle = fopen($filePath,'rb');
          if ($handle === false) throw new RuntimeException('attachment_open_failed');
        } catch (Throwable $downloadError) {
          error_log('office_mail_attachment_download_unavailable: '.$downloadError::class);
          http_response_code(503); exit('Bijlage niet beschikbaar.');
        }
        $safeName = trim(preg_replace('/[\\x00-\\x1F\\x7F"\\\\\\/]+/u','_', (string)$downloadRow['filename']) ?? 'bijlage');
        if ($safeName === '') $safeName='bijlage';
        $asciiName = preg_replace('/[^A-Za-z0-9._-]/','_', $safeName) ?: 'bijlage';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.$asciiName.'"; filename*=UTF-8\'\''.rawurlencode($safeName));
        header('Content-Length: '.(string)filesize($filePath));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        fpassthru($handle);
        fclose($handle);
        exit;
      }
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
      // Keep the category board visible until a message is explicitly opened.
      if (is_int($selectedMailId)) {
        require_once dirname(__DIR__) . '/src/Mail/OfficeMailDuplicateCandidates.php';
        $duplicateCandidates = (new \Brebo\Office\Mail\OfficeMailDuplicateCandidates($db))->forMessage($selectedMailId, $operatorId);
        // CRM recognition is read-only and never runs for personal mail.
        $selectedCard = null;
        foreach ($boardCards as $candidateCard) {
          if ((int)$candidateCard['id'] === $selectedMailId) { $selectedCard = $candidateCard; break; }
        }
        if ($selectedCard !== null) {
          // Load the message only after the selected card was verified as visible.
          $openStmt = $db->prepare("SELECT m.sender,m.recipient,m.subject,m.received_at,m.body_text
              FROM office_mail_messages m JOIN office_mail_mailboxes b ON b.id=m.mailbox_id
              WHERE m.id=:id AND b.active=1 AND
              ((b.mailbox_type='functional' AND EXISTS
                (SELECT 1 FROM office_mail_mailbox_access a WHERE a.mailbox_id=b.id
                 AND a.operator_id=:operator AND a.can_read=1))
               OR (b.mailbox_type='personal' AND b.owner_operator_id=:owner))
              LIMIT 1");
          $openStmt->execute([':id'=>$selectedMailId,':operator'=>$operatorId,':owner'=>$operatorId]);
          $openedMessage = $openStmt->fetch(PDO::FETCH_ASSOC) ?: null;
          if ($openedMessage !== null) {
            try {
              $attachmentStmt = $db->prepare("SELECT att.id,att.filename,att.content_type,att.byte_size,att.sha256
                FROM office_mail_attachments att
                INNER JOIN office_mail_messages m ON m.id=att.message_id
                INNER JOIN office_mail_mailboxes b ON b.id=m.mailbox_id
                WHERE m.id=:message AND b.active=1 AND
                  ((b.mailbox_type='functional' AND EXISTS
                    (SELECT 1 FROM office_mail_mailbox_access a WHERE a.mailbox_id=b.id
                     AND a.operator_id=:operator AND a.can_read=1))
                   OR (b.mailbox_type='personal' AND b.owner_operator_id=:owner))
                ORDER BY att.id LIMIT 100");
              $attachmentStmt->execute([':message'=>$selectedMailId,':operator'=>$operatorId,':owner'=>$operatorId]);
              $openedAttachments = $attachmentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
              $attachmentStatus = 'checked';
              // Compare only attachment hashes of messages visible to this operator.
              // No storage keys or binary content are returned.
              if ($openedAttachments !== []) {
                $hashes = array_values(array_unique(array_filter(
                  array_column($openedAttachments, 'sha256'),
                  static fn($hash):bool => is_string($hash) && preg_match('/^[a-f0-9]{64}$/D', $hash) === 1
                )));
                if ($hashes !== []) {
                  $hashPlaceholders = implode(',', array_fill(0, count($hashes), '?'));
                  $duplicateSql = "SELECT att.sha256,COUNT(DISTINCT att.message_id) AS message_count
                    FROM office_mail_attachments att
                    JOIN office_mail_messages m ON m.id=att.message_id
                    JOIN office_mail_mailboxes b ON b.id=m.mailbox_id
                    WHERE att.sha256 IN ($hashPlaceholders) AND b.active=1 AND
                    ((b.mailbox_type='functional' AND EXISTS
                      (SELECT 1 FROM office_mail_mailbox_access a
                       WHERE a.mailbox_id=b.id AND a.operator_id=? AND a.can_read=1))
                     OR (b.mailbox_type='personal' AND b.owner_operator_id=?))
                    GROUP BY att.sha256";
                  $duplicateStmt = $db->prepare($duplicateSql);
                  $duplicateStmt->execute(array_merge($hashes, [$operatorId,$operatorId]));
                  foreach ($duplicateStmt->fetchAll(PDO::FETCH_ASSOC) as $hashRow) {
                    $attachmentDuplicateCounts[(string)$hashRow['sha256']] = (int)$hashRow['message_count'];
                  }
                }
              }
            } catch (Throwable $attachmentError) {
              error_log('office_mail_attachment_metadata_unavailable: ' . $attachmentError::class);
              $attachmentStatus = 'unavailable';
            }
          }
        }
        if ($selectedCard !== null && (string)$selectedCard['module_key'] !== 'prive'
            && getenv('OFFICE_NATIVE_PROJECTS_ENABLED') === 'enabled'
            && getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED') === 'enabled') {
          try {
            require_once dirname(__DIR__) . '/src/Mail/OfficeMailBuildingProjectRecognition.php';
            // Read the stored body only after mailbox-level authorization.
            // No body is rendered or logged; limit extraction to 16 KB.
            $mailText = (string)$selectedCard['subject'];
            $bodyStmt = $db->prepare("SELECT m.body_text FROM office_mail_messages m
                INNER JOIN office_mail_mailboxes b ON b.id=m.mailbox_id
                WHERE m.id=:message AND b.active=1 AND b.mailbox_type='functional'
                AND EXISTS (SELECT 1 FROM office_mail_mailbox_access a
                  WHERE a.mailbox_id=b.id AND a.operator_id=:operator AND a.can_read=1)
                LIMIT 1");
            $bodyStmt->execute([':message'=>$selectedMailId,':operator'=>$operatorId]);
            $storedBody = $bodyStmt->fetchColumn();
            if (is_string($storedBody) && $storedBody !== '') {
              $mailText .= " " . substr($storedBody, 0, 16384);
            }
            $buildingProjectCandidates = (new \Brebo\Office\Mail\OfficeMailBuildingProjectRecognition($db))
              ->forSubject($mailText, $operatorId);
            $buildingRecognitionStatus = 'checked';
          } catch (Throwable $buildingRecognitionError) {
            error_log('office_mail_building_recognition_unavailable: ' . $buildingRecognitionError::class);
            $buildingRecognitionStatus = 'unavailable';
          }
        }
        if ($selectedCard !== null && (string)$selectedCard['module_key'] !== 'prive'
            && getenv('OFFICE_TEST_CRM_ENABLED') === 'enabled'
            && getenv('OFFICE_CRM_AUTH_MODE') === 'session_only'
            && getenv('OFFICE_CRM_RELATIONS_SCHEMA_VERIFIED') === 'enabled') {
          $activeOperator = $db->prepare('SELECT 1 FROM office_operator_accounts WHERE id=? AND active=1 LIMIT 1');
          $activeOperator->execute([$operatorId]);
          if ($activeOperator->fetchColumn() !== false) {
            require_once dirname(__DIR__) . '/src/Crm/OfficeCrmMailRelationCandidates.php';
            try {
              // CRM currently grants active Office operators access to the relation list.
              $crmRelationCandidates = (new \Brebo\Office\Crm\OfficeCrmMailRelationCandidates($db))
                ->forSender((string)$selectedCard['sender'], static fn(string $relationId): bool => true);
              $crmRecognitionStatus = 'checked';
            } catch (Throwable $crmRecognitionError) {
              error_log('office_mail_crm_recognition_unavailable: ' . $crmRecognitionError::class);
              $crmRecognitionStatus = 'unavailable';
            }
          }
        }

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
main{max-width:none;margin:20px auto;padding:0 20px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}
article{background:#fff;border:1px solid #dce3eb;border-radius:12px;padding:22px}
h2{margin-top:0}table{width:100%;border-collapse:collapse;background:white}td,th{padding:12px;border-bottom:1px solid #dce3eb;text-align:left}a{color:#175a91}.muted{color:#586c80}
 .candidate-panel{background:#fff;border:1px solid #dce3eb;border-radius:12px;padding:18px;margin:16px 0}.candidate-panel li{margin:8px 0}.status{font-size:13px;font-weight:650;color:#805a19}.board{display:flex;gap:8px;overflow-x:auto;overflow-y:hidden;padding:8px 0;align-items:stretch;min-width:0;flex:1;height:min(75vh,780px)}.lane{box-sizing:border-box;position:relative;flex:0 0 225px;min-width:145px;background:#e9eef6;border-radius:8px;padding:12px;min-height:0;overflow-y:auto;overflow-x:hidden;border:1px solid #c8d1dd;scrollbar-width:thin}.lane h3{padding-right:8px}.lane-resizer{position:absolute;right:-5px;top:0;bottom:0;width:10px;cursor:col-resize;z-index:3;touch-action:none}.lane-resizer:hover,.lane-resizer:focus-visible{background:#3579ac55;outline:none}.board.is-resizing,.board.is-resizing *{cursor:col-resize!important;user-select:none!important}.lane h3{margin:0 0 12px}.mail-card{padding:12px;margin:8px 0;border-radius:8px;cursor:grab}.mail-card p{overflow-wrap:anywhere}.mail-card form{display:grid;gap:7px}.mail-card select{max-width:100%}
.mail-workspace{display:flex;gap:16px;align-items:stretch;min-width:0}.mail-detail{box-sizing:border-box;flex:0 0 min(380px,34vw);min-width:260px;max-height:min(75vh,780px);overflow-y:auto;scrollbar-width:thin}.mail-detail .candidate-panel{margin:0}.mail-card p{margin:7px 0;font-size:13px}.mail-card strong{font-size:13px;overflow-wrap:anywhere}.lane h3{position:sticky;top:-12px;background:#e9eef6;padding:8px 0;z-index:1}@media(max-width:950px){.mail-workspace{flex-direction:column}.mail-detail{flex:auto;min-width:0;max-height:420px}.board{height:65vh;min-height:400px}}@media(max-width:600px){main{padding:0 10px}.lane{flex-basis:210px}.board{height:60vh}}.mail-workspace{display:grid;grid-template-columns:minmax(0,65fr) minmax(290px,35fr);gap:14px;width:100%;align-items:start}.board{grid-column:1;grid-row:1;width:100%;height:min(72vh,780px);min-height:420px}.mail-detail{grid-column:2;grid-row:1;width:100%;min-width:0;max-height:min(72vh,780px);height:min(72vh,780px);overflow-y:auto}.mail-card{padding:9px}.mail-card form{gap:4px}.mail-card button{cursor:pointer}.mail-toolbar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:12px 14px;background:#fff;border:1px solid #dce3eb;border-radius:10px;margin-bottom:12px}.mail-toolbar input{flex:1;min-width:170px;padding:9px;border:1px solid #c8d1dd;border-radius:7px}.mail-toolbar a{white-space:nowrap}.mail-empty-search{display:none;font-size:12px;color:#586c80}.mail-card[hidden]{display:none!important}@media(max-width:1100px){.mail-workspace{grid-template-columns:minmax(0,1fr)}.board{grid-column:1;grid-row:1}.mail-detail{grid-column:1;grid-row:2;max-height:420px;height:auto}}.mail-workspace:not(.is-open){display:block}.mail-workspace:not(.is-open) .mail-detail{display:none}.mail-workspace:not(.is-open) .board{height:min(76vh,830px)}.mail-workspace.is-open{display:grid;grid-template-columns:minmax(210px,24%) minmax(320px,1fr) minmax(260px,27%);gap:12px;align-items:start}.mail-workspace.is-open .mail-detail{grid-column:3;grid-row:1;max-height:76vh;height:76vh}.mail-thread-list{grid-column:1;grid-row:1;background:#fff;border:1px solid #dce3eb;border-radius:9px;height:76vh;overflow-y:auto;padding:12px;box-sizing:border-box}.thread-item{display:block;padding:12px 8px;border-bottom:1px solid #e2e8f0;text-decoration:none;color:#1b2c3c}.thread-item strong,.thread-item small{display:block;overflow-wrap:anywhere}.thread-item small{color:#586c80;margin-top:5px}.thread-item.active{background:#e9eef6}.back-link{display:block;margin-bottom:16px}.mail-reading{grid-column:2;grid-row:1;box-sizing:border-box;height:76vh;overflow-y:auto;padding:22px}.mail-body{white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.55}.mail-workspace.is-open .mail-detail .candidate-panel{padding:14px}@media(max-width:1100px){.mail-workspace.is-open{grid-template-columns:minmax(190px,35%) minmax(0,1fr)}.mail-workspace.is-open .mail-detail{grid-column:1 / -1;grid-row:2;height:auto;max-height:400px}}@media(max-width:650px){.mail-workspace.is-open{display:flex;flex-direction:column}.mail-thread-list,.mail-reading{width:100%;height:auto;max-height:350px}.mail-workspace.is-open .mail-detail{width:100%}}.mail-card{cursor:pointer}.mail-card:focus-visible{outline:3px solid #175a91;outline-offset:2px}.mail-card:hover{border-color:#91b5d0;background:#f9fcff}.mail-attachments{border-top:1px solid #dce3eb;margin-top:22px;padding-top:12px}.mail-attachments li{margin:8px 0;overflow-wrap:anywhere}.mail-attachments small{display:block;color:#586c80}.mail-reply-actions{margin-top:16px;display:flex;gap:8px;flex-wrap:wrap}.mail-reply-actions button{padding:9px;border:1px solid #dce3eb;border-radius:7px}</style></head><body><main>
<h1>E-mail</h1>

<div class="mail-toolbar" role="search">
<label for="mail-search"><strong>Zoeken in e-mailbord</strong></label>
<input id="mail-search" type="search" placeholder="Zoek op afzender of onderwerp" autocomplete="off">
<?php foreach ($mailboxes as $mailbox): ?>
<a href="/office-test/email?mailbox=<?= (int)$mailbox['id'] ?>"><?= htmlspecialchars((string)$mailbox['label'], ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></a>
<?php endforeach; ?>
<a href="#mailbord">Modulebord</a>
<span class="muted">Verzonden en persoonlijke push: nog niet aangesloten</span>
</div>
<?php if ($mailSchemaReady): ?>
<section id="mailbord"><h2>E-mail per module</h2><p class="muted">Persoonlijke berichten staan uitsluitend voor de eigenaar in Privé. Overdracht naar een zakelijke module vereist een afzonderlijke, expliciete actie en is nog niet beschikbaar.</p>
<?php if (!$boardEnabled): ?><p class="status">Modulebord-database nog niet geactiveerd.</p>
<?php else: ?>
<div class="mail-workspace<?= $openedMessage !== null ? ' is-open' : '' ?>">
<?php if ($openedMessage !== null): ?>
<div class="mail-thread-list" aria-label="Berichten in huidige module">
<a class="back-link" href="/office-test/email#mailbord">← Terug naar moduleoverzicht</a>
<h3><?= htmlspecialchars((string)($selectedCard['module_key'] ?? 'E-mail'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></h3>
<?php foreach ($boardCards as $threadCard): if ($threadCard['module_key'] !== $selectedCard['module_key']) continue; ?>
<a class="thread-item<?= (int)$threadCard['id']===$selectedMailId ? ' active' : '' ?>" href="/office-test/email?inspect_mail=<?= (int)$threadCard['id'] ?>#mailbord">
<strong><?= htmlspecialchars((string)$threadCard['subject'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></strong>
<small><?= htmlspecialchars((string)$threadCard['sender'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></small>
</a>
<?php endforeach; ?>
</div>
<article class="mail-reading" aria-label="Geopende e-mail">
<h2><?= htmlspecialchars((string)$openedMessage['subject'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></h2>
<p><strong>Van:</strong> <?= htmlspecialchars((string)$openedMessage['sender'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></p>
<p><strong>Aan:</strong> <?= htmlspecialchars((string)$openedMessage['recipient'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></p>
<p class="muted"><?= htmlspecialchars((string)$openedMessage['received_at'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></p>
<hr>
<div class="mail-body"><?= htmlspecialchars((string)($openedMessage['body_text'] ?: 'Geen platte berichttekst beschikbaar.'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></div>
<section class="mail-attachments" aria-label="Bijlagen">
<h3>Bijlagen</h3>
<?php if ($attachmentStatus === 'unavailable'): ?>
<p class="muted">Bijlagegegevens tijdelijk niet beschikbaar.</p>
<?php elseif ($openedAttachments === []): ?>
<p class="muted">Geen opgeslagen bijlagen bij dit bericht.</p>
<?php else: ?>
<ul><?php foreach ($openedAttachments as $attachment): ?>
<li><strong><?= htmlspecialchars((string)$attachment['filename'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></strong>
<?php if (getenv('OFFICE_MAIL_ATTACHMENT_STORAGE_ROOT')): ?>
<a href="/office-test/email?download_attachment=<?= (int)$attachment['id'] ?>" aria-label="Download bijlage">Download</a>
<?php endif; ?>
<small><?= htmlspecialchars((string)$attachment['content_type'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?> · <?= number_format((int)$attachment['byte_size']/1024,1,',','.') ?> KB</small>
<?php if (($attachmentDuplicateCounts[(string)$attachment['sha256']] ?? 0) > 1): ?>
<small class="status">Zelfde bestand aangetroffen in <?= (int)$attachmentDuplicateCounts[(string)$attachment['sha256']] ?> toegankelijke berichten</small>
<?php endif; ?></li>
<?php endforeach; ?></ul>
<p class="muted">Downloaden is alleen beschikbaar wanneer de private bijlagenopslag is geconfigureerd en het originele bestand aanwezig en intact is.</p>
<?php endif; ?>
</section>
<div class="mail-reply-actions"><button type="button" disabled title="Verzenden nog niet aangesloten">Beantwoorden (binnenkort)</button> <button type="button" disabled title="Verzenden nog niet aangesloten">Doorsturen (binnenkort)</button></div>
</article>
<?php endif; ?>
<aside class="mail-detail" aria-label="Dossierherkenning">
<?php if (is_int($selectedMailId)): ?>
<div class="candidate-panel"><h3>Automatische dubbelingencontrole - bericht #<?= (int)$selectedMailId ?></h3>
<p class="muted">Dit zijn uitsluitend mogelijke vervolgberichten met dezelfde afzender en hetzelfde onderwerp. Er wordt niets automatisch gekoppeld of aangemaakt.</p>
<h4>CRM-relatieherkenning</h4>
<?php if ($crmRecognitionStatus === 'checked'): ?>
<?php if ($crmRelationCandidates === []): ?><p>Geen bestaande actieve CRM-relatie op dit afzenderadres gevonden.</p>
<?php else: ?><ul><?php foreach ($crmRelationCandidates as $relationCandidate): ?>
<li><a href="/office-test/crm/relaties/detail?id=<?= urlencode((string)$relationCandidate['id']) ?>"><?= htmlspecialchars((string)$relationCandidate['display_name'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?></a> — <?= htmlspecialchars((string)$relationCandidate['reason'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?></li>
<?php endforeach; ?></ul><?php endif; ?>
<?php elseif ($crmRecognitionStatus === 'unavailable'): ?><p>CRM-herkenning tijdelijk niet beschikbaar; e-mailcontrole blijft actief.</p>
<?php else: ?><p>CRM-herkenning niet actief of geen toegang (privéberichten worden niet gecontroleerd).</p><?php endif; ?>
<h4>Dossiervoorstellen: gebouwen en projecten</h4>
<?php if ($selectedCard !== null && (string)$selectedCard['module_key'] !== 'prive'): ?>
<form method="post" action="/office-test/email#mailbord" style="margin:12px 0">
<input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?>">
<input type="hidden" name="message_id" value="<?= (int)$selectedMailId ?>">
<input type="hidden" name="module" value="nieuw">
<button type="submit">Later beoordelen (naar Nieuw)</button>
</form>
<?php endif; ?>
<p class="muted">Controleer alle gevonden adressen. Open het bestaande gebouw of CRM-dossier om de juiste vervolgactie te bepalen. Automatisch koppelen is uitgeschakeld.</p>
<?php if ($buildingRecognitionStatus === 'checked'): ?>
<?php if ($buildingProjectCandidates['buildings'] === []): ?><p>Geen toegankelijk gebouw gevonden op basis van postcodes in onderwerp of berichttekst.</p>
<?php else: ?>
<ul><?php foreach ($buildingProjectCandidates['buildings'] as $buildingCandidate): ?>
<li><a href="/office-test/gebouwen?id=<?= (int)$buildingCandidate['id'] ?>"><?= htmlspecialchars((string)$buildingCandidate['building_name'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></a> — <?= htmlspecialchars((string)$buildingCandidate['street_address'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?>, <?= htmlspecialchars((string)$buildingCandidate['postal_code'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?> <?= htmlspecialchars((string)$buildingCandidate['city'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></li>
<?php endforeach; ?></ul>
<?php if ($buildingProjectCandidates['projects'] !== []): ?><p>Toegankelijke projecten bij deze gebouwen:</p><ul>
<?php foreach ($buildingProjectCandidates['projects'] as $projectCandidate): ?>
<li>Project #<?= (int)$projectCandidate['project_id'] ?> (gebouw #<?= (int)$projectCandidate['building_id'] ?>)</li>
<?php endforeach; ?></ul><?php endif; ?>
<p class="muted">Bij meerdere postcodes worden alle toegankelijke kandidaten getoond. Controleer straat, huisnummer en toevoeging voordat je een dossier kiest.</p>
<?php endif; ?>
<?php elseif ($buildingRecognitionStatus === 'unavailable'): ?><p>Gebouwenherkenning tijdelijk niet beschikbaar.</p>
<?php else: ?><p>Gebouwenherkenning niet actief of privébericht.</p><?php endif; ?>
<h4>Mogelijk eerdere e-mails</h4>
<?php if ($duplicateCandidates === []): ?><p>Geen overeenkomende e-mails gevonden in de gecontroleerde selectie.</p>
<?php else: ?><ul><?php foreach ($duplicateCandidates as $candidate): ?><li><strong><?= htmlspecialchars($candidate['subject'], ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></strong> - <?= htmlspecialchars($candidate['received_at'], ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?> (bericht #<?= (int)$candidate['id'] ?>)</li><?php endforeach; ?></ul><?php endif; ?>
</div>
<?php endif; ?>
</aside>
<?php if ($openedMessage === null && ($_GET['move'] ?? '') === 'saved'): ?><p>Bericht verplaatst. De wijziging is vastgelegd.</p><?php endif; ?>
<?php if ($openedMessage === null): ?>
<div class="board" id="office-mail-columns">
<?php foreach (['nieuw'=>'Nieuw','prive'=>'Privé','crm'=>'CRM','projecten'=>'Projecten','finance'=>'Finance','documenten'=>'Documenten','inspecties'=>'Inspecties'] as $key=>$label): ?>
<section class="lane" data-module="<?= $key ?>"><h3><?= $label ?></h3>
<?php foreach ($boardCards as $card): if ($card['module_key'] !== $key) continue; ?>
<article class="mail-card" draggable="true" tabindex="0" role="link" aria-label="Open e-mail: <?= htmlspecialchars((string)$card['subject'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?>" data-message="<?= (int)$card['id'] ?>" data-open-url="/office-test/email?inspect_mail=<?= (int)$card['id'] ?>#mailbord">
<strong><?= htmlspecialchars((string)$card['subject'], ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8') ?></strong>
<p><a href="/office-test/email?inspect_mail=<?= (int)$card['id'] ?>#mailbord">Open e-mail en herkenning</a></p>
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
<?php endif; ?></div>
<script>
(() => {
 const search=document.getElementById('mail-search');
 if(search){
   const cards=Array.from(document.querySelectorAll('.mail-card'));
   search.addEventListener('input',()=>{
     const q=search.value.trim().toLocaleLowerCase('nl');
     cards.forEach(card=>{card.hidden=q!==''&&!card.textContent.toLocaleLowerCase('nl').includes(q);});
   });
 }

 let active=null;
 document.querySelectorAll('.mail-card').forEach(card=>{
   // Clicking the card opens its message; embedded controls keep their own actions.
   card.addEventListener('click',event=>{
     if(event.target.closest('a,button,select,input,textarea,label,form'))return;
     if(card.dataset.openUrl)window.location.assign(card.dataset.openUrl);
   });
   card.addEventListener('keydown',event=>{
     if(event.target!==card || (event.key!=='Enter' && event.key!==' '))return;
     event.preventDefault();
     if(card.dataset.openUrl)window.location.assign(card.dataset.openUrl);
   });
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
 if(!board)return;
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
