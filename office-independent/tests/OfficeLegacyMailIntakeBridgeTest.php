<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Communication/OfficeCommunicationRepository.php';
require_once dirname(__DIR__) . '/src/Communication/OfficeLegacyMailIntakeBridge.php';

use Brebo\Office\Communication\OfficeCommunicationRepository;
use Brebo\Office\Communication\OfficeLegacyMailIntakeBridge;

if (!extension_loaded('pdo_sqlite')) {
    echo "SKIP: pdo_sqlite unavailable\n";
    exit(0);
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_communication_messages (
 id INTEGER PRIMARY KEY AUTOINCREMENT, mailbox_id INTEGER NOT NULL,
 folder TEXT NOT NULL, uidvalidity INTEGER NOT NULL, imap_uid INTEGER NOT NULL,
 source_message_id TEXT, sender_address TEXT, subject TEXT, body_text TEXT,
 received_at TEXT, UNIQUE(mailbox_id, folder, uidvalidity, imap_uid))');
$bridge = new OfficeLegacyMailIntakeBridge();
$repository = new OfficeCommunicationRepository($db);
$message = [
 'source_id' => 'message-id:<invoice@example.test>',
 'from' => 'vendor@example.test', 'subject' => 'Factuur',
 'body' => 'Testfactuur', 'received_at' => '2026-10-31T10:00:00+00:00'
];
$id = $bridge->import($repository, 1, 'INBOX', 42, 77, $message);
$again = $bridge->import($repository, 1, 'INBOX', 42, 77, $message);
if ($id < 1 || $again !== $id || (int) $db->query('SELECT COUNT(*) FROM office_communication_messages')->fetchColumn() !== 1) {
 throw new RuntimeException('Legacy import must be idempotent.');
}
$row = $db->query('SELECT source_message_id, sender_address FROM office_communication_messages')->fetch(PDO::FETCH_ASSOC);
if ($row['source_message_id'] !== '<invoice@example.test>' || $row['sender_address'] !== 'vendor@example.test') {
 throw new RuntimeException('Legacy mail metadata lost.');
}
echo "OfficeLegacyMailIntakeBridgeTest PASS\n";
