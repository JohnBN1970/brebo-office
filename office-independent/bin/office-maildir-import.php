<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (getenv('OFFICE_BASE_PATH') !== '/office-test' || getenv('OFFICE_TEST_MAIL_DELIVERY_ENABLED') !== 'enabled') exit(2);
require_once __DIR__.'/../src/Mail/OfficeNativeMailboxRepository.php';
require_once __DIR__.'/../src/Mail/OfficeFunctionalMailIngest.php';
require_once __DIR__.'/../src/Mail/OfficeMaildirNormalizer.php';
$db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '', getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'brebo_office_test') exit(2);
$lock = fopen(sys_get_temp_dir().'/brebo-office-maildir-import.lock','c');
if (!$lock || !flock($lock, LOCK_EX|LOCK_NB)) exit(0);
$ingest = new \Brebo\Office\Mail\OfficeFunctionalMailIngest($db,new \Brebo\Office\Mail\OfficeNativeMailboxRepository($db));
$parser = new \Brebo\Office\Mail\OfficeMaildirNormalizer();
$root = '/var/vmail/testmail.brebobv.nl';
$failures = $processed = 0;
foreach ($db->query("SELECT id,address FROM office_mail_mailboxes WHERE active=1 AND mailbox_type='functional' AND gateway_ref IS NOT NULL AND address LIKE '%@testmail.brebobv.nl'")->fetchAll(PDO::FETCH_ASSOC) as $box) {
  if (!preg_match('/^([a-z0-9][a-z0-9._+-]*)@testmail\.brebobv\.nl$/D',$box['address'],$m)) { ++$failures; continue; }
  $home = $root.'/'.$m[1];
  foreach (['new','cur'] as $folder) {
    $dir = $home.'/'.$folder;
    if (realpath($dir) !== $dir || !is_readable($dir)) { ++$failures; continue; }
    foreach (new DirectoryIterator($dir) as $file) {
      if ($file->isDot() || $file->isLink() || !$file->isFile()) continue;
      try {
        if ($file->getSize() > 26214400) throw new RuntimeException('Message exceeds import limit');
        $raw = file_get_contents($file->getPathname(),false,null,0,26214401);
        if ($raw === false || strlen($raw)>26214400) throw new RuntimeException('Read failed');
        $msg = $parser->normalize($raw,$box['address']);
        $ingest->store((int)$box['id'],[
          'source_id'=>'maildir:'.hash('sha256',$raw),
          'sender'=>mb_strcut($msg['from'],0,320,'UTF-8'),
          'recipient'=>$box['address'],
          'subject'=>mb_strcut($msg['subject'],0,512,'UTF-8'),
          'received_at'=>gmdate('Y-m-d H:i:s',$file->getMTime()),
          'body_text'=>mb_strcut($msg['text'],0,2097152,'UTF-8'),
        ]);
        ++$processed;
      } catch (Throwable $e) { ++$failures; error_log('maildir_import_failed mailbox='.(int)$box['id'].' type='.$e::class); }
    }
  }
}
echo "MAILDIR_PROCESSED=$processed\nMAILDIR_FAILURES=$failures\n";
exit($failures ? 1 : 0);
