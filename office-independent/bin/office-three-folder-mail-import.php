<?php
declare(strict_types=1);

/**
 * Explicit three-folder, read-only IMAP import for Office-test.
 * DRY RUN is the default. Live mode requires TWO independent opt-in flags.
 * Never opens INBOX, never discovers extra folders, never writes to Zoho.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
    || getenv('OFFICE_NATIVE_MAIL_SCHEMA_VERIFIED') !== 'enabled'
    || !extension_loaded('imap')) {
    fwrite(STDERR, "Office-test mail prerequisites missing.\n"); exit(2);
}
$live = in_array('--import', $argv, true);
if ($live && (getenv('OFFICE_THREE_FOLDER_IMPORT_ENABLED') !== 'enabled'
    || getenv('OFFICE_THREE_FOLDER_IMPORT_CONFIRMED') !== 'enabled')) {
    fwrite(STDERR, "Import disabled.\n"); exit(2);
}
require_once dirname(__DIR__) . '/src/Mail/OfficeNativeMailboxRepository.php';
require_once dirname(__DIR__) . '/src/Mail/OfficeFunctionalMailIngest.php';
require_once dirname(__DIR__) . '/src/Mail/OfficeImapTextExtractor.php';
$dsn = getenv('OFFICE_INTAKE_PDO_DSN') ?: '';
$db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '',
    getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'u213420663_office_test') {
    fwrite(STDERR, "Wrong database.\n"); exit(2);
}
$ref = getenv('OFFICE_THREE_FOLDER_CREDENTIAL_REF') ?: '';
if (!preg_match('/^[A-Z][A-Z0-9_]{0,80}$/D', $ref)) {
    fwrite(STDERR, "Invalid credential reference.\n"); exit(2);
}
$server = getenv($ref . '_IMAP_SERVER') ?: '';
$user = getenv($ref . '_IMAP_USER') ?: '';
$password = getenv($ref . '_IMAP_PASSWORD') ?: '';
if (!preg_match('/^[a-zA-Z0-9.-]+$/D', $server) || $user === '' || $password === '') {
    fwrite(STDERR, "Missing IMAP credentials.\n"); exit(2);
}
$mailboxId = filter_var(getenv('OFFICE_THREE_FOLDER_MAILBOX_ID'), FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]);
if ($mailboxId === false) { fwrite(STDERR, "Mailbox ID missing.\n"); exit(2); }
$mb = $db->prepare("SELECT id,address,credential_ref FROM office_mail_mailboxes WHERE id=:id AND active=1 AND mailbox_type='functional'");
$mb->execute([':id' => $mailboxId]);
$mailbox = $mb->fetch(PDO::FETCH_ASSOC);
if (!$mailbox || $mailbox['credential_ref'] !== $ref) {
    fwrite(STDERR, "Functional mailbox mapping not verified.\n"); exit(2);
}
if ($live) {
    $exists = $db->query("SHOW TABLES LIKE 'office_mail_folder_cursors'")->fetchColumn();
    if (!$exists) { fwrite(STDERR, "Folder cursor migration missing.\n"); exit(2); }
}
$lock = fopen(sys_get_temp_dir() . '/brebo-office-three-folder-mail.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit(2);
$ingest = $live ? new \Brebo\Office\Mail\OfficeFunctionalMailIngest(
    $db, new \Brebo\Office\Mail\OfficeNativeMailboxRepository($db)) : null;
$root = '{' . $server . ':993/imap/ssl/validate-cert}';
$folders = [
    'BREBO-Office-Info' => null,
    'Facturen' => 'finance',
    'Offerteaanvraag' => 'crm',
];
// 13 Oct 2026 00:00 Europe/Amsterdam = 12 Oct 2026 22:00 UTC.
$cutoff = new DateTimeImmutable('2026-10-13 00:00:00', new DateTimeZone('Europe/Amsterdam'));
$cutoffTs = $cutoff->getTimestamp();
$failures = 0;
foreach ($folders as $folder => $suggestedModule) {
    $stream = @imap_open($root . $folder, $user, $password, OP_READONLY, 1);
    if ($stream === false) { fwrite(STDERR, "FOLDER=$folder ERROR=OPEN_FAILED\n"); ++$failures; continue; }
    try {
        $status = imap_status($stream, $root . $folder, SA_UIDVALIDITY);
        if (!$status || empty($status->uidvalidity)) throw new RuntimeException('Missing UIDVALIDITY');
        $validity = (int)$status->uidvalidity;
        $savedUid = 0;
        if ($live) {
            $cursor = $db->prepare('SELECT uidvalidity,last_uid FROM office_mail_folder_cursors WHERE mailbox_id=:id AND folder_name=:folder');
            $cursor->execute([':id' => $mailboxId, ':folder' => $folder]);
            $saved = $cursor->fetch(PDO::FETCH_ASSOC);
            if ($saved && (int)$saved['uidvalidity'] === $validity) $savedUid = (int)$saved['last_uid'];
        }
        $count = (int)(imap_num_msg($stream) ?: 0);
        $eligible = 0; $processed = 0; $skipped = 0;
        // Read headers only until live import; all messages remain untouched on Zoho.
        for ($n = 1; $n <= $count; ++$n) {
            $uid = imap_uid($stream, $n);
            if (!$uid) throw new RuntimeException('UID lookup failed');
            if ($uid <= $savedUid) continue;
            $head = imap_headerinfo($stream, $n);
            if (!$head || !isset($head->udate) || (int)$head->udate <= 0) {
                throw new RuntimeException('Missing reliable message date');
            }
            $date = (int)$head->udate;
            if ($date < $cutoffTs) {
                ++$skipped;
            } else {
                ++$eligible;
                if ($live) {
                    $sender = isset($head->from[0])
                        ? (($head->from[0]->mailbox ?? '') . '@' . ($head->from[0]->host ?? '')) : '';
                    $subject = isset($head->subject) ? imap_utf8((string)$head->subject) : '';
                    $body = \Brebo\Office\Mail\OfficeImapTextExtractor::extract($stream, $n);
                    $ingest->store((int)$mailboxId, [
                        'source_id' => 'imap:' . $folder . ':' . $validity . ':' . $uid,
                        'sender' => substr($sender, 0, 320),
                        'recipient' => substr((string)$mailbox['address'], 0, 1000),
                        'subject' => substr($subject, 0, 512),
                        'received_at' => gmdate('Y-m-d H:i:s', $date),
                        'body_text' => $body,
                        'suggested_module' => $suggestedModule,
                    ]);
                    ++$processed;
                }
            }
            if ($live) {
                // Advance even for pre-cutoff mail, but only after any eligible ingest succeeded.
                $save = $db->prepare('INSERT INTO office_mail_folder_cursors
                    (mailbox_id,folder_name,uidvalidity,last_uid,updated_at)
                    VALUES (:id,:folder,:validity,:uid,UTC_TIMESTAMP())
                    ON DUPLICATE KEY UPDATE uidvalidity=VALUES(uidvalidity),
                    last_uid=VALUES(last_uid),updated_at=UTC_TIMESTAMP()');
                $save->execute([':id'=>$mailboxId, ':folder'=>$folder,
                    ':validity'=>$validity, ':uid'=>$uid]);
            }
        }
        echo 'FOLDER=' . $folder . ' TOTAL=' . $count . ' ELIGIBLE=' . $eligible
            . ' SKIPPED_OLD=' . $skipped . ' IMPORTED=' . $processed
            . ' MODE=' . ($live ? 'IMPORT' : 'DRY_RUN') . "\n";
    } catch (Throwable $e) {
        error_log('office_three_folder_poll_failed folder=' . $folder . ' type=' . $e::class);
        fwrite(STDERR, "FOLDER=$folder ERROR=PROCESSING_FAILED\n");
        ++$failures;
    } finally { imap_close($stream); }
}
flock($lock, LOCK_UN); fclose($lock);
exit($failures ? 1 : 0);
