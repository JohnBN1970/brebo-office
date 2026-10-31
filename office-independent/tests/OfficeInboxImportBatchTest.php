<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Communication/OfficeCommunicationRepository.php';
require_once dirname(__DIR__) . '/src/Communication/OfficeLegacyMailIntakeBridge.php';
require_once dirname(__DIR__) . '/src/Communication/OfficeInboxImportBatch.php';
use Brebo\Office\Communication\OfficeCommunicationRepository;
use Brebo\Office\Communication\OfficeLegacyMailIntakeBridge;
use Brebo\Office\Communication\OfficeInboxImportBatch;
if (!extension_loaded('pdo_sqlite')) { echo "SKIP pdo_sqlite unavailable\n"; exit(0); }
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_communication_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, mailbox_id INTEGER NOT NULL, folder TEXT NOT NULL, uidvalidity INTEGER NOT NULL, imap_uid INTEGER NOT NULL, source_message_id TEXT, sender_address TEXT, subject TEXT, body_text TEXT, received_at TEXT, UNIQUE(mailbox_id,folder,uidvalidity,imap_uid))');
$import = new OfficeInboxImportBatch(new OfficeLegacyMailIntakeBridge(), new OfficeCommunicationRepository($db));
$source = ['raw_headers'=>"Content-Type: text/plain; charset=UTF-8\r\n",'source_id'=>'message-id:<a@test.invalid>','body'=>'Hello','subject'=>'Test','from'=>'test@example.invalid'];
$batch = ['uidvalidity'=>5,'messages'=>[['uid'=>10,'source'=>$source]]];
$first = $import->import(1,'INBOX',$batch);
$second = $import->import(1,'INBOX',$batch);
if ($first !== $second || count($first) !== 1) throw new RuntimeException('Idempotency failure.');
$batch['messages'][] = ['uid'=>11,'source'=>array_merge($source,['raw_headers'=>"Content-Type: multipart/mixed; boundary=x\r\n"])];
try { $import->import(1,'INBOX',$batch); throw new RuntimeException('Multipart import allowed.'); }
catch (RuntimeException $e) { if ($e->getMessage() === 'Multipart import allowed.') throw $e; }
if ((int) $db->query('SELECT COUNT(*) FROM office_communication_messages')->fetchColumn() !== 1) throw new RuntimeException('Partial batch written.');
echo "OfficeInboxImportBatchTest PASS\n";
