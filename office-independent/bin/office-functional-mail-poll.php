<?php
declare(strict_types=1);

// CLI-only native Office functional mailbox polling. Run via Office-owned cron.
// Configure secrets only in private environment, never in web root or repository.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
  || getenv('OFFICE_NATIVE_MAIL_SCHEMA_VERIFIED') !== 'enabled'
  || getenv('OFFICE_FUNCTIONAL_MAIL_CRON_ENABLED') !== 'enabled') {
  fwrite(STDERR, "Office mail polling disabled.\n"); exit(2);
}
if (!extension_loaded('imap')) {
  fwrite(STDERR, "PHP IMAP extension unavailable.\n"); exit(2);
}
require_once dirname(__DIR__) . '/src/Mail/OfficeNativeMailboxRepository.php';
require_once dirname(__DIR__) . '/src/Mail/OfficeFunctionalMailIngest.php';
$dsn = getenv('OFFICE_INTAKE_PDO_DSN') ?: '';
$db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '',
  getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'u213420663_office_test') {
  fwrite(STDERR, "Wrong database.\n"); exit(2);
}
$lock = fopen(sys_get_temp_dir() . '/brebo-office-functional-mail.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) exit(0);
$repository = new \Brebo\Office\Mail\OfficeNativeMailboxRepository($db);
$ingest = new \Brebo\Office\Mail\OfficeFunctionalMailIngest($db, $repository);
$mailboxes = $db->query("SELECT id, credential_ref FROM office_mail_mailboxes
  WHERE active = 1 AND mailbox_type = 'functional' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$failures = 0;
foreach ($mailboxes as $mailbox) {
  $id = (int) $mailbox['id'];
  $ref = (string) $mailbox['credential_ref'];
  if (!preg_match('/^[A-Z][A-Z0-9_]{0,80}$/D', $ref)) { ++$failures; continue; }
  $server = getenv($ref . '_IMAP_SERVER');
  $username = getenv($ref . '_IMAP_USER');
  $password = getenv($ref . '_IMAP_PASSWORD');
  if (!$server || !$username || !$password || !preg_match('/^[a-zA-Z0-9.-]+$/D', $server)) {
    ++$failures; continue;
  }
  $stream = @imap_open('{'.$server.':993/imap/ssl/validate-cert}INBOX', $username, $password, OP_READONLY, 1);
  if ($stream === false) { ++$failures; continue; }
  try {
    $status = imap_status($stream, '{'.$server.':993/imap/ssl/validate-cert}INBOX', SA_UIDVALIDITY);
    if (!$status || empty($status->uidvalidity)) throw new RuntimeException('IMAP UIDVALIDITY unavailable.');
    $uidValidity = (int) $status->uidvalidity;
    $overview = imap_check($stream);
    $last = $overview ? (int) $overview->Nmsgs : 0;
    $start = max(1, $last - 99);
    for ($n = $start; $n <= $last; ++$n) {
      $uid = imap_uid($stream, $n);
      if (!$uid) continue;
      $head = imap_headerinfo($stream, $n);
      if (!$head) continue;
      $date = isset($head->udate) && (int) $head->udate > 0 ? (int) $head->udate : time();
      $sender = isset($head->from[0]) ? (($head->from[0]->mailbox ?? '') . '@' . ($head->from[0]->host ?? '')) : '';
      $subject = isset($head->subject) ? imap_utf8((string) $head->subject) : '';
      // Body/attachments require MIME-safe extraction in a separate, reviewed step.
      $ingest->store($id, [
        'source_id' => 'imap:' . $uidValidity . ':' . $uid,
        'sender' => substr($sender, 0, 320),
        'recipient' => '',
        'subject' => substr($subject, 0, 512),
        'received_at' => gmdate('Y-m-d H:i:s', $date),
        'body_text' => null,
      ]);
    }
  } catch (Throwable $e) {
    error_log('office_functional_mail_poll_failed mailbox=' . $id . ' type=' . $e::class);
    ++$failures;
  } finally {
    imap_close($stream);
  }
}
flock($lock, LOCK_UN);
fclose($lock);
exit($failures > 0 ? 1 : 0);
